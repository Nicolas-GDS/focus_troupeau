<div class="space-y-6">
    <section class="carte">
        <h2 class="text-lg font-semibold">{{ $libelleElevage }} ({{ $elevage->code_pseudo }})@if ($ede) · EDE {{ $ede }}@endif</h2>
        <p class="mt-1 text-sm text-texte-doux">Visite du {{ $visite->date_visite->format('d/m/Y') }} · note envoyée le {{ $note->envoyee_le?->format('d/m/Y') }} par {{ $technicien }}</p>
        @if ($lienPdf) <p class="mt-2"><a href="{{ $lienPdf }}" class="lien">Télécharger la note en PDF</a></p> @endif
    </section>
    @if (session('status')) <div class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</div> @endif
    <section class="carte">
        <h3 class="font-semibold">Animaux concernés</h3>
        <p class="mt-1">{{ $note->lot_animaux ?? 'Non précisés' }}</p>
        <h3 class="mt-4 font-semibold">Question posée</h3>
        <p class="mt-1 whitespace-pre-line">{{ $note->question }}</p>
        <h3 class="mt-4 font-semibold">Ce que montrent les fourrages</h3>
        <ul class="mt-1 list-disc pl-5 text-sm">
            @forelse ($alertes as $l) <li>{{ $l['bulletin'] }} — {{ $l['alerte']->message }}</li> @empty <li>Aucune alerte de gravité 2 ou 3.</li> @endforelse
        </ul>
        <h3 class="mt-4 font-semibold">Retour attendu</h3>
        <p class="mt-1 text-sm">{{ $retourAttendu }}</p>
    </section>
    <section class="carte">
        <h3 class="font-semibold">Votre retour</h3>
        @if ($note->retour)
            <p class="mt-1 whitespace-pre-line">{{ $note->retour }}</p>
            <p class="mt-1 text-xs text-texte-doux">Enregistré le {{ $note->retour_le?->format('d/m/Y') }}</p>
        @elseif ($peutRepondre)
            <form wire:submit="repondre" class="mt-2 space-y-2">
                <label for="retour" class="etiquette">Résultats, prescription éventuelle, date de point</label>
                <textarea id="retour" wire:model="retour" rows="5" class="champ w-full"></textarea>
                @error('retour') <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
                <button type="submit" class="bouton-principal">Envoyer mon retour</button>
            </form>
        @else
            <p class="mt-1 text-sm text-texte-doux">Aucun retour pour l'instant.</p>
        @endif
    </section>
</div>
