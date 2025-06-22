<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Polices mappati.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
    ];

    /**
     * Registra le policy e i Gate dell’applicazione.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Gate: assignRoles
         *
         * Controlla che l'utente possa assegnare tutti i ruoli richiesti.
         *
         * @param  \App\Models\User  $user
         * @param  string[]          $requestedRoles
         * @return bool
         */
        Gate::define('assignRoles', function (User $user, $requestedRoles): bool {
            $requested = is_array($requestedRoles)
                ? $requestedRoles
                : [(string)$requestedRoles];

            // eager-load dei ruoli con i figli
            $rolesWithKids = $user
                ->roles()
                ->with('assignableRoles')
                ->get();

            // estraggo con callback: da ogni Role prendo la collection assignableRoles
            $allowed = $rolesWithKids
                ->flatMap(function(\App\Models\Role $r) {
                    return $r->assignableRoles;
                })
                ->pluck('name')
                ->unique()
                ->toArray();

            return [] === array_diff($requested, $allowed);
        });
    }
}

