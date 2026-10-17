<x-layouts.espace titre="Référentiels versionnés">
    @if (isset(\App\Livewire\Admin\Referentiels::COMPOSANTS[$table]))
        @livewire(\App\Livewire\Admin\Referentiels::COMPOSANTS[$table])
    @else
        <livewire:admin.referentiels :table="$table" />
    @endif
</x-layouts.espace>
