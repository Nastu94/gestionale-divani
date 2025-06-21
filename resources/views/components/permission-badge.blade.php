{{-- resources/views/components/permission-badge.blade.php --}}
{{-- Mostra una sola lettera “semaforo” che indica se il ruolo possiede il permesso.
 --  $granted  bool   → true = verde, false = grigio sbarrato
 --  $label    string → view | create | update | delete                        --}}
@props(['granted' => false, 'action' => '', 'map' => []])

@php
    [$letter, $title] = $map[$action]     // da $actionMap del controller
        ?? [strtoupper(substr($action,0,1)), ucfirst($action)];
@endphp

<span
    class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold
           {{ $granted
              ? 'bg-emerald-600 text-white'
              : 'bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400 line-through' }}"
    title="{{ $title }}"
    aria-label="{{ $title }}"
>
    {{ $letter }}
</span>