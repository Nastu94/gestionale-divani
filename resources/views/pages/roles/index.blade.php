{{-- resources/views/pages/roles/index.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Ruoli') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>
    
    <div class="py-6">
        <div x-data="roleCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">                

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-4 p-4">
                    <button @click="openCreate"
                            class="inline-flex items-center px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase
                                hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                        <i class="fas fa-plus mr-1"></i> Nuovo
                    </button>
                </div>

                {{-- Modale Create / Edit --}}
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showModal = false"></div>
                    <div class="relative z-10 w-full max-w-3xl">
                        {{-- passa i permessi raggruppati e le label al componente --}}
                        <x-role-create-modal :permissionsByModule="$permissionsByModule"
                                            :moduleLabels="$moduleLabels"
                                            :roles="$roles" />
                    </div>
                </div>

                <div class="p-6 overflow-x-auto">

                    {{-- === TABELLA === --}}
                    <table x-data="{ openId: null }"
                        class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-300 dark:bg-gray-900">
                            <tr class="uppercase tracking-wider">
                                <th class="px-4 py-2 text-left">Ruolo</th>
                                @foreach($permissionsByModule as $moduleKey => $perms)
                                    <th class="px-4 py-2 text-center whitespace-nowrap">
                                        {{ $moduleLabels[$moduleKey] ?? Str::headline(str_replace('.', ' ', $moduleKey)) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($roles as $role)
                                {{-- ---------- RIGA PRINCIPALE ------------ --}}
                                <tr @click="openId = openId === {{ $role->id }} ? null : {{ $role->id }}"
                                    :class="openId === {{ $role->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700">

                                    <td class="px-4 py-2 font-medium">
                                        {{ $role->name }}
                                    </td>

                                    @foreach($permissionsByModule as $moduleKey => $perms)
                                        @php
                                            // azioni realmente presenti in QUESTO modulo
                                            $actions = $perms->map(fn($p) => Str::afterLast($p->name, '.'))
                                                             ->unique()
                                                             ->values();

                                            // permessi posseduti
                                            $rolePerms = $role->permissions->pluck('name');
                                        @endphp

                                        <td class="px-4 py-2">
                                            <div class="grid grid-cols-2 gap-1 justify-items-center">
                                                @foreach($actions as $action)
                                                    <x-permission-badge
                                                        :granted="$rolePerms->contains($moduleKey.'.'.$action)"
                                                        :action="$action"
                                                        :map="$actionMap"
                                                    />
                                                @endforeach
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>

                                @can('roles.manage')
                                    {{-- ---------- RIGA CRUD (accordion) -------- --}}
                                    <tr x-show="openId === {{ $role->id }}" x-cloak>
                                        {{-- 1 (colonna Ruolo) + N moduli  --}}
                                        <td colspan="{{ 1 + $permissionsByModule->count() }}"
                                            class="px-6 py-2 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex space-x-4 text-xs">
                                                {{-- Modifica --}}
                                                @php
                                                    // pacchetto di dati che passeremo a Alpine
                                                    $rolePayload = [
                                                        'id'          => $role->id,
                                                        'name'        => $role->name,
                                                        'permissions' => $role->permissions->pluck('id'),
                                                        'assignable_roles' => $role->assignableRoles->pluck('name'),
                                                    ];
                                                @endphp

                                                <a href="#"
                                                @click.stop="openEdit(@js($rolePayload))"
                                                class="hover:text-yellow-600">
                                                    <i class="fas fa-pencil-alt mr-1"></i>Modifica
                                                </a>

                                                {{-- Elimina (protetto da conferma) --}}
                                                <form action="{{ route('roles.destroy', $role) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questo ruolo?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i>Elimina
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endcan
                            @endforeach
                        </tbody>
                    </table>

                    {{-- === LEGENDA DINAMICA === --}}
                    <div class="mt-4 text-xs text-gray-600 dark:text-gray-300 flex flex-wrap gap-x-4">
                        @foreach($actionMap as [$letter, $label])
                            <span><span class="font-bold">{{ $letter }}</span> = {{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<script>
    function roleCrud () {
        return {
            showModal: false,
            mode: 'create',     // 'create' | 'edit'
            form: { id:null, name:'', permissions:[], assignable_roles:[] },
            errors: {},
            /* Apri modale “Nuovo” */
            openCreate () {
                this.mode  = 'create';
                this.form = { id:null, name:'', permissions:[], assignable_roles:[] };
                this.errors = {};
                this.showModal = true;
            },
            /* Apri modale “Modifica” (chiamata dalla riga CRUD) */
            openEdit(role) {
            this.mode = 'edit';
            this.form = {
                id: role.id,
                name: role.name,
                permissions: role.permissions.map(String),
                assignable_roles: (role.assignable_roles || []).map(String)
            };
            this.errors = {};
            this.showModal = true;
            },
            /* Validazione minimale lato client  */
            validate () {
                this.errors = {};
                if (! this.form.name.trim())  this.errors.name = 'Obbligatorio';
                if (this.form.permissions.length === 0)
                    this.errors.permissions = 'Seleziona almeno un permesso';
                return Object.keys(this.errors).length === 0;
            }
        }
    }
</script>