<x-layouts.espace titre="Référentiels versionnés">
    @php($composant = \App\Livewire\Admin\Referentiels::COMPOSANTS[$table] ?? null)
    @if ($composant === 'admin.table-referentiel-ecran')
        @livewire($composant, ['table' => $table])
    @elseif ($composant !== null)
        @livewire($composant)
    @else
        <livewire:admin.referentiels :table="$table" />
    @endif
</x-layouts.espace>
