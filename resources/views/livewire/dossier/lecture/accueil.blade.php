@php
    use App\Livewire\Dossier\Interpretation\PlanActions;
    use App\Livewire\Interpretation\InterpretationBulletin;
    $jalons = PlanActions::jalons();
@endphp
<div class="space-y-6">
    @if (! $elevage)
        <p class="carte text-texte-doux">Aucun élevage n'est encore rattaché à votre compte. Contactez votre technicien GDS.</p>
    @else
        <p class="text-slate-700">Élevage : <span class="font-medium">{{ $libelleElevage }}</span></p>

        {{-- Dernière visite publiée et ses messages (EF-M1-01) --}}
        <section class="carte" aria-labelledby="titre-visite">
            <h2 id="titre-visite" class="text-lg font-semibold">Votre dernière visite</h2>
            @if ($accueil['visite'])
                @php($niveau = InterpretationBulletin::niveau($accueil['interpretation']->niveau_global))
                <p class="mt-1 text-sm text-texte-doux">
                    Visite du {{ $accueil['visite']->date_visite->format('d/m/Y') }}@if ($accueil['technicien']) avec {{ $accueil['technicien'] }}@endif
                    · publiée le {{ $accueil['interpretation']->publie_le?->format('d/m/Y') }}
                </p>
                <p class="mt-3 font-medium">{{ $accueil['interpretation']->titre ?: 'Votre visite d\'approche globale' }}</p>
                <p class="mt-1"><x-pastille :etat="$niveau['etat']" :libelle="$niveau['libelle']" /></p>
                @if ($accueil['messages']->isNotEmpty())
                    <ol class="mt-3 space-y-2" aria-label="Messages classés par gravité">
                        @foreach ($accueil['messages'] as $message)
                            <x-message-hierarchise :message="$message" wire:key="message-{{ $message->id }}" />
                        @endforeach
                    </ol>
                @endif
                <p class="mt-4 flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('dossier.visite', [$elevage->id, $accueil['visite']->id]) }}" class="lien">Lire la synthèse de la visite</a>
                    @if ($lienFiche) <a href="{{ $lienFiche }}" class="lien">Télécharger la fiche (PDF)</a> @endif
                </p>
            @else
                <p class="mt-2 text-texte-doux">Aucune visite publiée pour l'instant : votre technicien vous préviendra par e-mail dès que la fiche sera prête.</p>
            @endif
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Actions qui incombent au lecteur (EF-M1-01, EF-M2-06) --}}
            <section class="carte" aria-labelledby="titre-actions">
                <h2 id="titre-actions" class="text-lg font-semibold">Vos actions à faire</h2>
                @if ($accueil['actions']->isEmpty())
                    <p class="mt-2 text-sm text-texte-doux">Aucune action en attente.</p>
                @else
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($accueil['actions'] as $action)
                            <li wire:key="action-{{ $action->id }}">
                                <span class="font-medium">{{ $action->titre }}</span>
                                <span class="block text-texte-doux">Priorité {{ $action->priorite }} ·
                                    {{ collect([$action->quand, $action->jalon ? ($jalons[$action->jalon] ?? $action->jalon) : null, $action->echeance ? 'avant le '.$action->echeance->format('d/m/Y') : null])->filter()->implode(' · ') ?: 'échéance non précisée' }}</span>
                                @if ($action->echeance?->isPast()) <x-pastille etat="vigilance" libelle="Échéance dépassée" /> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-sm"><a href="{{ route('dossier.plan', $elevage->id) }}" class="lien">Voir le plan d'action</a></p>
            </section>

            {{-- Contrôles à venir (EF-M1-01) --}}
            <section class="carte" aria-labelledby="titre-controles">
                <h2 id="titre-controles" class="text-lg font-semibold">Contrôles à venir</h2>
                @if ($accueil['controles']->isEmpty())
                    <p class="mt-2 text-sm text-texte-doux">Aucun contrôle prévu.</p>
                @else
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($accueil['controles'] as $controle)
                            <li wire:key="controle-{{ $controle->id }}">
                                {{ $controle->mesure_code ? $controle->mesure_code.' — ' : '' }}cible : {{ $controle->cible }}
                                <span class="block text-texte-doux">avant le {{ $controle->echeance->format('d/m/Y') }}</span>
                                @if ($controle->echeance->isPast()) <x-pastille etat="vigilance" libelle="Échu" /> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Autonomie fourragère : dernier bilan mémorisé (EF-M6-05) --}}
        @if ($autonomieVisible)
            <section class="carte" aria-labelledby="titre-autonomie">
                <h2 id="titre-autonomie" class="text-lg font-semibold">Autonomie fourragère</h2>
                @if ($bilan === null)
                    <p class="mt-2 text-sm text-texte-doux">Aucun bilan pour l'inventaire en cours. <a href="{{ route('stocks', $elevage->id) }}" class="lien">Saisir les stocks</a></p>
                @else
                    <dl class="mt-2 grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-texte-doux">Autonomie</dt><dd class="text-xl font-semibold">{{ $bilan->autonomie_jours }} jours</dd></div>
                        <div><dt class="text-texte-doux">Rupture prévue</dt><dd class="text-xl font-semibold">{{ $bilan->date_rupture?->format('d/m/Y') ?? 'aucune sur la période' }}</dd>
                            @if ($bilan->date_rupture) <dd class="text-xs text-texte-doux">entre le {{ $bilan->date_rupture_min?->format('d/m/Y') ?? '—' }} et le {{ $bilan->date_rupture_max?->format('d/m/Y') ?? 'la fin de période' }}</dd> @endif</div>
                        <div><dt class="text-texte-doux">Couverture des besoins</dt><dd class="text-xl font-semibold">{{ \App\Services\Dossier\Affichage::nombre(round(100 * (float) $bilan->couverture), 0) }} %</dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-texte-doux">Bilan calculé le {{ $bilan->calcule_le->timezone(config('app.timezone'))->format('d/m/Y à H:i') }} sur l'inventaire en cours, sécurité déduite (RC-11, RC-12). <a href="{{ route('autonomie', $elevage->id) }}" class="lien">Voir le bilan et la projection</a></p>
                @endif
            </section>
        @endif

        <x-limites :limites="$limites" :version="$accueil['interpretation']?->version_grille" />
    @endif
</div>
