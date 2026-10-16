<?php

namespace App\Livewire\Dossier\Interpretation;

use App\Livewire\Interpretation\InterpretationBulletin;
use App\Models\App\User;
use App\Models\Dossier\CauseCommune;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\Visite;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * E21 — interprétation de visite et publication (EF-M2-04 à EF-M2-07) : éléments à croiser (lots, eau, mesures,
 * profils, bilan sanitaire, familles de cause proposées), synthèse et niveau retenu, cinq messages au plus, causes
 * communes, circuit brouillon → valide → (visée) → publiée, versions. Le plan d'action est un composant voisin.
 * La visite est relue à chaque requête à travers les scopes et chaque action est autorisée côté serveur (ENF-SEC-04).
 */
class EcranVisite extends Component
{
    #[Locked]
    public int $visiteId;

    public string $titre = '';

    public string $synthese = '';

    public string $limites = '';

    public ?string $niveauRetenu = null;

    /** @var list<array{gravite: string, texte: string, pilier: string|null, origine: string, reference_objet: string|null}> */
    public array $messages = [];

    /** @var array{famille: string, titre: string, preuves: string, consequences: string} */
    public array $cause = ['famille' => '', 'titre' => '', 'preuves' => '', 'consequences' => ''];

    public string $motifRefusVisa = '';

    public function mount(Visite $visite): void
    {
        $this->authorize('view', $visite);
        $this->visiteId = $visite->id;
        $this->remplir();
    }

    public function creer(InterpretationVisiteService $service): void
    {
        $visite = $this->visite();
        $this->authorize('create', [InterpretationVisite::class, $visite]);
        $this->executer(function () use ($service, $visite) {
            $service->creerBrouillon($visite, $this->utilisateur());
            $this->remplir();
        }, 'Brouillon créé : niveau et messages proposés à partir des lots et de l\'eau.');
    }

    public function ajouterMessage(): void
    {
        $this->messages[] = ['gravite' => '1', 'texte' => '', 'pilier' => null, 'origine' => 'technicien', 'reference_objet' => null];
    }

    public function retirerMessage(int $index): void
    {
        unset($this->messages[$index]);
        $this->messages = array_values($this->messages);
    }

    public function monterMessage(int $index): void
    {
        if ($index > 0 && isset($this->messages[$index])) {
            [$this->messages[$index - 1], $this->messages[$index]] = [$this->messages[$index], $this->messages[$index - 1]];
        }
    }

    public function enregistrer(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('update');
        $this->validerRedaction();

        $this->executer(fn () => $this->sauver($service, $interpretation), 'Interprétation de visite enregistrée.');
    }

    public function reproposer(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('update');
        $this->executer(function () use ($service, $interpretation) {
            $service->reproposerMessages($interpretation, $this->visite());
            $this->remplir();
        }, 'Messages et niveau proposés recalculés.');
    }

    public function ajouterCause(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('update');
        $this->validate([
            'cause.famille' => ['required', Rule::in(CauseCommune::FAMILLES)],
            'cause.titre' => ['required', 'string', 'max:300'],
            'cause.preuves' => ['nullable', 'string', 'max:2000'],
            'cause.consequences' => ['nullable', 'string', 'max:2000'],
        ], [], ['cause.famille' => 'famille de cause', 'cause.titre' => 'intitulé', 'cause.preuves' => 'preuves', 'cause.consequences' => 'conséquences']);

        $this->executer(function () use ($service, $interpretation) {
            $service->ajouterCause($interpretation, $this->cause['famille'], $this->cause['titre'], $this->cause['preuves'], $this->cause['consequences']);
            $this->reset('cause');
        });
    }

    public function supprimerCause(int $id, InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('update');
        $cause = CauseCommune::query()->where('interpretation_visite_id', $interpretation->id)->findOrFail($id);
        $this->executer(fn () => $service->supprimerCause($cause));
    }

    public function valider(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('valider');
        $this->validerRedaction();

        $this->executer(function () use ($service, $interpretation) {
            $this->sauver($service, $interpretation);
            $service->valider($interpretation->refresh(), $this->utilisateur());
        }, 'Interprétation de visite validée.');
    }

    public function viser(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('viser');
        $this->executer(fn () => $service->viser($interpretation, $this->utilisateur()), 'Interprétation visée : le technicien peut la publier.');
    }

    public function refuserVisa(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('refuserVisa');
        $this->validate(['motifRefusVisa' => ['required', 'string', 'min:5', 'max:1000']], [], ['motifRefusVisa' => 'motif du refus']);
        $this->executer(function () use ($service, $interpretation) {
            $service->refuserVisa($interpretation, $this->motifRefusVisa, $this->utilisateur());
            $this->motifRefusVisa = '';
        }, 'Visa refusé : l\'interprétation revient en brouillon chez le technicien.');
    }

    public function publier(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('publier');
        $this->executer(fn () => $service->publier($interpretation, $this->utilisateur()), 'Visite publiée : l\'éleveur peut consulter sa fiche.');
    }

    public function nouvelleVersion(InterpretationVisiteService $service): void
    {
        $interpretation = $this->autorisee('nouvelleVersion');
        $this->executer(function () use ($service, $interpretation) {
            $service->nouvelleVersion($interpretation, $this->utilisateur());
            $this->remplir();
        }, 'Nouvelle version créée en brouillon ; la version publiée reste visible de l\'éleveur jusqu\'à la publication.');
    }

