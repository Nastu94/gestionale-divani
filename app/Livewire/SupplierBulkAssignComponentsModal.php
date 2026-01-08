<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modale Livewire: assegnazione massiva di componenti a un fornitore (supplier).
 *
 * STEP 1:
 * - Solo UI + filtri + lista (infinite scroll) + selezione checkbox
 * - Nessuna scrittura su DB (salvataggio lo facciamo nello step successivo)
 *
 * NOTE:
 * - is_assigned_to_supplier viene calcolato con "Opzione A":
 *   prefetch di tutti i component_id già assegnati al supplier e set in memoria.
 * - In lista mostriamo SOLO: codice + descrizione (come richiesto),
 *   gli altri campi servono solo per filtri e logica.
 */
class SupplierBulkAssignComponentsModal extends Component
{
    /** Stato apertura modale */
    public bool $open = false;

    /** Supplier selezionato */
    public ?int $supplierId = null;

    /** Nome supplier (solo UI) */
    public string $supplierName = '';

    /**
     * Filtri UI:
     * - q: ricerca su code/description
     * - category_id: filtro categoria
     * - active: all|1|0 (default 1 = solo attivi)
     * - only_unassigned: mostra solo componenti NON ancora assegnati
     */
    public array $filters = [
        'q'              => '',
        'category_id'    => null,
        'active'         => '1',
        'only_unassigned'=> true,
    ];

    /** Lista categorie (solo quelle con almeno un componente attivo) */
    public array $categories = [];

    /**
     * Mappa componenti già assegnati al supplier:
     * [component_id => true]
     * (lookup O(1) in Blade)
     */
    public array $assignedMap = [];

    /** Id selezionati (checkbox) */
    public array $selected = [];

    /** Numero di elementi caricati (infinite scroll: aumentiamo il limit) */
    public int $limit = 50;

    /** Flag per infinite scroll: se true, esistono ancora risultati oltre il limit attuale */
    public bool $hasMore = false;

    /** Messaggio esito operazioni (success/info/error) mostrato nel modale */
    public ?string $uiMessage = null;

    /** Tipo messaggio (success|info|error) */
    public string $uiMessageType = 'success';

    /**
     * Apre il modale (chiamato via evento Livewire da JS/Blade).
     *
     * @param int $supplierId   ID fornitore
     * @param string $supplierName Nome fornitore (opzionale, solo UI)
     */
    #[On('open-bulk-assign-components')]
    public function openModal(int $supplierId, string $supplierName = ''): void
    {
        $this->open = true;
        $this->uiMessage = null;

        $this->supplierId = $supplierId;
        $this->supplierName = $supplierName;

        // Reset selezione e paginazione
        $this->selected = [];
        $this->limit = 50;

        // Carica categorie e mappa assegnati
        $this->loadCategories();
        $this->loadAssignedMap();

        // Reset filtri (puoi cambiare default qui se vuoi)
        $this->filters['q'] = '';
        $this->filters['category_id'] = null;
        $this->filters['active'] = '1';
        $this->filters['only_unassigned'] = true;
    }

    /**
     * Chiude il modale e resetta lo stato principale.
     */
    public function closeModal(): void
    {
        $this->open = false;

        $this->supplierId = null;
        $this->supplierName = '';

        $this->selected = [];
        $this->assignedMap = [];

        $this->limit = 50;
        $this->hasMore = false;
    }

    /**
     * Quando cambiano i filtri, riazzeriamo la "paginazione" (limit)
     * mantenendo la selezione (come da richiesta UX).
     */
    public function updatedFilters(mixed $value, string $key): void
    {
        // Quando cambi filtri ripartiamo dall’inizio della lista.
        $this->limit = 50;

        // ✅ UX: riportiamo lo scroll all’inizio dopo un cambio filtro.
        $this->dispatch('bulk-assign-scroll-top');
    }

    /**
     * Infinite scroll: carica altri elementi aumentando il limit.
     * (Guardiamo hasMore per evitare richieste inutili)
     */
    public function loadMore(): void
    {
        if (! $this->hasMore) {
            return;
        }

        $this->limit += 50;
    }

