<x-guest-layout>
    <div class="max-w-xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        
        <div class="bg-white dark:bg-gray-800 shadow-2xl sm:rounded-3xl overflow-hidden border border-gray-100 dark:border-gray-700 transform transition-all">
            
            <div class="px-6 py-12 sm:px-12 sm:py-16 text-center">
                @if($ok ?? false)
                    {{-- Icona Successo --}}
                    <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-8">
                        <i class="fas fa-check text-5xl text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight">
                        Operazione completata!
                    </h2>
                    
                    <p class="text-lg text-gray-600 dark:text-gray-300 font-medium">
                        {{ $message }}
                    </p>
                    
                    <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                        A breve riceverai un'email di riepilogo con il documento in allegato.
                        Puoi chiudere questa pagina.
                    </p>
                @else
                    {{-- Icona Errore / Avviso --}}
                    <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-yellow-100 dark:bg-yellow-900/30 mb-8">
                        <i class="fas fa-exclamation-triangle text-5xl text-yellow-600 dark:text-yellow-400"></i>
                    </div>
                    
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight">
                        Attenzione
                    </h2>
                    
                    <p class="text-lg text-gray-600 dark:text-gray-300 font-medium mb-4">
                        {{ $message }}
                    </p>
                    
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                        @lang('orders.confirm.link_help', ['days' => $ttl_days])
                    </p>
                @endif
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-900 px-6 py-6 sm:px-12 flex justify-center border-t border-gray-200 dark:border-gray-700">
                <button onclick="window.close()" class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 shadow-sm text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                    Chiudi questa finestra
                </button>
            </div>
            
        </div>
        
        <div class="text-center mt-8">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Tutti i diritti riservati.
            </p>
        </div>
        
    </div>
</x-guest-layout>
