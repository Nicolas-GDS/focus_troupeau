@php($statuts = ['active' => 'Convention active', 'echue' => 'Convention échue', 'resiliee' => 'Convention résiliée'])
<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="carte lg:col-span-2" aria-labelledby="titre-elevages">
            <h2 id="titre-elevages" class="text-lg font-semibold">Élevages</h2>
            <div class="mt-2"><label for="rech" class="etiquette">Rechercher (code, raison sociale, EDE)</label><input id="rech" type="search" wire:model.live.debounce.300ms="recherche" class="champ"></div>
            <ul class="mt-3 divide-y divide-filet text-sm">
                @forelse ($elevages as $e)
                    @php($c = $conventions[$e->id] ?? null)
                    <li wire:key="el-{{ $e->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('elevages.fiche', $e->id) }}" class="lien">{{ $identites[$e->id]->raison_sociale ?? $e->code_pseudo }}<span class="block text-xs text-texte-doux">{{ $e->code_pseudo }}{{ ($identites[$e->id]->num_cheptel_ede ?? null) ? ' · EDE '.$identites[$e->id]->num_cheptel_ede : '' }} · GDS {{ $e->gds_id }}</span></a>
                        @if ($c) <x-pastille :etat="$c->statut === 'active' ? 'couvert' : 'information'" :libelle="($statuts[$c->statut] ?? $c->statut).' — '.\Illuminate\Support\Carbon::parse($c->date_fin)->format('d/m/Y')" />
                        @else <span class="text-xs text-texte-doux">Sans convention</span> @endif
                    </li>
                @empty
                    <li class="py-2 text-texte-doux">Aucun élevage.</li>
                @endforelse
            </ul>
            <div class="mt-2">{{ $elevages->links() }}</div>
        </section>

        <section class="carte" aria-labelledby="titre-nouvel-elevage">
            <h2 id="titre-nouvel-elevage" class="text-lg font-semibold">Nouvel élevage</h2>
            <p class="text-sm text-texte-doux">Code pseudonyme attribué automatiquement ; la convention et les comptes se créent ensuite depuis la fiche. Un élevage adhérent peut aussi venir de l'import SAGEMA.</p>
            @error('nouveau') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="creer" class="mt-3 space-y-2">
                @if ($gds->count() > 1)
                    <div><label for="ne-gds" class="etiquette">GDS</label><select id="ne-gds" wire:model="nouveau.gds_id" class="champ w-full">@foreach ($gds as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach</select></div>
                @endif
                <div><label for="ne-rs" class="etiquette">Raison sociale</label><input id="ne-rs" type="text" wire:model="nouveau.raison_sociale" class="champ w-full">@error('nouveau.raison_sociale') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div>
                    <label for="ne-ede" class="etiquette">Numéro EDE</label>
                    <div class="flex gap-2">
                        <input id="ne-ede" type="text" wire:model="nouveau.num_cheptel_ede" class="champ w-full" placeholder="FR12345678" aria-describedby="ne-ede-aide">
                        @if ($preremplissageAgds)
                            <button type="button" wire:click="preremplirAgds" wire:loading.attr="disabled" wire:target="preremplirAgds" class="bouton-secondaire whitespace-nowrap">Pré-remplir depuis AGDS</button>
                        @endif
                    </div>
                    @if ($preremplissageAgds)
                        <p id="ne-ede-aide" class="mt-1 text-xs text-texte-doux">Saisissez le numéro EDE puis pré-remplissez : raison sociale, code postal, téléphone et e-mail sont repris d'AGDS (cheptel cherché dans le département du GDS choisi).</p>
                    @endif
                    @error('nouveau.num_cheptel_ede') <p class="mt-1 text-xs text-etat-critique" role="alert">{{ $message }}</p> @enderror
                    @if ($messageAgds) <p class="mt-1 text-xs" role="status">{{ $messageAgds }}</p> @endif
                </div>
                <div><label for="ne-contact" class="etiquette">Contact</label><input id="ne-contact" type="text" wire:model="nouveau.nom_contact" class="champ w-full"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label for="ne-cp" class="etiquette">Code postal</label><input id="ne-cp" type="text" inputmode="numeric" wire:model="nouveau.code_postal" class="champ w-full">@error('nouveau.code_postal') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                    <div><label for="ne-tel" class="etiquette">Téléphone</label><input id="ne-tel" type="tel" wire:model="nouveau.telephone" class="champ w-full"></div>
                </div>
                <div><label for="ne-mail" class="etiquette">E-mail</label><input id="ne-mail" type="email" wire:model="nouveau.courriel" class="champ w-full">@error('nouveau.courriel') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <fieldset><legend class="etiquette">Espèces</legend><div class="flex gap-3 text-sm">@foreach ($especes as $code => $libelle) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $code }}" wire:model="nouveau.especes"> {{ $libelle }}</label> @endforeach</div></fieldset>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="nouveau.consentement_donnees" class="mt-1"> L'éleveur a reçu l'information RGPD et consent au traitement de ses données (condition de création des comptes).</label>
                <button type="submit" class="bouton-primaire">Créer l'élevage</button>
            </form>
        </section>
    </div>
</div>
