<?php

namespace App\Services\Notifications;

use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Interpretation;
use App\Services\ElevageCourant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Notifications de publication (EF-M8-01, EF-M8-02, T-22) : e-mail aux éleveurs de l'élevage à la publication d'une
 * fiche de visite ou d'un résultat, copie au technicien ; SMS aux éleveurs et au technicien pour un niveau retenu 3
 * (texte court : objet concerné, consigne immédiate, contact), toujours envoyé. Les textes sont des textes types
 * (ref.texte_type) ; la consigne reprend le premier message de gravité 3, issu des référentiels (EF-GEN-04).
 */
class NotificationsPublication
{
    /** Longueur de la consigne reprise dans le SMS (un SMS reste court, EF-M8-02). */
    private const CONSIGNE_SMS_MAX = 120;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TextesTypes $textes,
    ) {}

    public function visitePubliee(InterpretationVisite $interpretation, User $auteur): void
    {
        $visite = Visite::query()->withoutGlobalScopes()->findOrFail($interpretation->visite_id);
        $elevage = $this->elevage($visite->elevage_id);
        $libelle = ElevageCourant::libelle($elevage);
        $date = $visite->date_visite->format('d/m/Y');
        $reference = 'dossier.interpretation_visite:'.$interpretation->id;
        $variables = ['date' => $date, 'elevage' => $libelle];
        $objet = $this->textes->texte('notification.fiche_publiee.objet', $variables);
        $lignes = array_map(fn (int $n) => $this->textes->texte("notification.fiche_publiee.ligne{$n}", $variables), [1, 2, 3]);
        $lien = route('visites.interpretation', $visite->id);
        $technicien = $this->technicien($visite->technicien_id, $auteur);

        $this->courriels($elevage, $technicien, 'fiche_publiee', $objet, $lignes, $lien, $reference, $interpretation->niveau_global === 3);

        if ($interpretation->niveau_global === 3) {
            $consigne = Message::query()->withoutGlobalScopes()->where('interpretation_visite_id', $interpretation->id)
                ->where('gravite', 3)->orderBy('ordre')->value('texte');
            $this->sms($elevage, $technicien, 'visite du '.$date, $consigne, $reference);
        }
    }

    public function resultatPublie(Interpretation $interpretation, User $auteur): void
    {
        $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($interpretation->analyse_id);
        $prelevement = DB::table('fourrage.prelevement as p')->leftJoin('ref.type_fourrage as t', 't.code', '=', 'p.type_fourrage_code')
            ->where('p.id', $analyse->prelevement_id)->first(['p.elevage_id', 'p.nom_lot', 't.libelle as type']);
        $elevage = $this->elevage((string) $prelevement?->elevage_id);
        $variables = ['bulletin' => (string) $analyse->num_echantillon_labo, 'elevage' => ElevageCourant::libelle($elevage)];
        $reference = 'fourrage.interpretation:'.$interpretation->id;
        $technicien = $this->technicien(null, $auteur);

        $this->courriels($elevage, $technicien, 'resultat_publie', $this->textes->texte('notification.resultat_publie.objet', $variables),
            array_map(fn (int $n) => $this->textes->texte("notification.resultat_publie.ligne{$n}", $variables), [1, 2, 3]),
            route('analyses.interpretation', $analyse->id), $reference, $interpretation->niveau_global === 3);

        if ($interpretation->niveau_global === 3) {
            $consigne = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->where('retenue', true)
                ->where('gravite', 3)->orderBy('id')->value('message');
            $lot = trim(($prelevement->nom_lot ?? $prelevement->type ?? '').' — bulletin '.$analyse->num_echantillon_labo, ' —');
            $this->sms($elevage, $technicien, $lot, $consigne, $reference);
        }
    }

    /**
     * @param  list<string>  $lignes
     */
    private function courriels(Elevage $elevage, User $technicien, string $type, string $objet, array $lignes, string $lien, string $reference, bool $niveau3): void
    {
        // EF-M8-05 : désinscription possible, sauf pour le niveau 3
        foreach ($this->eleveurs($elevage) as $eleveur) {
            $this->notifications->envoyer($eleveur, NotificationService::EMAIL, $type, $objet, $lignes, $lien, $elevage->id, $reference, $niveau3);
        }

        $this->notifications->envoyer($technicien, NotificationService::EMAIL, $type,
            $this->textes->texte('notification.copie_technicien.prefixe').$objet, $lignes, $lien, $elevage->id, $reference, $niveau3);
    }

    private function sms(Elevage $elevage, User $technicien, string $objet, ?string $consigne, string $reference): void
    {
        $contact = trim($technicien->nomComplet().' '.($technicien->telephone ?? ''));
        $consigne = $consigne === null ? '' : rtrim(mb_strimwidth($consigne, 0, self::CONSIGNE_SMS_MAX, '…'));
        $texte = $this->textes->texte('notification.niveau3.sms', [
            'marque' => (string) config('brand.nom'), 'elevage' => ElevageCourant::libelle($elevage), 'objet' => $objet,
            'consigne' => $consigne, 'contact' => $contact,
        ]);

        foreach ([...$this->eleveurs($elevage), $technicien] as $destinataire) {
            $this->notifications->envoyer($destinataire, NotificationService::SMS, 'niveau3', 'Alerte de niveau 3', [$texte],
                null, $elevage->id, $reference, obligatoire: true);
        }
    }

    /** @return Collection<int, User> comptes éleveurs actifs rattachés à l'élevage */
    private function eleveurs(Elevage $elevage): Collection
    {
        return User::query()->whereIn('id', DB::table('app.elevage_acces')->where('elevage_id', $elevage->id)->where('role', 'eleveur')->select('user_id'))
            ->where('actif', true)->orderBy('id')->get();
    }

    /** Technicien de la visite, à défaut l'auteur de la publication. */
    private function technicien(?int $technicienId, User $auteur): User
    {
        $technicien = $technicienId === null ? null : User::query()->find($technicienId);

        return $technicien ?? $auteur;
    }

    private function elevage(string $id): Elevage
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($id);
        $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $id)->value('raison_sociale'));

        return $elevage;
    }
}
