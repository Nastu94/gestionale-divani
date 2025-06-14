<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'alerts'.
 *
 * Gestisce notifiche e avvisi generici legati al magazzino.
 */
class Alert extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'type',       // Tipo avviso
        'message',    // Testo avviso
        'payload',    // Dati aggiuntivi (JSON)
        'is_read',    // Stato lettura
        'triggered_at'// Data creazione
    ];

    /**
     * Cast degli attributi.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
        'triggered_at' => 'datetime',
    ];
}
