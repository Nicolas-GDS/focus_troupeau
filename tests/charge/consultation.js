// T-42 (ENF-PERF-01) : 50 éleveurs simultanés consultent leur dossier, leurs stocks et leur autonomie.
// Seuil : affichage d'un écran de consultation en moins d'une seconde côté serveur (95e centile).
// Lancer : docker compose --profile charge run --rm k6 run /scripts/consultation.js
// Prérequis : php artisan db:seed (J1, J2, J3) puis php artisan db:seed --class=JeuChargeSeeder.
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = __ENV.BASE || 'http://nginx';
const MOT_DE_PASSE = __ENV.MOT_DE_PASSE || 'j4-developpement-seulement';

export const options = {
  noCookiesReset: true, // une session par utilisateur simulé, gardée d'une itération à l'autre
  scenarios: {
    consultation: { executor: 'constant-vus', vus: 50, duration: __ENV.DUREE || '3m' },
  },
  thresholds: {
    'http_req_duration{type:consultation}': ['p(95)<1000'],
    ...Object.fromEntries(['E02', 'E04', 'E05', 'E10', 'E11', 'E12', 'E14'].map(e => [`http_req_duration{ecran:${e}}`, ['p(95)<1000']])),
    'http_req_failed': ['rate<0.01'],
    'checks': ['rate>0.99'],
  },
};

function connecter() {
  const courriel = `charge.${String(__VU).padStart(2, '0')}@charge.test`;
  const formulaire = http.get(`${BASE}/connexion`, { tags: { type: 'connexion' } });
  const jeton = formulaire.html().find('input[name=_token]').attr('value');
  const reponse = http.post(`${BASE}/connexion`, { _token: jeton, email: courriel, password: MOT_DE_PASSE }, { tags: { type: 'connexion' } });
  check(reponse, { 'connexion réussie': r => r.status === 200 && !r.url.endsWith('/connexion') });
}

/** Liens du dossier de l'élevage courant, lus sur l'accueil (l'élevage dépend du compte). */
function liensDossier() {
  const accueil = http.get(`${BASE}/accueil`, { tags: { type: 'consultation', ecran: 'E02' } });
  check(accueil, { 'accueil affiché': r => r.status === 200 });
  const liens = accueil.html().find('a[href*="/elevages/"]').map((_, a) => a.attr('href'));
  const elevage = (liens.find(l => /\/elevages\/[0-9a-f-]{36}/.test(l)) || '').match(/\/elevages\/([0-9a-f-]{36})/);
  return elevage ? elevage[1] : null;
}

export default function () {
  if (__ITER === 0) {
    connecter();
  }
  const elevage = liensDossier();
  check(elevage, { 'élevage trouvé sur l\'accueil': e => e !== null });
  if (!elevage) {
    sleep(2);
    return;
  }
  for (const [ecran, chemin] of [['E04', 'fourrages'], ['E05', 'mineraux-matrice'], ['E10', 'historique'], ['E12', 'stocks'], ['E14', 'autonomie'], ['E11', 'plan-action']]) {
    group(ecran, () => {
      const r = http.get(`${BASE}/elevages/${elevage}/${chemin}`, { tags: { type: 'consultation', ecran } });
      check(r, { [`${ecran} affiché`]: x => x.status === 200 });
    });
    sleep(1 + Math.random() * 2); // temps de lecture
  }
}
