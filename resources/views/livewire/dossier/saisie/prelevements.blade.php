<div class="carte">
    <h3 class="font-semibold">Fourrages prélevés</h3>
    <p class="mt-1 text-sm text-texte-doux">Lots de l'élevage non rattachés à une autre visite. Le bulletin de chaque lot s'interprète depuis son écran.</p>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
    @if ($lots->isEmpty())
        <p class="mt-3 text-sm text-texte-doux">Aucun prélèvement disponible : importez les bulletins depuis l'écran d'import.</p>
    @else
        <form wire:submit="rattacher" class="mt-3">
            <fieldset>
                <legend class="sr-only">Prélèvements rattachés à la visite</legend>
                <ul class="divide-y divide-filet text-sm">
                    @foreach ($lots as $lot)
                        <li wire:key="lot-{{ $lot->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" value="{{ $lot->id }}" wire:model="rattaches" @disabled(! $modifiable)>
                                <span>{{ $lot->type ?? 'Type à compléter' }}@if ($lot->nom_lot) — {{ $lot->nom_lot }} @endif · prélevé le {{ \Illuminate\Support\Carbon::parse($lot->date_prelevement)->format('d/m/Y') }}</span>
                            </label>
                            @if ($lot->analyse_id)
                                <a href="{{ route('analyses.interpretation', $lot->analyse_id) }}" class="lien">Bulletin {{ $lot->num_echantillon_labo }} ({{ $lot->statut_analyse }})</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </fieldset>
            @if ($modifiable) <button type="submit" class="bouton-secondaire mt-3">Enregistrer le rattachement</button> @endif
        </form>
    @endif
</div>
