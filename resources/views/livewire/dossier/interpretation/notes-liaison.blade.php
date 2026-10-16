<div class="carte space-y-3">
    <h3 class="font-semibold">Note de liaison vétérinaire</h3>
    @if (session('note')) <p class="text-sm text-etat-couvert" role="status">{{ session('note') }}</p> @endif
    @error('note') <p class="text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @foreach ($notes as $n)
        <div wire:key="note-{{ $n->id }}" class="rounded-md border border-filet p-3 text-sm">
            <p class="font-medium">Note n° {{ $n->id }} · {{ $n->statut === 'retour_recu' ? 'Retour reçu le '.$n->retour_le?->format('d/m/Y') : ($n->statut === 'envoyee' ? 'Envoyée le '.$n->envoyee_le?->format('d/m/Y') : 'Brouillon') }}
                @if ($n->pdf) · <a href="{{ $lienPdf($n) }}" class="lien">PDF</a> @endif</p>
            <p class="mt-1"><span class="font-medium">Question :</span> {{ $n->question }}</p>
            @if ($n->retour) <p class="mt-1"><span class="font-medium">Retour du vétérinaire :</span> {{ $n->retour }}</p> @endif
        </div>
    @endforeach

    @if ($peutEnvoyer)
        @if ($veterinaires->isEmpty())
            <p class="text-sm text-texte-doux">Aucun vétérinaire n'est désigné par un consentement actif de l'éleveur : la note ne peut pas être envoyée.</p>
        @else
            <form wire:submit="envoyer" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="note-veto" class="etiquette">Vétérinaire désigné</label>
                    <select id="note-veto" wire:model="veterinaire" class="champ w-full">
                        <option value="">Choisir…</option>
                        @foreach ($veterinaires as $v) <option value="{{ $v->id }}">{{ $v->nomComplet() }}</option> @endforeach
                    </select>
                    @error('veterinaire') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="note-lot" class="etiquette">Espèce et animaux concernés</label>
                    <input id="note-lot" type="text" wire:model="lotAnimaux" class="champ w-full" placeholder="5 vaches en préparation au vêlage" maxlength="300">
                </div>
                <div class="sm:col-span-2">
                    <label for="note-question" class="etiquette">Question posée</label>
                    <textarea id="note-question" wire:model="question" rows="3" class="champ w-full" placeholder="Un profil métabolique sur 5 vaches est-il indiqué ?"></textarea>
                    @error('question') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2"><button type="submit" class="bouton-secondaire">Générer et envoyer la note</button></div>
            </form>
        @endif
    @elseif ($notes->isEmpty())
        <p class="text-sm text-texte-doux">Une note de liaison se rédige pour une interprétation de visite validée de niveau 2 ou 3.</p>
    @endif
</div>
