<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Product;
use App\Models\ComponentCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Exceptions\BusinessRuleException;

class TessuComponentResolver
{
    /**
     * Riconosce lo slot BOM TESSU per il prodotto.
     */
    public function tessuSlot(Product $product, bool $withTrashed = false): ?Component
    {
        $query = $product->components()
            ->where('product_components.is_variable', 1)
            ->where('product_components.variable_slot', 'TESSU');

        // I componenti usano SoftDeletes, ma product_components non lo usa.
        // Se il componente collegato è cestinato, withTrashed() ci permette di trovarlo.
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }

    /**
     * Determina il vero default configurato verificando product_fabrics.is_default e product_colors.is_default.
     * Un default si applica solo se c'è 1 solo tessuto predefinito e 1 solo colore predefinito,
     * entrambi in whitelist e con un componente TESSU attivo esatto.
     */
    public function configuredDefaultPair(Product $product): ?array
    {
        if (!$this->tessuSlot($product)) {
            return null;
        }

        $defaultFabrics = $product->fabrics()->wherePivot('is_default', 1)->get();
        $defaultColors = $product->colors()->wherePivot('is_default', 1)->get();

        if ($defaultFabrics->count() === 1 && $defaultColors->count() === 1) {
            $fabricId = $defaultFabrics->first()->id;
            $colorId = $defaultColors->first()->id;
            
            $slot = $this->tessuSlot($product);

            // Verifica che ci sia il componente esatto attivo
            $component = Component::where('category_id', $this->getTessuCategoryId())
                ->where('id', '!=', $slot->id)
                ->where('fabric_id', $fabricId)
                ->where('color_id', $colorId)
                ->where('is_active', 1)
                ->first();

            if ($component) {
                return [
                    'fabric_id' => $fabricId,
                    'color_id' => $colorId,
                    'component_id' => $component->id,
                ];
            }
        }

        return null;
    }

    /**
     * Intersezione tra whitelist tessuti, whitelist colori e componenti TESSU attivi esistenti.
     */
    public function validActivePairs(Product $product): array
    {
        $fabricIds = $product->fabricIds();
        $colorIds = $product->colorIds();

        if (empty($fabricIds) || empty($colorIds)) {
            return [];
        }

        $slot = $this->tessuSlot($product);
        if (!$slot) {
            return [];
        }

        $components = Component::where('category_id', $this->getTessuCategoryId())
            ->where('id', '!=', $slot->id)
            ->whereIn('fabric_id', $fabricIds)
            ->whereIn('color_id', $colorIds)
            ->where('is_active', 1)
            ->get(['id', 'fabric_id', 'color_id']);

        $pairs = [];
        foreach ($components as $component) {
            if (!is_null($component->fabric_id) && !is_null($component->color_id)) {
                $pairs[] = [
                    'fabric_id' => $component->fabric_id,
                    'color_id' => $component->color_id,
                    'component_id' => $component->id,
                ];
            }
        }

        return $pairs;
    }

    /**
     * Risolve il componente attivo esatto per nuove configurazioni o modifiche utente esplicite.
     */
    public function resolveForNewLine(Product $product, ?int $fabricId, ?int $colorId): ?Component
    {
        $slot = $this->tessuSlot($product);
        if (!$slot) {
            return null; // Nessun TESSU richiesto
        }

        // Se entrambi mancano, valuta il vero default
        if (is_null($fabricId) && is_null($colorId)) {
            $defaultPair = $this->configuredDefaultPair($product);
            if ($defaultPair) {
                $fabricId = $defaultPair['fabric_id'];
                $colorId = $defaultPair['color_id'];
            } else {
                throw ValidationException::withMessages(['fabric_id' => 'La configurazione tessuto e colore è obbligatoria e nessun default automatico è configurato.']);
            }
        }

        if (is_null($fabricId) || is_null($colorId)) {
            throw ValidationException::withMessages(['fabric_id' => 'Tessuto e colore sono entrambi obbligatori per questo prodotto.']);
        }

        // Verifica whitelist
        if (!in_array($fabricId, $product->fabricIds()) || !in_array($colorId, $product->colorIds())) {
            throw ValidationException::withMessages(['fabric_id' => 'La combinazione tessuto-colore selezionata non è configurata nella whitelist di questo prodotto.']);
        }

        // Cerca il componente attivo esatto
        $components = Component::where('category_id', $this->getTessuCategoryId())
            ->where('id', '!=', $slot->id)
            ->where('fabric_id', $fabricId)
            ->where('color_id', $colorId)
            ->where('is_active', 1)
            ->get();

        if ($components->count() === 0) {
            throw ValidationException::withMessages(['fabric_id' => 'Nessun componente TESSU attivo trovato per questa combinazione tessuto-colore.']);
        }

        if ($components->count() > 1) {
            throw ValidationException::withMessages(['fabric_id' => 'Trovati componenti TESSU duplicati per questa combinazione tessuto-colore. Contattare l\'amministratore.']);
        }

        return $components->first();
    }

    /**
     * Recupera il componente salvato o la coppia esatta per righe storiche invariate, resi e stampe storiche.
     */
    public function resolveForStoredLine(
        Product $product,
        ?int $resolvedComponentId,
        ?int $fabricId,
        ?int $colorId
    ): Component {
        // Se c'è lo slot salvato, è preferito, anche soft deleted
        if ($resolvedComponentId) {
            $component = Component::withTrashed()->find($resolvedComponentId);
            if ($component) {
                if ((int)$component->category_id !== $this->getTessuCategoryId()) {
                    throw new BusinessRuleException("Il componente risolto salvato (ID {$resolvedComponentId}) ha una categoria non coerente con TESSU.");
                }
                
                // Verifica facoltativa di coerenza se tessuto e colore sono anche passati
                if (!is_null($fabricId) && !is_null($colorId)) {
                    if ((int)$component->fabric_id !== (int)$fabricId || (int)$component->color_id !== (int)$colorId) {
                        throw new BusinessRuleException("Il componente risolto salvato (ID {$resolvedComponentId}) non corrisponde alla coppia tessuto/colore salvata nell'ordine storico.");
                    }
                }
                
                return $component;
            }
        }

        // Fallback: proviamo a risolvere per la coppia esatta storicizzata (anche soft deleted)
        if (is_null($fabricId) || is_null($colorId)) {
            throw new BusinessRuleException('Dati storici incoerenti: ID componente risolto assente e coppia tessuto-colore incompleta.');
        }
        
        $slot = $this->tessuSlot($product);

        $components = Component::withTrashed()
            ->where('category_id', $this->getTessuCategoryId())
            ->when($slot, function($query) use ($slot) {
                $query->where('id', '!=', $slot->id);
            })
            ->where('fabric_id', $fabricId)
            ->where('color_id', $colorId)
            ->get();

        if ($components->count() === 0) {
            throw new BusinessRuleException('Dati storici incoerenti: nessun componente esatto trovato per la coppia salvata.');
        }

        if ($components->count() > 1) {
            throw new BusinessRuleException('Dati storici incoerenti: trovate corrispondenze multiple (duplicati) per la coppia salvata.');
        }

        return $components->first();
    }

    /**
     * Ritorna l'ID della categoria TESSU cachato (visto che è un lookup frequente).
     */
    public function getTessuCategoryId(): int
    {
        return Cache::remember('tessu_category_id', 3600, function () {
            $catId = ComponentCategory::query()->where('code', 'TESSU')->value('id');
            if (!$catId) {
                abort(500, 'Categoria TESSU non trovata nel sistema.');
            }
            return (int) $catId;
        });
    }
}
