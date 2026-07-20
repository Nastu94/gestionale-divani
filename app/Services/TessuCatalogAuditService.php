<?php

namespace App\Services;

use App\Models\Fabric;
use App\Models\Color;
use App\Models\Product;
use App\Models\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TessuCatalogAuditService
{
    /**
     * Esegue l'audit globale e per prodotto.
     * Restituisce una struttura con i risultati classificati.
     *
     * @param array|null $fabricIds  Opzionale, array di fabric ID da filtrare
     * @param array|null $colorIds   Opzionale, array di color ID da filtrare
     * @param array|null $productIds Opzionale, array di product ID per la verifica whitelist
     * @return array
     */
    public function audit(?array $fabricIds = null, ?array $colorIds = null, ?array $productIds = null): array
    {
        $results = [
            'global_catalog' => collect(),
            'product_availability' => collect(),
            'stats' => [
                'total_fabrics' => 0,
                'total_colors' => 0,
                'total_components' => 0,
                'missing_components' => 0,
                'inactive_components' => 0,
                'archived_components' => 0,
                'wrong_category' => 0,
            ]
        ];

        // 1. Lettura catalogo globale
        $fabricsQuery = Fabric::query()->when($fabricIds, fn ($q) => $q->whereIn('id', $fabricIds));
        $colorsQuery = Color::query()->when($colorIds, fn ($q) => $q->whereIn('id', $colorIds));

        $fabrics = $fabricsQuery->get();
        $colors = $colorsQuery->get();

        $results['stats']['total_fabrics'] = $fabrics->count();
        $results['stats']['total_colors']  = $colors->count();

        // Mapping dei componenti esistenti
        $components = Component::query()
            ->when($fabricIds, fn ($q) => $q->whereIn('fabric_id', $fabricIds))
            ->when($colorIds, fn ($q) => $q->whereIn('color_id', $colorIds))
            ->whereNotNull('fabric_id')
            ->whereNotNull('color_id')
            // Se esiste il trait SoftDeletes o una colonna manuale, includiamoli per capire lo stato
            ->when(\Schema::hasColumn('components', 'deleted_at'), fn ($q) => $q->withTrashed())
            ->get();
            
        $results['stats']['total_components'] = $components->count();

        // Mappa per lookup: "fabric_id:color_id" -> lista di componenti
        $componentGrouped = $components->groupBy(function ($c) {
            return $c->fabric_id . ':' . $c->color_id;
        });

        $tessuCategoryId = app(\App\Services\TessuComponentResolver::class)->getTessuCategoryId();

        // Analizziamo solo i componenti esistenti (non il prodotto cartesiano di tutto)
        foreach ($componentGrouped as $key => $comps) {
            // Prendi il primo per capire fabric e color
            $first = $comps->first();
            $fabric = $fabrics->firstWhere('id', $first->fabric_id);
            $color = $colors->firstWhere('id', $first->color_id);

            if (!$fabric || !$color) continue; // Orfani (ignoriamo per ora)

            $isFabricActive = (bool) ($fabric->active ?? true);
            $isFabricArchived = !is_null($fabric->deleted_at);
            
            $isColorActive = (bool) ($color->active ?? true);
            $isColorArchived = !is_null($color->deleted_at);

            foreach ($comps as $comp) {
                $status = 'valid';

                if ($isFabricArchived) { $status = 'archived_fabric'; }
                elseif ($isColorArchived) { $status = 'archived_color'; }
                elseif (!$isFabricActive) { $status = 'inactive_fabric'; }
                elseif (!$isColorActive) { $status = 'inactive_color'; }
                else {
                    if ((int)$comp->category_id !== $tessuCategoryId) {
                        $status = 'wrong_category';
                        $results['stats']['wrong_category']++;
                    } elseif (!is_null($comp->deleted_at)) {
                        $status = 'archived_component';
                        $results['stats']['archived_components']++;
                    } elseif (!(bool)$comp->is_active) {
                        $status = 'inactive_component';
                        $results['stats']['inactive_components']++;
                    }
                }

                $results['global_catalog']->push([
                    'fabric_id' => $fabric->id,
                    'fabric_name' => $fabric->name,
                    'color_id' => $color->id,
                    'color_name' => $color->name,
                    'status' => $status,
                    'component_id' => $comp->id,
                ]);
            }
            
            // Check duplicati attivi
            $activeCount = $comps->filter(fn($c) => is_null($c->deleted_at) && $c->is_active && (int)$c->category_id === $tessuCategoryId)->count();
            if ($activeCount > 1) {
                $results['global_catalog']->push([
                    'fabric_id' => $fabric->id,
                    'fabric_name' => $fabric->name,
                    'color_id' => $color->id,
                    'color_name' => $color->name,
                    'status' => 'duplicate_components',
                    'component_id' => null,
                ]);
            }
        }

        // 2. Controllo disponibilità su Prodotti
        if ($productIds) {
            $products = Product::whereIn('id', $productIds)->with(['fabrics', 'colors'])->get();
            
            // Per velocizzare l'analisi valid_pairs in memoria
            $resolver = app(TessuComponentResolver::class);

            foreach ($products as $product) {
                if (!$resolver->tessuSlot($product)) {
                    continue; // Skip se non richiede TESSU
                }

                $pFabrics = $product->fabrics->pluck('id')->toArray();
                $pColors = $product->colors->pluck('id')->toArray();
                $validPairs = collect($resolver->validActivePairs($product))->pluck('component_id')->toArray();

                // Analizza la matrice per questo prodotto
                foreach ($pFabrics as $fid) {
                    foreach ($pColors as $cid) {
                        $compKey = $fid . ':' . $cid;
                        $comps = $componentGrouped->get($compKey) ?? collect();
                        
                        // Per il prodotto, cerchiamo se c'è almeno un componente valido
                        $validComp = $comps->first(fn($c) => in_array($c->id, $validPairs));

                        if ($validComp) {
                            $status = 'valid';
                            $comp = $validComp;
                        } else {
                            $comp = $comps->first(); // Ne prendiamo uno a caso se ci sono errori
                            
                            if (!$comp) {
                                $status = 'missing_component';
                                $results['stats']['missing_components']++;
                            } elseif ((int)$comp->category_id !== $tessuCategoryId) {
                                $status = 'wrong_category';
                            } elseif (!is_null($comp->deleted_at)) {
                                $status = 'archived_component';
                            } elseif (!(bool)$comp->is_active) {
                                $status = 'inactive_component';
                            } else {
                                $status = 'wrong_category_or_invalid';
                            }
                        }

                        $results['product_availability']->push([
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'fabric_id' => $fid,
                            'color_id' => $cid,
                            'status' => $status,
                            'component_id' => $comp?->id,
                        ]);
                    }
                }
            }
        }

        return $results;
    }
}
