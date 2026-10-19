<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="carte" aria-labelledby="titre-comptes">
            <h2 id="titre-comptes" class="text-lg font-semibold">Comptes</h2>
            <div class="mt-2 flex flex-wrap gap-2">
                <div><label for="recherche" class="etiquette">Rechercher (e-mail ou nom)</label><input id="recherche" type="search" wire:model.live.debounce.300ms="recherche" class="champ"></div>
                <div><label for="filtre-role" class="etiquette">Rôle</label>
                    <select id="filtre-role" wire:model.live="role" class="champ"><option value="">Tous</option>@foreach ($libellesRoles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach</select></div>
            </div>
            <ul class="mt-3 divide-y divide-filet text-sm">
                @forelse ($comptes as $c)
                    <li wire:key="compte-{{ $c->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <button type="button" wire:click="choisir({{ $c->id }})" class="lien text-left">{{ $c->nomComplet() }}<span class="block text-xs text-texte-doux">{{ $c->email }}</span></button>
                        <span class="text-xs">{{ $c->roles->map(fn ($r) => $libellesRoles[$r->name] ?? $r->name)->join(', ') }}
                            @unless ($c->actif) <x-pastille etat="information" libelle="Désactivé" /> @endunless</span>
                    </li>
                @empty
                    <li class="py-2 text-texte-doux">Aucun compte.</li>
                @endforelse
            </ul>
            <div class="mt-2">{{ $comptes->links() }}</div>
        </section>

        <div class="space-y-6">
            @if ($choisi)
                <section class="carte" aria-labelledby="titre-compte">
                    <h2 id="titre-compte" class="text-lg font-semibold">{{ $choisi->nomComplet() }}</h2>
                    <p class="text-sm text-texte-doux">{{ $choisi->email }} · {{ $choisi->entra_oid ? 'connexion Entra ID' : 'connexion par mot de passe ou lien' }} · dernière connexion : {{ $choisi->derniere_connexion?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'jamais' }}</p>
                    @error('edition') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                    <form wire:submit="enregistrer" class="mt-3 space-y-3">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <div><label for="ed-nom" class="etiquette">Nom</label><input id="ed-nom" type="text" wire:model="edition.nom" class="champ w-full">@error('edition.nom') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                            <div><label for="ed-prenom" class="etiquette">Prénom</label><input id="ed-prenom" type="text" wire:model="edition.prenom" class="champ w-full"></div>
                            <div><label for="ed-tel" class="etiquette">Téléphone</label><input id="ed-tel" type="tel" wire:model="edition.telephone" class="champ w-full"></div>
                        </div>
                        <fieldset><legend class="etiquette">Rôles</legend>
                            <div class="flex flex-wrap gap-3 text-sm">@foreach ($rolesAttribuables as $r) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $r }}" wire:model="edition.roles"> {{ $libellesRoles[$r] }}</label> @endforeach</div></fieldset>
                        <fieldset><legend class="etiquette">GDS de rattachement</legend>
                            <div class="flex flex-wrap gap-3 text-sm">@foreach ($gdsAttribuables as $id => $nom) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $id }}" wire:model="edition.gds"> {{ $nom }}</label> @endforeach</div></fieldset>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="bouton-primaire">Enregistrer</button>
                            @if ($choisi->actif)
                                <button type="button" wire:click="activer(false)" wire:confirm="Désactiver ce compte ? Il ne pourra plus se connecter." class="bouton-secondaire">Désactiver</button>
                                @unless ($choisi->entra_oid) <button type="button" wire:click="inviter" class="bouton-secondaire">Renvoyer l'invitation</button> @endunless
                            @else
                                <button type="button" wire:click="activer(true)" class="bouton-secondaire">Réactiver</button>
                            @endif
                        </div>
                    </form>

                    <h3 class="mt-4 font-semibold">Élevages rattachés</h3>
                    <ul class="mt-1 space-y-1 text-sm">
                        @forelse ($rattachements as $r)
                            <li wire:key="ratt-{{ $r->id }}">{{ $r->code_pseudo }} ({{ $r->role === 'eleveur' ? 'éleveur' : 'vétérinaire, par consentement' }})
                                @if ($r->role === 'eleveur') <button type="button" wire:click="detacher('{{ $r->id }}')" class="lien text-xs">retirer</button> @endif</li>
                        @empty
                            <li class="text-texte-doux">Aucun.</li>
                        @endforelse
                    </ul>
                    <form wire:submit="rattacher" class="mt-2 flex flex-wrap items-end gap-2">
                        <div><label for="ratt" class="etiquette">Rattacher comme éleveur (code de l'élevage)</label><input id="ratt" type="text" wire:model="elevageARattacher" class="champ"></div>
                        <button type="submit" class="bouton-secondaire">Rattacher</button>
                    </form>
                    @error('rattachement') <p class="mt-1 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                </section>
            @endif

            <section class="carte" aria-labelledby="titre-nouveau">
                <h2 id="titre-nouveau" class="text-lg font-semibold">Nouveau compte</h2>
                <p class="text-sm text-texte-doux">Un éleveur ou un vétérinaire d'élevage reçoit une invitation par e-mail ; un agent se connecte par Entra ID.</p>
                @error('nouveau') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                <form wire:submit="creer" class="mt-3 space-y-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div><label for="n-email" class="etiquette">E-mail</label><input id="n-email" type="email" wire:model="nouveau.email" class="champ w-full">@error('nouveau.email') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                        <div><label for="n-tel" class="etiquette">Téléphone</label><input id="n-tel" type="tel" wire:model="nouveau.telephone" class="champ w-full"></div>
                        <div><label for="n-nom" class="etiquette">Nom</label><input id="n-nom" type="text" wire:model="nouveau.nom" class="champ w-full">@error('nouveau.nom') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                        <div><label for="n-prenom" class="etiquette">Prénom</label><input id="n-prenom" type="text" wire:model="nouveau.prenom" class="champ w-full"></div>
                    </div>
                    <fieldset><legend class="etiquette">Rôles</legend>
                        <div class="flex flex-wrap gap-3 text-sm">@foreach ($rolesAttribuables as $r) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $r }}" wire:model="nouveau.roles"> {{ $libellesRoles[$r] }}</label> @endforeach</div>
                        @error('nouveau.roles') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</fieldset>
                    <fieldset><legend class="etiquette">GDS de rattachement (agents)</legend>
                        <div class="flex flex-wrap gap-3 text-sm">@foreach ($gdsAttribuables as $id => $nom) <label class="flex items-center gap-1"><input type="checkbox" value="{{ $id }}" wire:model="nouveau.gds"> {{ $nom }}</label> @endforeach</div></fieldset>
                    <button type="submit" class="bouton-primaire">Créer le compte</button>
                </form>
            </section>
        </div>
    </div>
</div>
