<?php
/**
 * (re)Create VIEW  v_order_item_phase_qty
 *
 * • Per ogni riga d’ordine (order_items) e per ciascuna fase (0-6)
 *   restituisce il saldo attuale dei pezzi presenti in quella fase (qty_in_phase > 0).
 * • Calcolo:
 *     – fase 0  → quantità iniziale dell’order_item
 *     – fasi 1-6→ Σ(entrate) – Σ(uscite) dagli eventi
 *
 * Filtro: consideriamo soltanto gli order_items che rappresentano prodotti
 *         concreti di produzione  →  order_items.product_id IS NOT NULL
 *
 * La vista è read-only; se un domani servissero performance extra
 * potrà essere sostituita da una tabella materializzata + trigger.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /* elimina eventuale versione precedente */
        DB::statement('DROP VIEW IF EXISTS v_order_item_phase_qty');

        /* definizione aggiornata */
        DB::statement(<<<SQL
CREATE VIEW v_order_item_phase_qty AS
WITH
base_qty AS (
    SELECT  id        AS order_item_id,
            0         AS phase,
            quantity  AS qty
    FROM    order_items
    WHERE   product_id IS NOT NULL
),
movements AS (
      SELECT order_item_id, to_phase   AS phase,  quantity          AS delta
      FROM   order_item_phase_events
      UNION ALL
      SELECT order_item_id, from_phase AS phase, -quantity          AS delta
      FROM   order_item_phase_events
),
events_net AS (
    SELECT  order_item_id,
            phase,
            SUM(delta) AS qty
    FROM    movements
    GROUP BY order_item_id, phase
),
full_balance AS (
    SELECT * FROM base_qty
    UNION ALL
    SELECT * FROM events_net
)
SELECT  fb.order_item_id,
        fb.phase,
        SUM(fb.qty) AS qty_in_phase
FROM    full_balance fb
JOIN    order_items oi ON oi.id = fb.order_item_id
WHERE   oi.product_id IS NOT NULL
GROUP BY fb.order_item_id, fb.phase
HAVING  SUM(fb.qty) > 0;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_order_item_phase_qty');
    }
};
