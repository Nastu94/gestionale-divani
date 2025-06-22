<?php

namespace App\Models;

use Spatie\Permission\Models\Role as BaseRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends BaseRole
{
    /**
     * Ruoli che questo ruolo può assegnare.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function assignableRoles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_assignable_roles',
            'role_id',
            'assignable_role_id'
        );
    }
}
