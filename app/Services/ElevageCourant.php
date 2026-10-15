<?php

namespace App\Services;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Élevage courant de la session (EF-M0-08) : sélecteur en tête d'écran, co-branding de l'en-tête (§ 8.1).
 *
 * La liste passe par les scopes de cloisonnement : un utilisateur ne peut ni voir ni choisir un élevage hors
 * de son périmètre. Le libellé affiché est la raison sociale (prive.elevage_identite) lorsqu'elle est connue,
 * sinon le code pseudonyme.
 */
class ElevageCourant
{
    private const CLE_SESSION = 'elevage_courant';

    public function __construct(private readonly Session $session) {}

    /**
     * @return Collection<int, Elevage>
     */
    public function accessibles(): Collection
    {
        return Elevage::query()
            ->leftJoin('prive.elevage_identite as identite', 'identite.elevage_id', '=', 'fourrage.elevage.id')
            ->select('fourrage.elevage.*', 'identite.raison_sociale')
            ->orderByRaw('coalesce(identite.raison_sociale, fourrage.elevage.code_pseudo)')
            ->get();
    }

    /**
     * L'élevage courant : celui choisi dans la session s'il est toujours accessible, sinon le premier accessible.
     */
    /**
     * @param  Collection<int, Elevage>|null  $elevages  liste déjà chargée par accessibles(), le cas échéant
     */
    public function courant(?Collection $elevages = null): ?Elevage
    {
        $id = $this->session->get(self::CLE_SESSION);
        $elevages ??= $this->accessibles();

        return $elevages->firstWhere('id', $id) ?? $elevages->first();
    }

    public function choisir(User $user, Elevage $elevage): void
    {
        Gate::forUser($user)->authorize('view', $elevage);

        $this->session->put(self::CLE_SESSION, $elevage->id);
    }

    public static function libelle(Elevage $elevage): string
    {
        $raisonSociale = $elevage->getAttribute('raison_sociale');

        return is_string($raisonSociale) && $raisonSociale !== '' ? $raisonSociale : $elevage->code_pseudo;
    }

    /**
     * GDS à afficher dans l'en-tête : celui de l'élevage courant, sinon l'unique GDS de rattachement de l'agent.
     */
    public function gdsAffiche(User $user, ?Elevage $elevage): ?int
    {
        if ($elevage !== null) {
            return $elevage->gds_id;
        }

        $gds = $user->gds()->pluck('id');

        return $gds->count() === 1 ? (int) $gds->first() : null;
    }
}
