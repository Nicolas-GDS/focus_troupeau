<?php

namespace App\Livewire\Dossier\Saisie;

use App\Livewire\Dossier\Concerns\SaisieDeVisite;
use App\Livewire\Dossier\Visites;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\EvenementSanitaire;
use App\Services\Dossier\BilanSanitaireService;
use App\Services\Dossier\VisiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Étape « collecter » (EF-M1-07, EF-M1-09) : date, motif et compte rendu de la visite ; indicateurs du bilan
 * sanitaire de la campagne avec leur position S1 / S2 ; événements sanitaires déclarés.
 */
class Collecte extends Component
{
    use SaisieDeVisite {
        mount as monterVisite;
    }

    public string $dateVisite = '';

    public string $motif = '';

    public string $motifDetail = '';

    public string $compteRendu = '';

    /** @var array<string, array{nb_cas?: string|null, denominateur?: string|null, valeur?: string|null}> */
    public array $indicateurs = [];

    /** @var array{type_evenement: string, espece_code: string, date_evenement: string, nb_animaux: string, gravite: string, description: string} */
    public array $evenement = ['type_evenement' => '', 'espece_code' => 'BOV', 'date_evenement' => '', 'nb_animaux' => '', 'gravite' => '', 'description' => ''];

    public function mount(int $visiteId): void
    {
        $this->monterVisite($visiteId);
        $visite = $this->visite();
        $this->dateVisite = $visite->date_visite->toDateString();
        $this->motif = $visite->motif;
        $this->motifDetail = (string) $visite->motif_detail;
        $this->compteRendu = (string) $visite->compte_rendu;
        $this->evenement['espece_code'] = $visite->elevage->listeEspeces()[0] ?? 'BOV';
    }

    public function enregistrerVisite(VisiteService $service): void
    {
        $visite = $this->visite();
        $this->authorize('update', $visite);
        $this->validate([
            'dateVisite' => ['required', 'date'],
            'motif' => ['required', Rule::in(Visite::MOTIFS)],
            'motifDetail' => ['nullable', 'string', 'max:500'],
            'compteRendu' => ['nullable', 'string', 'max:10000'],
        ], [], ['dateVisite' => 'date de visite', 'compteRendu' => 'compte rendu']);

        $this->executer(fn () => $service->mettreAJour($visite, $this->dateVisite, $this->motif, $this->motifDetail, $this->compteRendu),
            'Visite enregistrée.');
    }

    public function enregistrerIndicateur(string $code, BilanSanitaireService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [IndicateurSanitaire::class, $visite->elevage]);
        $this->validate([
            "indicateurs.{$code}.nb_cas" => ['nullable', 'integer', 'min:0'],
            "indicateurs.{$code}.denominateur" => ['nullable', 'integer', 'min:1'],
            "indicateurs.{$code}.valeur" => ['nullable', 'numeric'],
        ], [], ["indicateurs.{$code}.nb_cas" => 'nombre de cas', "indicateurs.{$code}.denominateur" => 'nombre de vêlages', "indicateurs.{$code}.valeur" => 'valeur']);

        if ($visite->campagne_code === null) {
            $this->addError('saisie', 'La visite n\'est rattachée à aucune campagne : le bilan sanitaire ne peut pas être saisi.');

            return;
        }

        $saisie = $this->indicateurs[$code] ?? [];
        $this->executer(fn () => $service->enregistrer($visite->elevage, $visite->campagne_code, $code,
            self::entierOuNul($saisie['nb_cas'] ?? null), self::entierOuNul($saisie['denominateur'] ?? null), self::nombreOuNul($saisie['valeur'] ?? null)),
            'Indicateur enregistré.');
    }

    public function declarerEvenement(BilanSanitaireService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [IndicateurSanitaire::class, $visite->elevage]);
        $this->validate([
            'evenement.type_evenement' => ['required', Rule::in(self::codes('ref.trouble_sanitaire'))],
            'evenement.espece_code' => ['required', Rule::in(self::codes('ref.espece_animale'))],
            'evenement.date_evenement' => ['required', 'date'],
            'evenement.nb_animaux' => ['nullable', 'integer', 'min:1'],
            'evenement.gravite' => ['nullable', 'integer', 'between:1,3'],
            'evenement.description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'evenement.type_evenement' => 'trouble', 'evenement.espece_code' => 'espèce', 'evenement.date_evenement' => 'date',
            'evenement.nb_animaux' => 'nombre d\'animaux', 'evenement.gravite' => 'gravité',
        ]);

        $this->executer(function () use ($service, $visite) {
            $service->declarerEvenement($visite->elevage, [
                'type_evenement' => $this->evenement['type_evenement'],
                'espece_code' => $this->evenement['espece_code'],
                'date_evenement' => $this->evenement['date_evenement'],
                'nb_animaux' => self::entierOuNul($this->evenement['nb_animaux']),
                'gravite' => self::entierOuNul($this->evenement['gravite']),
                'source' => 'gds',
                'description' => $this->evenement['description'] === '' ? null : $this->evenement['description'],
            ]);
            $this->evenement = [...$this->evenement, 'type_evenement' => '', 'date_evenement' => '', 'nb_animaux' => '', 'gravite' => '', 'description' => ''];
        }, 'Événement enregistré.');
    }

    public function render(BilanSanitaireService $service): View
    {
        $visite = $this->visite();

        return view('livewire.dossier.saisie.collecte', [
            'visite' => $visite,
            'modifiable' => $this->modifiable(),
            'peutSaisirBilan' => Gate::allows('create', [IndicateurSanitaire::class, $visite->elevage]),
            'motifs' => Visites::motifs(),
            'bilan' => $visite->campagne_code === null ? [] : $service->lignes($visite->elevage, $visite->campagne_code),
            'evenements' => EvenementSanitaire::query()->where('elevage_id', $visite->elevage_id)->orderByDesc('date_evenement')->limit(50)->get(),
            'troubles' => DB::table('ref.trouble_sanitaire')->whereNull('version_fin')->orderBy('libelle')->pluck('libelle', 'code'),
            'especes' => DB::table('ref.espece_animale')->pluck('libelle', 'code'),
        ]);
    }

    private static function entierOuNul(mixed $valeur): ?int
    {
        return $valeur === null || $valeur === '' ? null : (int) $valeur;
    }
}
