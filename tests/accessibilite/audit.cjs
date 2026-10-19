// Audit d'accessibilité automatisé des écrans éleveur (T-41, ENF-ACC-01) : axe-core (règles WCAG 2.1 A et AA) dans le
// Chromium de l'image, en largeur téléphone (360 px) et bureau (1280 px). Une partie seulement du RGAA est
// automatisable : la navigation clavier, la pertinence des alternatives et l'ordre de lecture se vérifient à la main
// (docs/recette/L8_accessibilite.md).
// Usage (conteneur app) : php artisan accessibilite:auditer, ou
// node tests/accessibilite/audit.cjs <base> <fichier-json> <courriel|anonyme>:<mot-de-passe>:<chemin,chemin,…> […]
const puppeteer = require('/usr/local/lib/node_modules/puppeteer');
const fs = require('fs');
const axe = fs.readFileSync(__dirname + '/../../node_modules/axe-core/axe.min.js', 'utf8');

const [base, sortie, ...comptes] = process.argv.slice(2);
const LARGEURS = [360, 1280];

(async () => {
  const navigateur = await puppeteer.launch({ executablePath: '/usr/bin/chromium', args: ['--no-sandbox'] });
  const rapport = [];
  for (const compte of comptes) {
    const [courriel, motDePasse, chemins] = compte.split(':');
    const contexte = await navigateur.createBrowserContext(); // une session par compte
    const page = await contexte.newPage();
    if (courriel !== 'anonyme') {
      await page.goto(base + '/connexion', { waitUntil: 'networkidle0' });
      await page.type('#email', courriel);
      await page.type('#password', motDePasse);
      await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    }
    for (const chemin of chemins.split(',')) {
      for (const largeur of LARGEURS) {
        await page.setViewport({ width: largeur, height: 900 });
        const reponse = await page.goto(base + chemin, { waitUntil: 'networkidle0' });
        await page.evaluate(axe); // la CSP refuse un script en ligne : l'outil s'évalue dans la page
        const resultat = await page.evaluate(() => axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
        rapport.push({
          compte: courriel, chemin, largeur, statut: reponse.status(), titre: await page.title(),
          violations: resultat.violations.map(v => ({ id: v.id, impact: v.impact, aide: v.help, noeuds: v.nodes.length,
            exemples: v.nodes.slice(0, 3).map(n => ({ cible: n.target.join(' '), resume: n.failureSummary })) })),
        });
      }
    }
    await contexte.close();
  }
  await navigateur.close();
  fs.writeFileSync(sortie, JSON.stringify(rapport, null, 2));
  const graves = rapport.flatMap(r => r.violations.filter(v => ['serious', 'critical'].includes(v.impact)).map(v => `${r.chemin} (${r.largeur} px) : ${v.id} ×${v.noeuds}`));
  const autres = rapport.flatMap(r => r.violations.filter(v => !['serious', 'critical'].includes(v.impact)).map(v => `${r.chemin} (${r.largeur} px) : ${v.id} ×${v.noeuds}`));
  console.log(`${rapport.length} pages auditées ; ${graves.length} violations graves ou critiques ; ${autres.length} autres.`);
  graves.forEach(g => console.log('GRAVE  ' + g));
  autres.forEach(a => console.log('MINEUR ' + a));
  const http = rapport.filter(r => r.statut !== 200).map(r => `${r.chemin} : HTTP ${r.statut}`);
  http.forEach(h => console.log('HTTP   ' + h));
  process.exit(graves.length + http.length > 0 ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