    /**
     * Carica categorie con almeno un componente attivo.
     * Usiamo query DB (senza dipendere da relazioni Eloquent).
     */
    private function loadCategories(): void
    {
        $this->categories = DB::table('component_categories as cc')
            ->join('components as c', 'c.category_id', '=', 'cc.id')
            ->where('c.is_active', true)
            ->select(['cc.id', 'cc.name'])
            ->distinct()
            ->orderBy('cc.name')
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    /**
     * Toggle selezione di un componente (ID-based).
     * - Evita bug "per posizione" quando la lista cambia.
     * - Se il componente è già assegnato al fornitore, non è selezionabile.
     */
    public function toggleSelect(int $componentId): void
    {
        // Se è già assegnato, non permettiamo selezione
        if (isset($this->assignedMap[$componentId])) {
            return;
        }

        // Se già selezionato -> rimuovi
        if (in_array($componentId, $this->selected, true)) {
            $this->selected = array_values(array_filter(
                $this->selected,
                fn ($id) => (int) $id !== $componentId
            ));
            return;
        }

        // Altrimenti -> aggiungi
        $this->selected[] = $componentId;
    }

    /**
     * Prefetch (Opzione A) di tutti i componenti già assegnati al supplier.
     * Questo ci permette di:
     * - disabilitare checkbox in lista
     * - mostrare badge “Già assegnato”
     */
    private function loadAssignedMap(): void
    {
        $this->assignedMap = [];

        if (! $this->supplierId) {
            return;
        }

        $ids = DB::table('component_supplier')
            ->where('supplier_id', $this->supplierId)
            ->pluck('component_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Trasforma in set: [id => true]
        $this->assignedMap = array_fill_keys($ids, true);
    }

    /**
     * Query componenti per la lista (code/description, filtri).
     * NOTA: mostriamo solo code/description, ma filtriamo usando category_id e is_active.
     */
    private function buildComponentsQuery()
    {
        $q = DB::table('components as c')
            ->select([
                'c.id',
                'c.code',
                'c.description',
                'c.is_active',
                'c.category_id',
            ]);

        // Filtro "attivi"
        if (($this->filters['active'] ?? 'all') === '1') {
            $q->where('c.is_active', true);
        } elseif (($this->filters['active'] ?? 'all') === '0') {
            $q->where('c.is_active', false);
        }

        // Filtro categoria
        if (! empty($this->filters['category_id'])) {
            $q->where('c.category_id', (int) $this->filters['category_id']);
        }

        // Filtro ricerca testo (case-insensitive)
        $term = trim((string) ($this->filters['q'] ?? ''));
        if ($term !== '') {
            $needle = '%'.mb_strtolower($term).'%';
            $q->where(function ($sub) use ($needle) {
                $sub->whereRaw('LOWER(c.code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(c.description) LIKE ?', [$needle]);
            });
        }

        // Solo non assegnati (toggle)
        if (! empty($this->filters['only_unassigned']) && ! empty($this->assignedMap)) {
            $q->whereNotIn('c.id', array_keys($this->assignedMap));
        }

        // Ordinamento
        $q->orderBy('c.code');

        return $q;
    }

    /**
     * Recupera i componenti per la view con “hasMore” calcolato (limit+1).
     */
    private function getComponentsForView(): array
    {
        // Prendiamo limit+1 per capire se esistono ancora risultati oltre il limit
        $rows = $this->buildComponentsQuery()
            ->limit($this->limit + 1)
            ->get();

        $this->hasMore = $rows->count() > $this->limit;

        // Taglia a $limit per la visualizzazione
        $items = $rows->take($this->limit)->values();

        return [$items, $this->hasMore];
    }

    /**
     * Seleziona tutti i componenti che matchano i filtri correnti (versione "pro"),
     * escludendo quelli già assegnati (checkbox disabilitate).
     */
    public function selectAllMatching(): void
    {
        if (! $this->supplierId) {
            $this->uiMessageType = 'error';
            $this->uiMessage = 'Fornitore non valido.';
            return;
        }

        // Recupera TUTTI gli ID che matchano i filtri (senza limit)
        $ids = $this->buildComponentsQuery()
            ->pluck('c.id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // Escludi quelli già assegnati (non selezionabili)
        if (! empty($this->assignedMap)) {
            $ids = array_values(array_filter($ids, fn ($id) => ! isset($this->assignedMap[$id])));
        }

        // Merge unico con selezione esistente
        $this->selected = array_values(array_unique(array_merge(
            array_map('intval', $this->selected),
            $ids
        )));

        $this->uiMessageType = 'info';
        $this->uiMessage = 'Selezionati tutti i componenti filtrati (non assegnati).';
    }

    /**
     * Svuota la selezione (solo memoria UI, non tocca il DB).
     */
    public function clearSelection(): void
    {
        $this->selected = [];
        $this->uiMessage = null;
    }

/**
 * Salva in bulk su component_supplier:
 * - calcola dal DB quanti dei selezionati sono già presenti (conteggio affidabile)
 * - inserisce solo i mancanti con last_cost=0 e lead_time_days=0
 */
public function assignSelected(): void
{
    // ✅ Validazione supplier
    if (! $this->supplierId) {
        $this->uiMessageType = 'error';
        $this->uiMessage = 'Fornitore non valido.';
        return;
    }

    // ✅ Normalizza selezione: ID unici e interi
    $selectedIds = array_values(array_unique(array_map('intval', $this->selected)));

    if (empty($selectedIds)) {
        $this->uiMessageType = 'info';
        $this->uiMessage = 'Nessun componente selezionato.';
        return;
    }

    /**
     * ✅ Conteggio "già presenti" affidabile:
     * leggiamo dal DB quali componenti tra i selezionati esistono già in pivot.
     */
    $existingIds = DB::table('component_supplier')
        ->where('supplier_id', $this->supplierId)
        ->whereIn('component_id', $selectedIds)
        ->pluck('component_id')
        ->map(fn ($v) => (int) $v)
        ->all();

    $alreadyPresent = count($existingIds);

    /**
     * ✅ Inseriamo solo i mancanti (diff).
     */
    $toInsertIds = array_values(array_diff($selectedIds, $existingIds));

    // Se non c'è nulla da inserire, messaggio coerente
    if (empty($toInsertIds)) {
        $this->uiMessageType = 'info';
        $this->uiMessage = "Assegnazione completata: inseriti 0, già presenti {$alreadyPresent}.";
        return;
    }

    // ✅ Prepara righe pivot
    $now = now();
    $rows = array_map(function (int $componentId) use ($now) {
        return [
            'supplier_id'    => $this->supplierId,
            'component_id'   => $componentId,
            'last_cost'      => 0,  // preferiamo 0 a NULL
            'lead_time_days' => 0,  // preferiamo 0 a NULL
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }, $toInsertIds);

    /**
     * ✅ Insert idempotente: se in mezzo qualcuno li inserisce in parallelo,
     * insertOrIgnore li ignora.
     */
    $inserted = (int) DB::table('component_supplier')->insertOrIgnore($rows);

    // Se qualcuno ha inserito in parallelo, questi risultano "ignorati"
    $ignoredOnInsert = count($toInsertIds) - $inserted;

    // Totale già presenti = presenti prima + ignorati per concorrenza
    $alreadyTotal = $alreadyPresent + $ignoredOnInsert;

    // ✅ Refresh mappa assegnati per aggiornare UI (verdi + disabilitate)
    $this->loadAssignedMap();

    // ✅ Dopo assegnazione svuotiamo la selezione
    $this->selected = [];

    $this->uiMessageType = 'success';
    $this->uiMessage = "Assegnazione completata: inseriti {$inserted}, già presenti {$alreadyTotal}.";
}



    /**
     * Render della view Livewire.
     */
    public function render()
    {
        [$components, $hasMore] = $this->getComponentsForView();

        return view('livewire.supplier-bulk-assign-components-modal', [
            'components' => $components,
            'hasMore'    => $hasMore,
        ]);
    }
}
