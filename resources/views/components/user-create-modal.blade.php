{{-- resource/views/components/user-create-modal.blade.php --}}

@props(['roles'])

<!-- Modale Create/Edit Utente -->
<div @click.away="showModal = false" class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-xl p-6 z-10">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo Utente' : 'Modifica Utente'"></span>
        </h3>
        <button type="button" @click="showModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <form 
        x-bind:action="mode === 'create' ? '{{ route('users.store') }}' : '{{ url('users') }}/' + form.id" 
        method="POST" @submit.prevent="if(validate()) $el.submit()"
    >
        @csrf
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>
        <div class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input id="name" name="name" x-model="form.name" type="text" required
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                <p x-text="errors.name" class="text-red-600 text-xs mt-1"></p>
            </div>
            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input id="email" name="email" x-model="form.email" type="email" required
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                <p x-text="errors.email" class="text-red-600 text-xs mt-1"></p>
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Password</label>
                <input id="password" name="password" x-model="form.password" :required="mode === 'create'" type="password"
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                <p x-text="errors.password" class="text-red-600 text-xs mt-1"></p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Conferma Password</label>
                <input id="password_confirmation" name="password_confirmation" x-model="form.password_confirmation" :required="mode === 'create'" type="password"
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
                <p x-text="errors.password_confirmation" class="text-red-600 text-xs mt-1"></p>
            </div>
            <div>
                <label for="role" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Ruolo</label>
                <select id="role" name="role" x-model="form.role" required
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                    <option value="">Seleziona ruolo</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <p x-text="errors.role" class="text-red-600 text-xs mt-1"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-2">
            <button type="button" @click="showModal = false"
                    class="px-4 py-1.5 text-xs font-medium rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-500">Annulla</button>
            <button type="submit" class="px-4 py-1.5 text-xs font-medium rounded-md bg-purple-600 text-white hover:bg-purple-500">
                <span x-text="mode === 'create' ? 'Salva' : 'Aggiorna'"></span>
            </button>
        </div>
    </form>
</div>