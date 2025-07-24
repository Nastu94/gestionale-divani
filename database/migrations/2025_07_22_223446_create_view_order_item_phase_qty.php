<?php
/**
 * Crea la VIEW v_order_item_phase_qty
 *
 * Scopo – per ogni riga d’ordine (order_item) mostra
 *   • phase          → valore enum 0-6
 *   • qty_in_phase   → pezzi attualmente presenti in quella fase
 *
 * Formula:
 *   Σ(quantità entrate nella fase) – Σ(quantità uscite dalla fase)
 *
 * La VIEW è read-only e si aggiorna automaticamente ad
 * ogni INSERT in order_item_phase_events.
 *
 * NB: se un domani servirà performance extra, potrai
 * convertirla in tabella + trigger senza toccare il codice applicativo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @inheritDoc */
    public function up(): void
    {
        // Per sicurezza: rimuove vista pre-esistente se c’è
        DB::statement('DROP VIEW IF EXISTS v_order_item_phase_qty');

        DB::statement(<<<SQL
        /* ⬇︎ definizione view aggiornata */
        CREATE OR REPLACE VIEW v_order_item_phase_qty AS
        SELECT
            oi.id                         AS order_item_id,
            oi.current_phase              AS phase,
            (oi.quantity - oi.qty_completed) AS qty_in_phase
        FROM order_items oi
        WHERE (oi.quantity - oi.qty_completed) > 0 AND oi.product_id IS NOT NULL

        UNION ALL
        SELECT
            m.order_item_id,
            m.phase,
            SUM(m.net_qty)                AS qty_in_phase
        FROM (
                SELECT order_item_id, to_phase   AS phase,  quantity  AS net_qty
                FROM   order_item_phase_events
                UNION ALL
                SELECT order_item_id, from_phase AS phase, -quantity  AS net_qty
                FROM   order_item_phase_events
        ) AS m
        GROUP BY m.order_item_id, m.phase
        HAVING qty_in_phase > 0;
        SQL);
    }

    /** @inheritDoc */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_order_item_phase_qty');
    }
};
