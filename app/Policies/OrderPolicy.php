<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * UPDATE: ora consente la modifica ANCHE per gli STANDARD confermati,
     * ma solo a ruoli autorizzati (config('orders.modifiable_standard_roles')).
     *
     * Regole complessive:
     * - status = 1 (confermato)
     *   - se OCCASIONALE (occasional_customer_id != null)  → ruoli in modifiable_occasional_roles
     *   - se STANDARD    (occasional_customer_id == null)  → ruoli in modifiable_standard_roles
     * - status = 0 (non confermato) → lasciamo true (eventuali altre ACL restano a carico tuo)
     */
    public function update(User $user, Order $order): bool
    {
        if ((int) $order->status === 1) {
            $isOccasional = ! is_null($order->occasional_customer_id);

            if ($isOccasional) {
                // confermato + OCCASIONALE → ruoli speciali occasionali
                return $this->userHasModifiableOccasionalRole($user);
            }

            // confermato + STANDARD → ruoli speciali standard
            return $this->userHasModifiableStandardRole($user);
        }

        // Non confermato → nessun vincolo aggiuntivo qui.
        return true;
    }

    /**
     * Già presente nello step 2: mantiene la compatibilità.
     * Ora delega al helper generico case-insensitive.
     */
    protected function userHasModifiableOccasionalRole(User $user): bool
    {
        $roles = (array) config('orders.modifiable_occasional_roles', []);
        return $this->userHasAnyRoleCaseInsensitive($user, $roles);
    }

    /**
     * NUOVO: ruoli per STANDARD confermati.
     */
    protected function userHasModifiableStandardRole(User $user): bool
    {
        $roles = (array) config('orders.modifiable_standard_roles', []);
        return $this->userHasAnyRoleCaseInsensitive($user, $roles);
    }

    /**
     * Helper GENERICO: verifica se l'utente ha uno qualunque dei ruoli indicati,
     * con confronto case-insensitive e compatibilità con/ senza spatie/permission.
     */
    protected function userHasAnyRoleCaseInsensitive(User $user, array $allowed): bool
    {
        $allowed = array_map('mb_strtolower', $allowed);

        // Spatie: tentativo diretto
        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole($allowed)) {
                return true;
            }
            // Retry case-insensitive leggendo i role names
            $userRoles = collect($user->getRoleNames() ?? [])->map(fn ($r) => mb_strtolower($r))->all();
            return (bool) array_intersect($allowed, $userRoles);
        }

        // Fallback relazione roles()->pluck('name')
        if (method_exists($user, 'roles')) {
            $userRoles = $user->roles()->pluck('name')->map(fn ($r) => mb_strtolower($r))->all();
            return (bool) array_intersect($allowed, $userRoles);
        }

        // Fallback legacy proprietà singola
        if (property_exists($user, 'role') && is_string($user->role)) {
            return in_array(mb_strtolower($user->role), $allowed, true);
        }

        return false;
    }
}
