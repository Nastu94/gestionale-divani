{{-- resources/views/pages/warehouse/partials/stock-levels-static-table.blade.php --}}

<table class="min-w-full text-sm divide-y divide-gray-300">
    <thead class="bg-gray-200">
        <tr>
            <th class="px-3 py-1">Codice</th>
            <th class="px-3 py-1">Descrizione</th>
            <th class="px-3 py-1">UM</th>
            <th class="px-3 py-1 text-right">Totale</th>
            <th class="px-3 py-1 text-right">Riservato</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($levels as $row)
            <tr>
                <td class="px-3 py-0.5">{{ $row->component_code }}</td>
                <td class="px-3 py-0.5">{{ $row->component_description }}</td>
                <td class="px-3 py-0.5">{{ $row->uom }}</td>
                <td class="px-3 py-0.5 text-right">{{ $row->quantity }}</td>
                <td class="px-3 py-0.5 text-right">{{ $row->reserved_quantity }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
