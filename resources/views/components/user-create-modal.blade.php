@props(['roles'])

<!-- Contenuto del modale vero e proprio -->
<div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuovo Utente</h3>
        <!-- Pulsante chiudi -->
        <button @click="$dispatch('close-modal')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label for="password" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Password</label>
                <input type="password" name="password" id="password" required class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Conferma Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label for="role" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Ruolo</label>
                <select name="role" id="role" required class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                    <option value="">Seleziona ruolo</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-2">
            <!-- Pulsante Annulla -->
            <button type="button" @click="$dispatch('close-modal')" class="px-4 py-1.5 text-xs font-medium rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-500">Annulla</button>
            <!-- Pulsante Salva -->
            <button type="submit" class="px-4 py-1.5 text-xs font-medium rounded-md bg-purple-600 text-white hover:bg-purple-500">Salva</button>
        </div>
    </form>
</div>