    public function render(InterpretationVisiteService $service): View
    {
        $visite = $this->visite();
        $interpretation = $this->interpretation();
        $agent = $this->voitTout();

        return view('livewire.dossier.interpretation.ecran-visite', [
            'visite' => $visite,
            'libelleElevage' => ElevageCourant::libelle($visite->elevage),
            'interpretation' => $interpretation,
            'elements' => $agent ? $service->elementsACroiser($visite) : null,
            'causes' => $interpretation === null ? collect() : CauseCommune::query()->where('interpretation_visite_id', $interpretation->id)->orderBy('id')->get(),
            'messagesLus' => $interpretation === null ? collect() : Message::query()->where('interpretation_visite_id', $interpretation->id)->orderBy('ordre')->get(),
            'versions' => $agent ? $service->versions($visite) : collect(),
            'obstacles' => $interpretation !== null && $agent && $interpretation->statut !== InterpretationVisite::STATUT_PUBLIE ? $service->obstaclesPublication($visite) : [],
            'peutCreer' => $interpretation === null && Gate::allows('create', [InterpretationVisite::class, $visite]),
            'peutModifier' => $interpretation !== null && Gate::allows('update', $interpretation),
            'peutViser' => $interpretation !== null && Gate::allows('viser', $interpretation),
            'peutPublier' => $interpretation !== null && Gate::allows('publier', $interpretation),
            'peutNouvelleVersion' => $interpretation !== null && Gate::allows('nouvelleVersion', $interpretation),
            'agent' => $agent,
            'lienFiche' => fn (InterpretationVisite $v) => URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(30), ['visite' => $v->visite_id, 'version' => $v->version]),
            'familles' => self::familles(),
            'piliers' => self::piliers(),
        ])->layout('components.layouts.espace', ['titre' => 'Interprétation de la visite du '.$visite->date_visite->format('d/m/Y')]);
    }

    /** @return array<string, string> */
    public static function familles(): array
    {
        return [
            'immunite_oligo' => 'Immunité, vitamines, oligo-éléments',
            'metabolisme_mineral' => 'Métabolisme minéral',
            'metabolisme_energetique' => 'Métabolisme énergétique',
            'confort' => 'Confort',
            'infectieux' => 'Infectieux',
        ];
    }

    /** @return array<string, string> les cinq piliers de la démarche */
    public static function piliers(): array
    {
        return ['hydratation' => 'Hydratation', 'nutrition' => 'Nutrition', 'physique' => 'Physique', 'environnement' => 'Environnement', 'bien_etre' => 'Bien-être'];
    }

    /** @return array{libelle: string, etat: string} */
    public static function statut(string $statut): array
    {
        return InterpretationBulletin::statut($statut);
    }

    private function visite(): Visite
    {
        return Visite::query()->with('elevage')->findOrFail($this->visiteId);
    }

    /** Version montrée : la version en cours si l'utilisateur peut la lire, sinon la dernière version publiée. */
    private function interpretation(): ?InterpretationVisite
    {
        $service = app(InterpretationVisiteService::class);
        $courante = $service->courante($this->visite());

        if ($courante === null || Gate::allows('view', $courante)) {
            return $courante;
        }

        return $service->publiee($this->visite());
    }

    private function voitTout(): bool
    {
        return app(RegleStatut::class)->voitTousLesStatuts($this->utilisateur());
    }

    private function autorisee(string $capacite): InterpretationVisite
    {
        $interpretation = $this->interpretation() ?? abort(404);
        $this->authorize($capacite, $interpretation);

        return $interpretation;
    }

    private function remplir(): void
    {
        $interpretation = $this->interpretation();

        if ($interpretation === null) {
            return;
        }

        $this->titre = (string) $interpretation->titre;
        $this->synthese = (string) $interpretation->synthese;
        $this->limites = (string) $interpretation->limites;
        $this->niveauRetenu = $interpretation->niveau_global === null ? null : (string) $interpretation->niveau_global;
        $this->messages = Message::query()->where('interpretation_visite_id', $interpretation->id)->orderBy('ordre')->get()
            ->map(fn (Message $m) => ['gravite' => (string) $m->gravite, 'texte' => $m->texte, 'pilier' => $m->pilier, 'origine' => $m->origine, 'reference_objet' => $m->reference_objet])
            ->values()->all();
    }

    private function validerRedaction(): void
    {
        $this->validate([
            'titre' => ['nullable', 'string', 'max:300'],
            'synthese' => ['nullable', 'string', 'max:3000'],
            'limites' => ['nullable', 'string', 'max:3000'],
            'niveauRetenu' => ['nullable', 'integer', 'between:0,3'],
            'messages' => ['array'],
            'messages.*.gravite' => ['required', 'integer', 'between:0,3'],
            'messages.*.texte' => ['required', 'string', 'min:5', 'max:500'],
            'messages.*.pilier' => ['nullable', Rule::in(Message::PILIERS)],
        ], [], ['synthese' => 'synthèse', 'niveauRetenu' => 'niveau retenu', 'messages.*.texte' => 'texte du message', 'messages.*.gravite' => 'gravité']);
    }

    private function sauver(InterpretationVisiteService $service, InterpretationVisite $interpretation): void
    {
        $service->enregistrer($interpretation, new RedactionVisite($this->titre, $this->synthese, $this->limites,
            $this->niveauRetenu === null || $this->niveauRetenu === '' ? null : (int) $this->niveauRetenu), $this->utilisateur());
        $service->remplacerMessages($interpretation, array_map(fn (array $m) => [
            'gravite' => (int) $m['gravite'], 'texte' => $m['texte'], 'pilier' => $m['pilier'] ?: null,
            'origine' => $m['origine'], 'reference_objet' => $m['reference_objet'],
        ], $this->messages));
    }

    private function executer(callable $operation, ?string $message = null): void
    {
        try {
            $operation();

            if ($message !== null) {
                session()->flash('status', $message);
            }
        } catch (SaisieRefusee $e) {
            $this->addError('circuit', $e->getMessage());
        }
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
