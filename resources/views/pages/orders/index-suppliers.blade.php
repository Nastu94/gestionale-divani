{{-- resources/views/pages/orders/index-suppliers.blade.php --}}

<x-app-layout>
    {{-- ========== HEADER ========== --}}
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Ordini Fornitore') }}
        </h2>
    </x-slot>

    {{-- ========== MAIN ========== --}}
    <div class="py-6">
        <div
            x-data="{
                openId: null,
                
                openCreate() {
                    window.dispatchEvent(new CustomEvent('open-supplier-order-modal'))
                },
                openEdit(id) {
                    window.dispatchEvent(
                        new CustomEvent('open-supplier-order-modal', { detail: { orderId: id } })
                    )
                }
            }"
            class="max-w-full mx-auto sm:px-6 lg:px-8"
        >
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Toolbar --}}
                <div class="flex justify-end m-2 p-2">
                    @can('orders.supplier.create')
                        <button
                            @click="openCreate"
                            class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase
                                   hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition"
                        >
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    @endcan
                </div>

                {{-- ====== TAB ELLA ====== --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>
                                <x-th-menu 
                                    field="id"            
                                    label="Ordine #"      
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="orders.supplier.index" 
                                    align="left" 
                                />
                                <x-th-menu 
                                    field="supplier"      
                                    label="Fornitore"      
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="orders.supplier.index"
                                />
                                <x-th-menu 
                                    field="ordered_at"    
                                    label="Data Ordine"    
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="orders.supplier.index" 
                                    :filterable="false"
                                />
                                <x-th-menu 
                                    field="delivery_date" 
                                    label="Data Consegna"  
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="orders.supplier.index" 
                                    :filterable="false"
                                />
                                <x-th-menu 
                                    field="total"        
                                    label="Valore (€)"     
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="orders.supplier.index" 
                                    :filterable="false"
                                    align="left" 
                                />
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($orders as $order)
                                @php
                                    $canEdit   = auth()->user()->can('orders.supplier.edit');
                                    $canDelete = auth()->user()->can('orders.supplier.delete');
                                    $canCrud   = $canEdit || $canDelete;
                                @endphp

                                {{-- ───────── RIGA PRINCIPALE ───────── --}}
                                <tr
                                    @if($canCrud)
                                        @click="openId = (openId === {{ $order->id }} ? null : {{ $order->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $order->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 text-right">{{ $order->id }}</td>
                                    <td class="px-6 py-2">{{ $order->supplier?->name }}</td>
                                    <td class="px-6 py-2">{{ $order->ordered_at?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-2">{{ $order->delivery_date?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-2 text-right">{{ number_format($order->total, 2, ',', '.') }}</td>
                                </tr>

                                {{-- ───────── RIGA ESPANSA CRUD ───────── --}}
                                @if($canCrud)
                                <tr x-show="openId === {{ $order->id }}" x-cloak>
                                    <td :colspan="5" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                        <div class="flex items-center space-x-4 text-xs">

                                            {{-- Modifica --}}
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click.stop="openEdit({{ $order->id }})"
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            {{-- Elimina --}}
                                            @if($canDelete)
                                                <form
                                                    action="{{ route('orders.supplier.destroy', $order) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Confermi di voler eliminare l\'ordine #{{ $order->id }}?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center hover:text-red-600"
                                                    >
                                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
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

                {{-- Paginazione --}}
                <div class="mt-4 px-6 py-2">
                    {{ $orders->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>

        {{-- Modale per la creazione/modifica ordine fornitore --}}
        <x-supplier-order-create-modal />
    </div>
</x-app-layout>
