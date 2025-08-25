{{-- resources/views/components/role-create-modal.blade.php --}}

@props([
    'permissionsByModule',
    'moduleLabels',
    'roles',
])

@php
    // ID del permesso "users.create" (Crea nuovi utenti)
    $createUserPermId = (string) $permissionsByModule
        ->flatten()
        ->firstWhere('name', 'users.create')
        ->id;
@endphp

{{-- wrapper del modal, visibile solo se showModal = true nel genitore --}}
<div 
     x-show="showModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center">
     
    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black opacity-50"></div>

    {{-- contenuto --}}
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto max-h-[90vh] w-full max-w-3xl p-6 z-10">
        
        {{-- Header titolo --}}
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                <span x-text="mode === 'create' ? 'Nuovo Ruolo' : 'Modifica Ruolo'"></span>
            </h3>
            <button type="button" @click="showModal = false"
                    class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- FORM Role --}}
        <form x-bind:action="mode === 'create'
                ? '{{ route('roles.store') }}'
                : '{{ url('roles') }}/' + form.id"
              method="POST"
              @submit.prevent="if (validate()) $el.submit()">

            @csrf
            <template x-if="mode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div class="space-y-6">
                {{-- Nome ruolo --}}
                <div>
                    <label for="name"
                           class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Nome Ruolo
                    </label>
                    <input id="name" name="name" x-model="form.name" type="text" required
                           class="mt-1 block w-full px-3 py-2 border rounded-md
                                  bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                    <p x-text="errors.name" class="text-red-600 text-xs mt-1"></p>
                </div>

                {{-- Permessi --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Permessi
                    </label>
                    <div class="grid sm:grid-cols-2 gap-4 max-h-[50vh] overflow-y-auto pr-1">
                        @foreach($permissionsByModule as $moduleKey => $perms)
                            <fieldset class="border rounded p-2">
                                <legend class="text-[0.65rem] uppercase font-semibold px-1">
                                    {{ $moduleLabels[$moduleKey] 
                                       ?? \Illuminate\Support\Str::headline(str_replace('.', ' ', $moduleKey)) }}
                                </legend>

                                @foreach($perms as $perm)
                                    <label class="flex items-center space-x-2 text-xs mt-1">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               :value="'{{ $perm->id }}'"
                                               x-model="form.permissions">
                                        <span class="cursor-help"
                                              title="{{ $perm->description ?? $perm->display_name }}">
                                            {{ $perm->display_name 
                                               ?? Str::headline(Str::afterLast($perm->name, '.')) }}
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach

                    {{-- Checklist ruoli assegnabili, visibile solo se 'Crea Utenti' è spuntato --}}
                    <div  
                        x-show="form.permissions.includes('{{ $createUserPermId }}')">
                        <fieldset class="border rounded p-2">
                            <legend class="text-[0.65rem] uppercase font-semibold px-1">
                                Ruoli assegnabili agli utenti creati
                            </legend>

                            @foreach($roles as $role)
                                <label class="flex items-center space-x-2 text-xs mt-1">
                                    <input type="checkbox"
                                        name="assignable_roles[]"
                                        :value="'{{ $role->name }}'"
                                        x-model="form.assignable_roles">
                                    <span>{{ $role->name }}</span>
                                </label>
                            @endforeach

                        </fieldset>
                    </div>
                    <p x-text="errors.permissions" class="text-red-600 text-xs mt-1"></p>
                    <p x-text="errors.assignable_roles" class="text-red-600 text-xs mt-1"></p>
                </div>
                </div>
            </div>

            {{-- Footer buttons --}}
            <div class="mt-6 flex justify-end space-x-2">
                <button type="button" @click="showModal = false"
                        class="px-4 py-1.5 text-xs font-medium rounded-md
                               bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100
                               hover:bg-gray-300 dark:hover:bg-gray-500">
                    Annulla
                </button>
                <button type="submit"
                        class="px-4 py-1.5 text-xs font-medium rounded-md
                               bg-purple-600 text-white hover:bg-purple-500">
                    <span x-text="mode === 'create' ? 'Salva' : 'Aggiorna'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
