<?php

namespace App\Services\Dossier;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\NoteLiaison;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\EvenementSanitaire;
use App\Services\ElevageCourant;
use App\Services\Interpretation\InterpretationService;
use App\Services\JournalService;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TextesTypes;
use App\Services\Pdf\RenduPdf;
use App\Services\Pdf\RessourcesCharte;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Note de liaison vétérinaire (EF-M2-09 ; gabarit du document 04, § 4.1) : générée depuis une interprétation de
 * visite de niveau retenu 2 ou 3, adressée à un vétérinaire désigné par un consentement actif de l'éleveur (§ 2.5),
 * archivée en PDF, envoyée par e-mail avec un lien vers l'espace du vétérinaire, qui y saisit son retour ; le
 * technicien est prévenu du retour.
 *
 * Blocs : objet (élevage avec son EDE, espèce et lot d'animaux, date, technicien) ; ce qui a été observé (troubles
 * déclarés, bilan sanitaire dégradé) ; ce que montrent les fourrages (alertes de gravité 2 et 3 avec valeur, seuil
 * et source, éléments non dosés) ; question posée ; retour attendu.
 */
class NoteLiaisonService
{
    public const STATUTS_INTERPRETATION = [InterpretationVisite::STATUT_VALIDE, InterpretationVisite::STATUT_VISEE, InterpretationVisite::STATUT_PUBLIE];

    public function __construct(
        private readonly InterpretationService $interpretations,
        private readonly BilanSanitaireService $bilan,
        private readonly Referentiels $referentiels,
        private readonly NotificationService $notifications,
        private readonly TextesTypes $textes,
        private readonly RenduPdf $rendu,
        private readonly JournalService $journal,
    ) {}

    /**
     * Vétérinaires désignés par un consentement actif de l'élevage (§ 2.5).
     *
     * @return Collection<int, User>
     */
    public function veterinairesConsentis(string $elevageId): Collection
    {
        return User::query()->where('actif', true)
            ->whereIn('id', DB::table('app.consentement')->where('elevage_id', $elevageId)->where('type', 'veterinaire')
                ->whereNull('revoque_le')->select('veterinaire_user_id'))
            ->orderBy('nom')->get();
    }

    /** Une note se rédige pour une interprétation de visite validée, visée ou publiée, de niveau retenu 2 ou 3. */
    public static function eligible(InterpretationVisite $interpretation): bool
    {
        return ($interpretation->niveau_global ?? 0) >= 2 && in_array($interpretation->statut, self::STATUTS_INTERPRETATION, true);
    }

    public function envoyer(InterpretationVisite $interpretation, User $veterinaire, string $lotAnimaux, string $question, User $technicien): NoteLiaison
    {
        $visite = Visite::query()->withoutGlobalScopes()->findOrFail($interpretation->visite_id);

        if (! self::eligible($interpretation)) {
            throw new SaisieRefusee('Une note de liaison se rédige pour une interprétation de visite validée de niveau 2 ou 3.');
        }

        if (! $this->veterinairesConsentis($visite->elevage_id)->contains('id', $veterinaire->id)) {
            throw new SaisieRefusee('Ce vétérinaire n\'est pas désigné par un consentement actif de l\'éleveur.');
        }

        if (trim($question) === '') {
            throw new SaisieRefusee('La question posée au vétérinaire est obligatoire.');
        }

        return DB::transaction(function () use ($interpretation, $veterinaire, $lotAnimaux, $question, $technicien, $visite) {
            $note = NoteLiaison::query()->create([
                'elevage_id' => $visite->elevage_id,
                'interpretation_visite_id' => $interpretation->id,
                'veterinaire_user_id' => $veterinaire->id,
                'lot_animaux' => trim($lotAnimaux) === '' ? null : trim($lotAnimaux),
                'question' => trim($question),
            ]);

            $chemin = config('fiches.dossier').'/'.$this->elevage($visite->elevage_id)->gds_id.'/'.$visite->elevage_id.'/note-liaison-'.$note->id.'.pdf';
            $html = view('pdf.note-liaison', [...$this->donnees($note), 'ressources' => RessourcesCharte::toutes()])->render();
            Storage::disk((string) config('fiches.disque'))->put($chemin, $this->rendu->pdf($html, $this->piedDePage($note)));
            $note->update(['pdf' => $chemin, 'statut' => NoteLiaison::STATUT_ENVOYEE, 'envoyee_le' => now()]);

            $variables = ['elevage' => ElevageCourant::libelle($this->elevage($visite->elevage_id)), 'date' => $visite->date_visite->format('d/m/Y')];
            $this->notifications->envoyer($veterinaire, NotificationService::EMAIL, 'note_liaison',
                $this->textes->texte('notification.note_liaison.objet', $variables),
                array_map(fn (int $n) => $this->textes->texte("notification.note_liaison.ligne{$n}", $variables), [1, 2, 3]),
                route('notes-liaison.voir', $note->id), $visite->elevage_id, 'dossier.note_liaison:'.$note->id);

            $this->journal->ecrire('envoi_note_liaison', $technicien->id, 'dossier.note_liaison:'.$note->id,
                ['interpretation_visite_id' => $interpretation->id, 'veterinaire_user_id' => $veterinaire->id], $visite->elevage_id);

            return $note->refresh();
        });
    }

