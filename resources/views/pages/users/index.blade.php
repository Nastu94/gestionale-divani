<x-app-layout>
    <!-- Header -->
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Utenti') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    <div class="py-6" x-data="{ showCreateModal: false }"
        @close-modal.window="showCreateModal = false"
        @keydown.escape.window="showCreateModal = false"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 text-gray-900 dark:text-gray-100">
                    <!-- Pulsante Nuovo -->
                    <div class="flex justify-end mb-4">
                        <button @click="showCreateModal = true" class="inline-flex items-center px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase tracking-wider hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                            <i class="fas fa-plus mr-1"></i>
                            Nuovo
                        </button>
                    </div>

                    <!-- Modal Creazione Utente -->
                    <!-- Wrapper modale con overlay -->
                    <div x-show="showCreateModal" x-cloak
                        @click.away=" showCreateModal = false"
                        @close-modal.window=" showCreateModal = false"
                        class="fixed inset-0 z-50 flex items-center justify-center"
                    >
                        <!-- Overlay semitrasparente -->
                        <div class="absolute inset-0 bg-black opacity-75"></div>
                        <!-- Contenuto modale, interazioni abilitate -->
                        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden w-full max-w-md p-6 z-10">
                            <x-user-create-modal :roles="$roles" @close-modal.window="showCreateModal = false" />
                        </div>
                    </div>

                    <!-- Tabella Utenti con riga espandibile -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr class="text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    <th class="px-6 py-2 text-left">#</th>
                                    <th class="px-6 py-2 text-left">Nome</th>
                                    <th class="px-6 py-2 text-left">Email</th>
                                    <th class="px-6 py-2 text-left">Ruoli</th>
                                </tr>
                            </thead>
                            <!-- Aggiunto x-data al tbody per gestire l'apertura con openId -->
                            <tbody class="bg-white dark:bg-gray-800" x-data="{ openId: null }">
                                @foreach ($users as $user)
                                    <!-- Riga utente -->
                                    <tr @click="openId = (openId === {{ $user->id }} ? null : {{ $user->id }})"
                                        :class="openId === {{ $user->id }} ? 'bg-gray-50 dark:bg-gray-700' : ''"
                                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-2 whitespace-nowrap">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                                        <td class="px-6 py-2 whitespace-nowrap">{{ $user->name }}</td>
                                        <td class="px-6 py-2 whitespace-nowrap">{{ $user->email }}</td>
                                        <td class="px-6 py-2 whitespace-nowrap">{{ $user->roles->pluck('name')->join(', ') }}</td>
                                    </tr>
                                    <!-- Riga CRUD espandibile -->
                                    <tr x-show="openId === {{ $user->id }}" x-cloak>
                                        <td colspan="4" class="px-6 py-2 bg-gray-50 dark:bg-gray-700">
                                            <div class="flex space-x-4 text-xs">
                                                <a href="#" class="flex items-center hover:text-blue-600">
                                                    <i class="fas fa-eye mr-1"></i> Visualizza
                                                </a>
                                                <a href="{{ route('users.edit', $user) }}" class="flex items-center hover:text-yellow-600">
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </a>
                                                @can('users.delete', $user)
                                                    <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questo utente?');" class="flex items-center">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="flex items-center hover:text-red-600">
                                                            <i class="fas fa-trash-alt mr-1"></i> Elimina
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    <div class="mt-4">{{ $users->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>