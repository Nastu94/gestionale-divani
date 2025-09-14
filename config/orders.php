<?php

/**
 * Configurazione di contesto per il flusso ordini.
 *
 * NOTA: non modifica logiche esistenti, espone solo parametri:
 * - ruoli autorizzati a modificare ordini OCCASIONALI già confermati
 * - TTL del link di conferma per clienti STANDARD
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Ruoli abilitati a modificare ordini OCCASIONALI confermati
    |--------------------------------------------------------------------------
    |
    | Gli ordini per clienti occasionali vengono confermati automaticamente
    | al salvataggio. In linea generale, dopo la conferma un ordine è "locked".
    | Per gli occasionali vogliamo una deroga controllata: solo alcuni ruoli
    | possono intervenire (il flusso resta quello attuale: verifica→riserva→PO).
    |
    */
    'modifiable_occasional_roles' => [
        'Admin',
        'Supervisor',
    ],

    /*
    |--------------------------------------------------------------------------
    | TTL (giorni) del link di conferma ordine STANDARD
    |--------------------------------------------------------------------------
    |
    | Numero di giorni di validità del token di conferma inviato via email.
    | Richiesto: 14 giorni.
    |
    */
    'confirmation_link_ttl_days' => env('ORDERS_CONFIRMATION_TTL_DAYS', 14),
    'sales_roles'                => ['commerciale'],

    /*
    |--------------------------------------------------------------------------
    | Ruoli abilitati a modificare ordini STANDARD confermati
    |--------------------------------------------------------------------------
    |
    | Come per gli occasionali, anche gli ordini standard (occasional_customer_id = null)
    | possono essere modificati DOPO la conferma, ma solo dagli utenti che hanno uno dei
    | ruoli elencati qui. Il controllo avviene via Policy @can('update', $order).
    |
    */
    'modifiable_standard_roles' => [
        'Admin',
        'Supervisor',
    ],
];