    public function repondre(NoteLiaison $note, string $retour, User $veterinaire): NoteLiaison
    {
        if ($note->statut !== NoteLiaison::STATUT_ENVOYEE || $note->veterinaire_user_id !== $veterinaire->id) {
            throw new SaisieRefusee('Seul le vétérinaire destinataire répond à une note envoyée.');
        }

        if (trim($retour) === '') {
            throw new SaisieRefusee('Le retour est vide.');
        }

        return DB::transaction(function () use ($note, $retour, $veterinaire) {
            $note->update(['retour' => trim($retour), 'retour_le' => now(), 'statut' => NoteLiaison::STATUT_RETOUR_RECU]);
            $technicien = $note->cree_par === null ? null : User::query()->find($note->cree_par);
            $visite = Visite::query()->withoutGlobalScopes()->find(InterpretationVisite::query()->withoutGlobalScopes()
                ->whereKey($note->interpretation_visite_id)->value('visite_id'));

            if ($technicien !== null && $visite !== null) {
                $variables = ['elevage' => ElevageCourant::libelle($this->elevage($note->elevage_id)), 'date' => $note->envoyee_le?->format('d/m/Y') ?? ''];
                $this->notifications->envoyer($technicien, NotificationService::EMAIL, 'note_liaison_retour',
                    $this->textes->texte('notification.note_liaison_retour.objet', $variables),
                    array_map(fn (int $n) => $this->textes->texte("notification.note_liaison_retour.ligne{$n}", $variables), [1, 2, 3]),
                    route('visites.interpretation', $visite->id), $note->elevage_id, 'dossier.note_liaison:'.$note->id);
            }

            $this->journal->ecrire('retour_note_liaison', $veterinaire->id, 'dossier.note_liaison:'.$note->id, [], $note->elevage_id);

            return $note->refresh();
        });
    }

    /**
     * Contenu de la note, bloc par bloc (document 04, § 4.1).
     *
     * @return array<string, mixed>
     */
    public function donnees(NoteLiaison $note): array
    {
        $interpretation = InterpretationVisite::query()->withoutGlobalScopes()->findOrFail($note->interpretation_visite_id);
        $visite = Visite::query()->withoutGlobalScopes()->findOrFail($interpretation->visite_id);
        $elevage = $this->elevage($visite->elevage_id);
        $fenetre = (int) $this->referentiels->parametre('interpretation.fenetre_evenements_mois');
        $analyses = Analyse::query()->withoutGlobalScopes()
            ->whereIn('prelevement_id', DB::table('fourrage.prelevement')->where('visite_id', $visite->id)->select('id'))
            ->where('statut', '!=', Analyse::STATUT_ANNULE)->orderBy('num_echantillon_labo')->get();

        $alertes = [];
        $doses = [];
        foreach ($analyses as $analyse) {
            $doses = [...$doses, ...DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->whereNotNull('valeur')->pluck('parametre_code')->all()];
            $lot = $this->interpretations->courante($analyse);
            foreach ($lot === null ? [] : $this->interpretations->lignesAlertes($lot) as $ligne) {
                if ($ligne['alerte']->retenue && $ligne['alerte']->gravite >= 2) {
                    $alertes[] = [...$ligne, 'bulletin' => (string) $analyse->num_echantillon_labo];
                }
            }
        }

        return [
            'note' => $note,
            'interpretation' => $interpretation,
            'visite' => $visite,
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'ede' => DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('num_cheptel_ede'),
            'gds' => DB::table('app.gds')->where('id', $elevage->gds_id)->first(),
            'technicien' => DB::table('app.user')->where('id', $note->cree_par ?? $visite->technicien_id)->selectRaw("concat_ws(' ', prenom, nom) as nom")->value('nom'),
            'veterinaire' => DB::table('app.user')->where('id', $note->veterinaire_user_id)->selectRaw("concat_ws(' ', prenom, nom) as nom")->value('nom'),
            'evenements' => EvenementSanitaire::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)
                ->whereDate('date_evenement', '>=', $visite->date_visite->copy()->subMonths($fenetre))
                ->whereDate('date_evenement', '<=', $visite->date_visite)->orderByDesc('date_evenement')->get(),
            'troubles' => DB::table('ref.trouble_sanitaire')->pluck('libelle', 'code'),
            'bilan' => $visite->campagne_code === null ? [] : array_values(array_filter($this->bilan->lignes($elevage, $visite->campagne_code),
                fn (array $l) => $l['classe']?->estDegradee() === true)),
            'alertes' => $alertes,
            'nonDoses' => $analyses->isEmpty() ? [] : DB::table('ref.parametre')->whereIn('code', array_diff(FicheVisiteService::NON_DOSES_SIGNALES, $doses))->pluck('libelle')->all(),
            'retourAttendu' => $this->textes->texte('note_liaison.retour_attendu'),
        ];
    }

    public function piedDePage(NoteLiaison $note): string
    {
        $texte = e(config('brand.nom').' · Membre du réseau GDS France · note de liaison n° '.$note->id.' · version '.config('app.version'));

        return '<div style="width:100%;font-family:sans-serif;font-size:7pt;color:#4A5568;padding:0 12mm;display:flex;justify-content:space-between">'
            .'<span>'.$texte.'</span><span>Page <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>';
    }

    private function elevage(string $id): Elevage
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($id);
        $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $id)->value('raison_sociale'));

        return $elevage;
    }
}
