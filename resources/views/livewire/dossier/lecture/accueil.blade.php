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

        {{-- Autonomie fourragère : lot L6 (EF-M6-05) --}}
        <x-ecran-a-venir ecran="E02, autonomie" lot="L6">Jours d'autonomie restants, date de rupture prévisionnelle et couverture des besoins.</x-ecran-a-venir>

        <x-limites :limites="$limites" :version="$accueil['interpretation']?->version_grille" />
    @endif
</div>
