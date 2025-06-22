<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determina se $user può assegnare i ruoli $requestedRoles
     * all'utente $targetUser.
     *
     * @param  \App\Models\User  $user            Utente loggato
     * @param  \App\Models\User  $targetUser      Utente da creare o aggiornare
     * @param  string[]          $requestedRoles  Array di nomi di ruolo richiesti
     * @return bool
     */
    public function updateRoles(User $user, User $targetUser, array $requestedRoles): bool
    {
        // Prendo i ruoli del current user
        $myRoles = $user->roles;

        // Da ciascun ruolo ricavo i ruoli “figli” che può assegnare
        $allowed = $myRoles
            ->flatMap->assignableRoles()  // relazione definita nel model Role
            ->pluck('name')               // ottengo solo i nomi
            ->unique()
            ->toArray();

        // Se tutti i ruoli richiesti sono in $allowed, ritorno true
        return [] === array_diff($requestedRoles, $allowed);
    }

    /**
     * Determina se $user può modificare $targetUser
     * sulla base dei suoi ruoli correnti.
     */
    public function update(User $user, User $targetUser): bool
    {
        // 1) Eager-load dei ruoli del corrente utente + assignableRoles
        $rolesWithKids = $user
            ->roles()
            ->with('assignableRoles')
            ->get();

        // 2) Costruisco la lista di nomi che può assegnare
        $allowed = $rolesWithKids
            ->flatMap(fn($r) => $r->assignableRoles)
            ->pluck('name')
            ->unique()
            ->toArray();

        // 3) Prendo i nomi dei ruoli già assegnati al target
        $targetRoles = $targetUser
            ->roles
            ->pluck('name')
            ->toArray();

        // 4) True se targetRoles è sottoinsieme di allowed
        return [] === array_diff($targetRoles, $allowed);
    }

    /**
     * Determina se $user può eliminare $targetUser
     * sulla stessa logica di update.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // identica logica a update
        $rolesWithKids = $user
            ->roles()
            ->with('assignableRoles')
            ->get();

        $allowed = $rolesWithKids
            ->flatMap(fn($r) => $r->assignableRoles)
            ->pluck('name')
            ->unique()
            ->toArray();

        $targetRoles = $targetUser
            ->roles
            ->pluck('name')
            ->toArray();

        return [] === array_diff($targetRoles, $allowed);
    }

    /**
     * (Opzionale) Blocca sempre l'accesso ad azioni globali
     * come delete o viewAny se non sei admin.
     */
    public function before(User $user, string $ability)
    {
        if ($user->hasRole('Admin')) {
            return true; // Admin può fare tutto
        }
    }
}
