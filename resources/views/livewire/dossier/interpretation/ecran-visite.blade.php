@php
    use App\Livewire\Dossier\Etats;
    use App\Livewire\Dossier\Interpretation\EcranVisite as Ecran;
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
@endphp
<div class="space-y-8">
    <section class="carte" aria-labelledby="titre-visite">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="titre-visite" class="text-lg font-semibold">{{ $libelleElevage }} ({{ $visite->elevage->code_pseudo }})</h2>
                <p class="mt-1 text-sm text-texte-doux">Visite du {{ $visite->date_visite->format('d/m/Y') }} · campagne {{ $visite->campagne_code ?? 'non déterminée' }}
                    @if ($agent) · <a href="{{ route('visites.saisie', $visite->id) }}" class="lien">saisie de la visite</a> @endif</p>
            </div>
            @if ($interpretation)
                @php($statut = Ecran::statut($interpretation->statut))
                <div class="text-right">
                    <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
                    <p class="mt-1 text-xs text-texte-doux">Version {{ $interpretation->version }} · grille v{{ $interpretation->version_grille }}</p>
                </div>
            @endif
        </div>
    </section>

    @if (session('status'))
        <div class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</div>
    @endif
    @error('circuit')
        <div class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- Étape « croiser » : éléments de la visite (agents seulement) --}}
    @if ($elements)
        <section aria-labelledby="titre-croiser" class="space-y-4">
            <h2 id="titre-croiser" class="text-xl font-semibold">4. Croiser : ce que montre la visite</h2>

            <div class="carte">
                <h3 class="font-semibold">Fourrages</h3>
                @forelse ($elements['lots'] as $lot)
                    @php($i = $lot['interpretation'])
                    <p class="mt-2 text-sm">
                        <a href="{{ route('analyses.interpretation', $lot['analyse_id']) }}" class="lien">Bulletin {{ $lot['numero'] }}</a> —
                        @if ($i)
                            @php($niveau = InterpretationBulletin::niveau($i->niveau_global))
                            <x-pastille :etat="$niveau['etat']" :libelle="$niveau['libelle']" /> {{ InterpretationBulletin::statut($i->statut)['libelle'] }}
                        @else
                            <span class="text-texte-doux">pas encore interprété</span>
                        @endif
                    </p>
                @empty
                    <p class="mt-2 text-sm text-texte-doux">Aucun prélèvement rattaché à la visite.</p>
                @endforelse
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="carte">
                    <h3 class="font-semibold">Eau</h3>
                    @forelse ($elements['eau'] as $item)
                        <p class="mt-2 text-sm font-medium">{{ $item['analyse']->point_prelevement }} ({{ $item['analyse']->date_prelevement->format('d/m/Y') }})</p>
                        <ul class="text-sm">
                            @foreach ($item['lignes'] as $l)
                                @php($c = Etats::classe($l['classe'], $l['gravite']))
                                <li>{{ $l['libelle'] }} : {{ $l['censure'] }}{{ Affichage::nombre($l['valeur']) }} {{ $l['unite'] }} — <x-pastille :etat="$c['etat']" :libelle="$c['libelle']" /></li>
                            @endforeach
                        </ul>
                    @empty
                        <p class="mt-2 text-sm text-texte-doux">Aucune analyse d'eau.</p>
                    @endforelse
                </div>

                <div class="carte">
                    <h3 class="font-semibold">Bilan sanitaire</h3>
                    <ul class="mt-2 text-sm">
                        @foreach (array_filter($elements['bilan'], fn ($l) => $l['valeur'] !== null) as $l)
                            @php($e = Etats::bilan($l['classe']))
                            <li>{{ $l['libelle'] }} : {{ Affichage::nombre($l['valeur'], $l['mode'] === 'taux' ? 1 : 0) }} {{ $l['unite'] }} — <x-pastille :etat="$e['etat']" :libelle="$e['libelle']" /></li>
                        @endforeach
                    </ul>
                    @if (array_filter($elements['bilan'], fn ($l) => $l['valeur'] !== null) === []) <p class="mt-2 text-sm text-texte-doux">Aucun indicateur saisi.</p> @endif
                </div>

                <div class="carte">
                    <h3 class="font-semibold">Mesures terrain</h3>
                    @forelse ($elements['mesures'] as $g)
                        <p class="mt-2 text-sm font-medium">{{ $g['libelle'] }}</p>
                        <ul class="text-sm">
                            @foreach ($g['mesures'] as $m)
                                @php($e = Etats::mesure($m['lecture']?->value))
                                <li>{{ $m['libelle'] }} : médiane {{ Affichage::nombre($m['mediane'], $m['decimales']) }} — <x-pastille :etat="$e['etat']" :libelle="$e['libelle']" /></li>
                            @endforeach
                            @foreach ($g['lectures'] as $lecture)
                                <li class="font-medium">Lecture proposée : {{ $lecture['libelle'] }}</li>
                            @endforeach
                        </ul>
                    @empty
                        <p class="mt-2 text-sm text-texte-doux">Aucune mesure.</p>
                    @endforelse
                </div>

                <div class="carte">
                    <h3 class="font-semibold">Profils sanguins</h3>
                    <ul class="mt-2 text-sm">
                        @forelse ($elements['profils'] as $l)
                            @php($e = Etats::profil($l['lecture']))
                            <li>{{ ucfirst($l['profil']->parametre) }} : {{ Affichage::nombre((float) $l['profil']->valeur_moyenne) }} {{ $l['profil']->unite }} — <x-pastille :etat="$e['etat']" :libelle="$e['libelle']" /></li>
                        @empty
                            <li class="text-texte-doux">Aucun profil.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="carte">
                <h3 class="font-semibold">Grille trouble × famille de cause</h3>
                @if ($elements['familles'] === [])
                    <p class="mt-2 text-sm text-texte-doux">Aucun trouble présent dans le bilan sanitaire ni dans les événements des douze derniers mois.</p>
                @else
                    <p class="mt-1 text-sm text-texte-doux">Troubles présents : {{ count($elements['troubles']) }}. Les familles qui reviennent le plus orientent la recherche des causes communes.</p>
                    <ol class="mt-2 list-decimal pl-5 text-sm">
                        @foreach ($elements['familles'] as $f)
                            <li>{{ $familles[$f->familleCause] }} — force {{ $f->force }}</li>
                        @endforeach
                    </ol>
                    <p class="mt-1 text-xs font-medium text-etat-vigilance">{{ Affichage::A_VALIDER }}</p>
                @endif
            </div>
        </section>
    @endif

    {{-- Interprétation --}}
    <section aria-labelledby="titre-interpretation" class="space-y-4">
        <h2 id="titre-interpretation" class="text-xl font-semibold">Synthèse et messages pour l'éleveur</h2>

        @if (! $interpretation)
            <div class="carte">
                @if ($peutCreer)
                    <p class="text-sm text-texte-doux">Préparez le brouillon : le niveau et les messages sont proposés à partir des interprétations des lots et de l'eau.</p>
                    <button type="button" wire:click="creer" class="bouton-principal mt-3">Préparer l'interprétation de visite</button>
                @else
                    <p class="text-sm text-texte-doux">L'interprétation de cette visite n'est pas encore disponible.</p>
                @endif
            </div>
        @else
            @if ($interpretation->refus_visa_motif && $interpretation->statut === 'brouillon')
                <div class="rounded-md border border-etat-vigilance bg-etat-vigilance-fond p-3 text-sm" role="status">Visa refusé le {{ $interpretation->refus_visa_le?->format('d/m/Y') }} : {{ $interpretation->refus_visa_motif }}</div>
            @endif

            <div class="carte space-y-4">
                @php($propose = InterpretationBulletin::niveau($interpretation->niveau_propose))
                <p class="text-sm">Niveau proposé : <x-pastille :etat="$propose['etat']" :libelle="$propose['libelle']" /></p>
                @if ($peutModifier)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="iv-titre" class="etiquette">Titre orienté décision</label>
                            <input id="iv-titre" type="text" wire:model="titre" class="champ w-full" maxlength="300">
                        </div>
                        <div>
                            <label for="iv-niveau" class="etiquette">Niveau retenu</label>
                            <select id="iv-niveau" wire:model="niveauRetenu" class="champ w-full">
                                <option value="">Non fixé</option>
                                @foreach ([0, 1, 2, 3] as $n) <option value="{{ $n }}">{{ InterpretationBulletin::niveau($n)['libelle'] }}</option> @endforeach
                            </select>
                            @error('niveauRetenu') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="iv-synthese" class="etiquette">Synthèse (3 à 5 lignes, dans le langage de l'éleveur)</label>
                        <textarea id="iv-synthese" wire:model="synthese" rows="4" class="champ w-full"></textarea>
                    </div>
                    <div>
                        <label for="iv-limites" class="etiquette">Limites (ce qui n'est pas dosé, ce que la fiche ne remplace pas)</label>
                        <textarea id="iv-limites" wire:model="limites" rows="2" class="champ w-full"></textarea>
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="font-semibold">Messages hiérarchisés (cinq au plus)</legend>
                        @foreach ($messages as $i => $m)
                            <div wire:key="message-{{ $i }}" class="rounded-md border border-filet p-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div>
                                        <label for="msg-gravite-{{ $i }}" class="etiquette">Gravité</label>
                                        <select id="msg-gravite-{{ $i }}" wire:model="messages.{{ $i }}.gravite" class="champ">
                                            @foreach ([0, 1, 2, 3] as $n) <option value="{{ $n }}">{{ $n }}</option> @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="msg-pilier-{{ $i }}" class="etiquette">Pilier</label>
                                        <select id="msg-pilier-{{ $i }}" wire:model="messages.{{ $i }}.pilier" class="champ">
                                            <option value="">—</option>
                                            @foreach ($piliers as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                        </select>
                                    </div>
                                    <button type="button" wire:click="monterMessage({{ $i }})" class="lien text-sm" @disabled($i === 0)>Monter</button>
                                    <button type="button" wire:click="retirerMessage({{ $i }})" class="lien text-sm">Retirer</button>
                                </div>
                                <label for="msg-texte-{{ $i }}" class="etiquette mt-2">Message {{ $i + 1 }}</label>
                                <textarea id="msg-texte-{{ $i }}" wire:model="messages.{{ $i }}.texte" rows="2" class="champ w-full"></textarea>
                                @error("messages.{$i}.texte") <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-3">
                            <button type="button" wire:click="ajouterMessage" class="lien text-sm">Ajouter un message</button>
                            <button type="button" wire:click="reproposer" wire:confirm="Remplacer les messages par ceux que propose l'application ?" class="lien text-sm">Reprendre les messages proposés</button>
                        </div>
                    </fieldset>
                    <button type="button" wire:click="enregistrer" class="bouton-secondaire">Enregistrer</button>
                @else
                    @if ($interpretation->titre) <p class="text-lg font-semibold">{{ $interpretation->titre }}</p> @endif
                    @php($retenu = InterpretationBulletin::niveau($interpretation->niveau_global))
                    <p class="text-sm">Niveau retenu : <x-pastille :etat="$retenu['etat']" :libelle="$retenu['libelle']" /></p>
                    <p class="whitespace-pre-line">{{ $interpretation->synthese }}</p>
                    <ol class="list-decimal space-y-1 pl-5">
                        @foreach ($messagesLus as $m)
                            @php($n = InterpretationBulletin::niveau($m->gravite))
                            <li><x-pastille :etat="$n['etat']" :libelle="'Gravité '.$m->gravite" /> {{ $m->texte }}</li>
                        @endforeach
                    </ol>
                    @if ($interpretation->limites) <p class="text-sm text-texte-doux">Limites : {{ $interpretation->limites }}</p> @endif
                @endif
            </div>

            {{-- Causes communes --}}
            <div class="carte">
                <h3 class="font-semibold">Causes communes</h3>
                @forelse ($causes as $c)
                    <div wire:key="cause-{{ $c->id }}" class="mt-3 rounded-md border border-filet p-3 text-sm">
                        <p class="font-medium">{{ $c->titre }} <span class="text-texte-doux">({{ $familles[$c->famille_cause] ?? $c->famille_cause }})</span></p>
                        @if ($c->preuves) <p class="mt-1"><span class="font-medium">Preuves :</span> {{ $c->preuves }}</p> @endif
                        @if ($c->consequences) <p class="mt-1"><span class="font-medium">Conséquences :</span> {{ $c->consequences }}</p> @endif
                        @if ($peutModifier) <button type="button" wire:click="supprimerCause({{ $c->id }})" class="lien mt-1 text-sm">Retirer</button> @endif
                    </div>
                @empty
                    <p class="mt-2 text-sm text-texte-doux">Aucune cause commune rédigée.</p>
                @endforelse
                @if ($peutModifier)
                    <form wire:submit="ajouterCause" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="cause-famille" class="etiquette">Famille de cause</label>
                            <select id="cause-famille" wire:model="cause.famille" class="champ w-full">
                                <option value="">Choisir…</option>
                                @foreach ($familles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                            </select>
                            @error('cause.famille') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cause-titre" class="etiquette">Intitulé</label>
                            <input id="cause-titre" type="text" wire:model="cause.titre" class="champ w-full" maxlength="300">
                            @error('cause.titre') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cause-preuves" class="etiquette">Preuves (au moins deux sources)</label>
                            <textarea id="cause-preuves" wire:model="cause.preuves" rows="2" class="champ w-full"></textarea>
                        </div>
                        <div>
                            <label for="cause-consequences" class="etiquette">Conséquences</label>
                            <textarea id="cause-consequences" wire:model="cause.consequences" rows="2" class="champ w-full"></textarea>
                        </div>
                        <div class="sm:col-span-2"><button type="submit" class="bouton-secondaire">Ajouter la cause commune</button></div>
                    </form>
                @endif
            </div>
        @endif
    </section>

    @if ($interpretation)
        <section aria-labelledby="titre-agir" class="space-y-4">
            <h2 id="titre-agir" class="text-xl font-semibold">5. Agir et 6. Suivre : plan d'action</h2>
            <livewire:dossier.interpretation.plan-actions :visite-id="$visite->id" :key="'plan-'.$interpretation->id" />
        </section>

        {{-- Circuit (EF-M2-04) --}}
        <section class="carte space-y-3" aria-labelledby="titre-circuit">
            <h2 id="titre-circuit" class="text-lg font-semibold">Validation et publication</h2>
            @if ($obstacles !== [])
                <div class="text-sm">
                    <p class="font-medium">Avant publication :</p>
                    <ul class="list-disc pl-5">@foreach ($obstacles as $o) <li>{{ $o }}</li> @endforeach</ul>
                </div>
            @endif
            <div class="flex flex-wrap gap-3">
                @if ($peutModifier) <button type="button" wire:click="valider" class="bouton-principal">Valider l'interprétation de visite</button> @endif
                @if ($peutViser) <button type="button" wire:click="viser" class="bouton-principal">Viser (niveau 3)</button> @endif
                @if ($peutPublier) <button type="button" wire:click="publier" wire:confirm="Publier la visite à l'éleveur ?" class="bouton-principal">Publier la visite</button> @endif
                @if ($peutNouvelleVersion) <button type="button" wire:click="nouvelleVersion" class="bouton-secondaire">Créer une nouvelle version</button> @endif
            </div>
            @if ($peutViser)
                <form wire:submit="refuserVisa" class="flex flex-wrap items-end gap-2">
                    <div class="grow">
                        <label for="motif-refus" class="etiquette">Motif du refus de visa</label>
                        <input id="motif-refus" type="text" wire:model="motifRefusVisa" class="champ w-full" maxlength="1000">
                        @error('motifRefusVisa') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="bouton-secondaire">Refuser le visa</button>
                </form>
            @endif
            @if ($versions->count() > 1)
                <div class="text-sm">
                    <p class="font-medium">Versions</p>
                    <ul>
                        @foreach ($versions as $v)
                            <li>Version {{ $v->version }} — {{ Ecran::statut($v->statut)['libelle'] }}@if ($v->publie_le) , publiée le {{ $v->publie_le->format('d/m/Y') }} (grille v{{ $v->version_grille }}) @endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif
</div>
