<?php
/**
 * Alias e termini ambigui per il controllo coerenza NOME ↔ MAPPING.
 *
 * Strategia Fase A:
 * - Gli alias vengono aggiunti "a runtime" ai nomi ufficiali presi da DB.
 * - Qui definiamo sinonimi "sicuri" che mappano verso una famiglia colore/fabric.
 * - I termini ambigui (non 1:1) generano INFO (non WARNING).
 *
 * NB: L’associazione degli alias ai singoli ID avviene nel controller costruendo
 *     una mappa per-id a partire dal nome ufficiale (normalizzato).
 */

return [

    // Sinonimi per FABRIC: canonical name (normalizzato) => [alias...]
    'fabric_synonyms_map' => [

        // --- Fibre naturali / miste comuni ---
        'cotone' => [
            'cotton', 'tela di cotone', 'canvas',
        ],
        'lino' => [
            'linen', 'misto lino',
        ],
        'seta' => [
            'silk', 'raso di seta', 'shantung', 'tussah',
        ],
        'lana' => [
            'wool', 'panno di lana', 'feltro', 'loden',
        ],
        'canapa' => [
            'hemp', 'tessuto di canapa',
        ],
        'viscosa' => [
            'rayon',
        ],

        // --- Tessuti “aspetto” / lavorazione ---
        'velluto' => [
            'velour', 'vellutino', 'velluto a coste', 'corduroy', 'vellutato',
        ],
        'boucle' => [
            'bouclee', 'tessuto boucle',
        ],
        'chenille' => [
            'ciniglia',
        ],
        'jacquard' => [
            'jaquard', // refuso comune
        ],
        'tweed' => [
            'harris tweed', 'spinato',
        ],

        // --- Microfibre / sintetici ---
        'microfibra' => [
            'micro fibra', 'micro-fibra', 'microfiber', 'microsuede', 'micro suede',
        ],
        'poliestere' => [
            'polyester', 'poliestere riciclato', 'rpet',
        ],
        'alcantara' => [
            'alcantra', 'ultrasuede', 'microfibra scamosciata',
        ],

        // --- Pelli (vere e similari) ---
        'pelle' => [
            // reali
            'vera pelle', 'pelle naturale', 'pelle pieno fiore', 'pelle fiore',
            'pelle corretta', 'pelle rigenerata', 'cuoio',
            // similari/marketing (li trattiamo come “pelle” per riconoscimento testuale)
            'similpelle', 'simil pelle', 'ecopelle', 'eco pelle',
            'finta pelle', 'pelle sintetica', 'pelle artificiale',
        ],
        'nabuk' => [
            'nubuck', 'nubuk', 'pelle nabuk',
        ],
        'pelle scamosciata' => [
            'scamosciato', 'scamosciata', 'suede', 'pelle suede',
        ],
    ],

    // Sinonimi per COLORI: alias => canonical color name (normalizzati)
    // Esempio: 'antracite' è da trattare come 'grigio'
    'color_synonyms_to_canonical' => [

        // Nero
        'black'           => 'nero',

        // Bianco
        'off white'       => 'bianco',
        'bianco ottico'   => 'bianco',
        'panna'           => 'bianco',
        'avorio'          => 'bianco',
        // (evitiamo "crema" e "ghiaccio" perché spesso ambigui con beige/grigio chiaro)

        // Grigio
        'gray'            => 'grigio',
        'grey'            => 'grigio',
        'antracite'       => 'grigio',
        'grafite'         => 'grigio',
        'piombo'          => 'grigio',
        'slate'           => 'grigio',
        'charcoal'        => 'grigio',
        'smoke'           => 'grigio',
        'silver'          => 'grigio', // metallizzato ma spesso usato come grigio

        // Beige
        'sabbia'          => 'beige',
        'ecru'            => 'beige',
        'sabbia naturale' => 'beige',
        // (evitiamo 'khaki', 'camel', 'greige', 'tortora' perché ambigui)

        // Marrone
        'cioccolato'      => 'marrone',
        'testa di moro'   => 'marrone',
        'tabacco'         => 'marrone',
        'cognac'          => 'marrone',

        // Rosso
        'red'             => 'rosso',
        'bordeaux'        => 'rosso',
        'granata'         => 'rosso',
        'amaranto'        => 'rosso',
        'scarlatto'       => 'rosso',
        'crimson'         => 'rosso',

        // Arancione
        'arancio'         => 'arancione',
        'zucca'           => 'arancione',
        'mandarino'       => 'arancione',

        // Giallo
        'giallo limone'   => 'giallo',
        'zafferano'       => 'giallo',
        'senape'          => 'giallo',
        'ocra'            => 'giallo',

        // Verde
        'bottiglia'       => 'verde',
        'smeraldo'        => 'verde',
        'oliva'           => 'verde',
        'salvia'          => 'verde',
        'menta'           => 'verde',
        'foresta'         => 'verde',
        'militare'        => 'verde',

        // Blu
        'navy'            => 'blu',
        'blu notte'       => 'blu',
        'blu royal'       => 'blu',
        'bluette'         => 'blu',
        'cobalto'         => 'blu',

        // Azzurro
        'celeste'         => 'azzurro',
        'azzurro polvere' => 'azzurro',
        'cielo'           => 'azzurro',

        // Viola
        'porpora'         => 'viola',
        'lilla'           => 'viola',
        'lavanda'         => 'viola',
        'melanzana'       => 'viola',
        'prugna'          => 'viola',

        // Rosa
        'pink'            => 'rosa',
        'fucsia'          => 'rosa',
        'magenta'         => 'rosa',
    ],

    // Termini ambigui (non mappati 1:1) → INFO (mai WARNING)
    'ambiguous_colors' => [
        // Neutri / “greige”
        'tortora', 'greige', 'taupe', 'perla', 'ghiaccio', 'nebbia', 'fumo',

        // Beige/giallo chiari e sabbia (spesso tra bianco, beige e giallo)
        'crema', 'champagne', 'paglia',

        // Rosati / aranciati ambigui
        'cipria', 'corallo', 'pesca', 'salmone',

        // Rossi/bruni ambigui
        'terracotta', 'mattone', 'rame', 'bronzo', 'vinaccia',

        // Verde/blu ambigui
        'ottanio', 'turchese', 'teal', 'petrolio', 'acquamarina', 'laguna', 'pavone',

        // Azzurri/violetti ambigui
        'malva', 'carta da zucchero', 'polvere',
    ],
];
