@php
    $statuts = ['active' => 'Active', 'echue' => 'Échue', 'resiliee' => 'Résiliée'];
    $systemes = ['' => '—', 'lait' => 'Lait', 'viande' => 'Viande', 'mixte' => 'Mixte'];
    $champs = ['raison_sociale' => 'Raison sociale', 'num_cheptel_ede' => 'Numéro EDE', 'siret' => 'SIRET', 'nom_contact' => 'Contact', 'adresse' => 'Adresse',
        'code_postal' => 'Code postal', 'telephone' => 'Téléphone', 'courriel' => 'E-mail', 'num_client_labo' => 'N° client laboratoire'];
@endphp
<div class="space-y-6">
    <p class="text-texte-doux"><a href="{{ route('elevages') }}" class="lien">élevages</a> · {{ $elevage->code_pseudo }} · GDS {{ $elevage->gds_id }}
        @if ($peutExporter) · <a href="{{ route('export.rationnement', $elevage->id) }}" class="lien">export rationnement (CSV)</a> · <a href="{{ route('export.donnees', $elevage->id) }}" class="lien">données de l'élevage (ZIP)</a> @endif</p>
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif

    <section class="carte" aria-labelledby="titre-identite">
        <h2 id="titre-identite" class="text-lg font-semibold">Identité et caractéristiques</h2>
        @error('identite') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        <form wire:submit="enregistrer" class="mt-3 space-y-3">
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach ($champs as $champ => $libelle)
                    <div><label for="id-{{ $champ }}" class="etiquette">{{ $libelle }}</label><input id="id-{{ $champ }}" type="text" wire:model="identite.{{ $champ }}" class="champ w-full">@error('identite.'.$champ) <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                @endforeach
                <div><label for="id-insee" class="etiquette">Commune du siège (code INSEE)</label><input id="id-insee" type="text" wire:model="identite.code_insee_commune" class="champ w-full"></div>
            </div>
            <fieldset><legend class="etiquette">Espèces</legend><div class="flex gap-3 text-sm">@foreach ($especes as $code => $libelle) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $code }}" wire:model="identite.especes"> {{ $libelle }}</label> @endforeach</div></fieldset>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['bovin' => 'Bovins', 'ovin' => 'Ovins', 'caprin' => 'Caprins'] as $e => $l)
                    <div class="flex gap-2">
                        <div><label for="sys-{{ $e }}" class="etiquette">Système {{ mb_strtolower($l) }}</label><select id="sys-{{ $e }}" wire:model="identite.systeme_{{ $e }}" class="champ">@foreach ($systemes as $c => $s) <option value="{{ $c }}">{{ $s }}</option> @endforeach</select></div>
                        <div><label for="eff-{{ $e }}" class="etiquette">Effectif</label><input id="eff-{{ $e }}" type="text" inputmode="numeric" wire:model="identite.effectif_{{ $e }}s" class="champ w-24"></div>
                    </div>
                @endforeach
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="identite.adherent_gds"> Adhérent du GDS</label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="identite.consentement_donnees" class="mt-1"> Information RGPD remise et consentement au traitement des données recueilli @if ($identite['consentement_date']) (le {{ \Illuminate\Support\Carbon::parse($identite['consentement_date'])->format('d/m/Y') }}) @endif</label>
            <button type="submit" class="bouton-primaire">Enregistrer</button>
        </form>
    </section>

    <section class="carte" aria-labelledby="titre-conventions">
        <h2 id="titre-conventions" class="text-lg font-semibold">Conventions</h2>
        @error('convention') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        <ul class="mt-2 space-y-2 text-sm">
            @forelse ($conventions as $c)
                <li wire:key="conv-{{ $c->id }}" class="flex flex-wrap items-end gap-2">
                    <span>{{ $statuts[$c->statut] ?? $c->statut }} : du {{ $c->date_debut->format('d/m/Y') }} au {{ $c->date_fin->format('d/m/Y') }}{{ $c->reference_externe ? ' · '.$c->reference_externe : '' }}</span>
                    @if ($peutCreerConvention && $c->statut !== 'resiliee')
                        <form wire:submit="prolonger({{ $c->id }})" class="flex items-end gap-1"><label for="prol-{{ $c->id }}" class="sr-only">Nouvelle date de fin</label><input id="prol-{{ $c->id }}" type="date" wire:model="prolongation" class="champ"><button type="submit" class="bouton-secondaire">Prolonger</button></form>
                    @endif
                </li>
            @empty
                <li class="text-texte-doux">Aucune convention.</li>
            @endforelse
        </ul>
        @if ($peutCreerConvention && $conventions->where('statut', 'active')->isEmpty())
            <form wire:submit="creerConvention" class="mt-3 flex flex-wrap items-end gap-2">
                <div><label for="cv-debut" class="etiquette">Début</label><input id="cv-debut" type="date" wire:model="convention.date_debut" class="champ"></div>
                <div><label for="cv-fin" class="etiquette">Fin</label><input id="cv-fin" type="date" wire:model="convention.date_fin" class="champ"></div>
                <div><label for="cv-ref" class="etiquette">Référence du contrat</label><input id="cv-ref" type="text" wire:model="convention.reference_externe" class="champ"></div>
                <button type="submit" class="bouton-secondaire">Créer la convention</button>
            </form>
        @endif
    </section>

    <section class="carte" aria-labelledby="titre-comptes">
        <h2 id="titre-comptes" class="text-lg font-semibold">Comptes éleveurs</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse ($comptes as $u)
                <li wire:key="cpt-{{ $u->id }}">{{ $u->nomComplet() }} — {{ $u->email }} · {{ $u->derniere_connexion ? 'dernière connexion le '.$u->derniere_connexion->format('d/m/Y') : 'invitation en attente' }}@unless ($u->actif) · désactivé @endunless</li>
            @empty
                <li class="text-texte-doux">Aucun compte.</li>
            @endforelse
        </ul>
        @error('invitation') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        <form wire:submit="inviter" class="mt-3 flex flex-wrap items-end gap-2">
            <div><label for="inv-mail" class="etiquette">E-mail</label><input id="inv-mail" type="email" wire:model="invitation.email" class="champ">@error('invitation.email') <span class="block text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
            <div><label for="inv-nom" class="etiquette">Nom</label><input id="inv-nom" type="text" wire:model="invitation.nom" class="champ">@error('invitation.nom') <span class="block text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
            <div><label for="inv-prenom" class="etiquette">Prénom</label><input id="inv-prenom" type="text" wire:model="invitation.prenom" class="champ"></div>
            <button type="submit" class="bouton-secondaire">Inviter un éleveur</button>
        </form>
    </section>

    <section class="carte" aria-labelledby="titre-consentements">
        <h2 id="titre-consentements" class="text-lg font-semibold">Consentements</h2>
        <p class="text-sm text-texte-doux">L'éleveur les gère depuis son espace ; chaque changement est journalisé.</p>
        @error('consentement') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        <ul class="mt-2 space-y-1 text-sm">
            @forelse ($consentements as $c)
                <li wire:key="cs-{{ $c->id }}">{{ $c->type === 'references' ? 'Références' : 'Vétérinaire : '.$c->veterinaire?->nomComplet() }} — accordé le {{ $c->accorde_le->format('d/m/Y') }}{{ $c->revoque_le ? ', révoqué le '.$c->revoque_le->format('d/m/Y') : '' }}
                    @if ($peutGererConsentements && ! $c->revoque_le) <button type="button" wire:click="revoquer({{ $c->id }})" wire:confirm="Révoquer ce consentement ?" class="lien text-xs">révoquer</button> @endif</li>
            @empty
                <li class="text-texte-doux">Aucun consentement.</li>
            @endforelse
        </ul>
        @if ($peutGererConsentements && $consentements->where('type', 'references')->whereNull('revoque_le')->isEmpty())
            <button type="button" wire:click="accorderReferences" class="bouton-secondaire mt-2">Enregistrer le consentement « références »</button>
        @endif
    </section>

    @if ($peutAnonymiser)
        <section class="carte" aria-labelledby="titre-anonymisation">
            <h2 id="titre-anonymisation" class="text-lg font-semibold">Anonymisation (demande RGPD)</h2>
            <p class="text-sm text-texte-doux">Irréversible : l'identité et les coordonnées sont effacées, les comptes éleveurs anonymisés et désactivés, la convention résiliée ; les données pseudonymisées restent. Exporter d'abord les données si l'éleveur les demande.</p>
            @error('anonymisation') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="anonymiser" class="mt-2 flex flex-wrap items-end gap-2">
                <div class="grow"><label for="motif-anon" class="etiquette">Demande (date, canal)</label><input id="motif-anon" type="text" wire:model="motifAnonymisation" class="champ w-full"></div>
                <button type="submit" wire:confirm="Anonymiser définitivement cet élevage ?" class="bouton-secondaire">Anonymiser</button>
            </form>
        </section>
    @endif
</div>
