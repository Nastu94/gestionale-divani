{{-- resources/views/pages/users/index.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Utenti') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    <div class="py-6" 
         x-data="{
             showModal: false,
             mode: 'create',
             form: { id: null, name: '', email: '', password: '', password_confirmation: '', role: '' },
             errors: {},
             openCreate() {
                 this.mode = 'create';
                 this.form = { id: null, name: '', email: '', password: '', password_confirmation: '', role: '' };
                 this.errors = {};
                 this.showModal = true;
             },
             openEdit(user) {
                 this.mode = 'edit';
                 this.form = {...user, password: '', password_confirmation: ''};
                 this.errors = {};
                 this.showModal = true;
             },
             validate() {
                 this.errors = {};
                 if (!this.form.name.trim()) this.errors.name = 'Il nome è obbligatorio.';
                 if (!/^[^@]+@[^@]+\.[^@]+$/.test(this.form.email)) this.errors.email = 'Formato email non valido.';
                 if (this.mode === 'create' || this.form.password) {
                     if (this.form.password.length < 8) this.errors.password = 'Minimo 8 caratteri.';
                     if (!/[A-Z]/.test(this.form.password)) this.errors.password = 'Serve almeno una maiuscola.';
                     if (!/[0-9]/.test(this.form.password)) this.errors.password = 'Serve almeno un numero.';
                     if (!/[!@#$%^&*(),.?:{}|<>]/.test(this.form.password)) this.errors.password = 'Serve almeno un carattere speciale.';
                     if (this.form.password !== this.form.password_confirmation) this.errors.password_confirmation = 'Le password non corrispondono.';
                 }
                 if (!this.form.role) this.errors.role = 'Seleziona un ruolo.';
                 return Object.keys(this.errors).length === 0;
             }
         }" @close-modal.window="showModal=false"
         @keydown.escape.window="showModal=false">

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-end p-4">
                <button @click="openCreate()" class="inline-flex items-center px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                    <i class="fas fa-plus mr-1"></i> Nuovo
                </button>
            </div>

            <!-- Modale Create/Edit -->
            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black opacity-75"></div>
                <div class="relative z-10 w-full max-w-xl">
                    <x-user-create-modal :roles="$roles" />
                </div>
            </div>

            <!-- Tabella utenti -->
            <div class="overflow-x-auto p-4">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-300 dark:bg-gray-700">
                        <tr class="uppercase tracking-wider">
                            <th class="px-6 py-2 text-left">#</th>
                            <th class="px-6 py-2 text-left">Nome</th>
                            <th class="px-6 py-2 text-left">Email</th>
                            <th class="px-6 py-2 text-left">Ruoli</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800" x-data="{ openId: null }">
                        @foreach($users as $user)
                            @php
                                // Controlli Spatie + Policy per update e delete
                                $canEdit   = auth()->user()->can('users.update') && auth()->user()->can('update', $user);
                                $canDelete = auth()->user()->can('users.delete') && auth()->user()->can('delete', $user);
                                // Espandibilità se almeno uno dei due è true
                                $canCrud   = $canEdit || $canDelete;
                            @endphp

                            {{-- riga principale --}}
                            <tr
                                @if($canCrud)
                                    @click="openId = openId === {{ $user->id }} ? null : {{ $user->id }}"
                                    class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                @endif
                                :class="openId === {{ $user->id }} && {{ $canCrud ? 'true' : 'false' }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                            >
                                <td class="px-6 py-2">{{ $loop->iteration + ($users->currentPage()-1)*$users->perPage() }}</td>
                                <td class="px-6 py-2">{{ $user->name }}</td>
                                <td class="px-6 py-2">{{ $user->email }}</td>
                                <td class="px-6 py-2">{{ $user->roles->pluck('name')->join(', ') }}</td>
                            </tr>

                            {{-- riga “CRUD” --}}
                            @if($canCrud)
                                <tr x-show="openId === {{ $user->id }}" x-cloak>
                                    <td colspan="4" class="px-6 py-2 bg-gray-200 dark:bg-gray-700">
                                        <div class="flex space-x-4 text-xs">
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click="openEdit({
                                                    id: {{ $user->id }},
                                                    name: '{{ addslashes($user->name) }}',
                                                    email: '{{ $user->email }}',
                                                    role: '{{ $user->roles->pluck('name')->first() }}'
                                                    })"
                                                    class="hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i>Modifica
                                                </button>
                                            @endif

                                            @if($canDelete)
                                                <form
                                                    action="{{ route('users.destroy', $user) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?');"
                                                >
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i>Elimina
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>