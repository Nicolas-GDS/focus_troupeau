{{-- Styles communs des documents PDF à la charte (§ 8.4) ; $ressources : App\Services\Pdf\RessourcesCharte --}}
<style>
@font-face { font-family: 'Outfit'; src: url('{{ $ressources['polices']['outfit'] }}') format('woff2'); font-weight: 100 900; }
@font-face { font-family: 'Source Serif 4'; src: url('{{ $ressources['polices']['serif'] }}') format('woff2'); font-weight: 200 900; }
@font-face { font-family: 'Source Serif 4'; src: url('{{ $ressources['polices']['serif_italique'] }}') format('woff2'); font-weight: 200 900; font-style: italic; }
{!! $ressources['css_jetons'] !!}
@page { size: A4; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; font-family: var(--ft-police-corps); font-size: 9.5pt; line-height: 1.4; color: var(--ft-texte); background: #fff; }
h1, h2, h3, th, .etat, .titre-police { font-family: var(--ft-police-titre); }
h1 { font-size: 17pt; margin: 0 0 2mm; color: var(--ft-quasi-noir); line-height: 1.2; }
h2 { font-size: 11.5pt; margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 2px solid var(--ft-entite-primaire); color: var(--ft-entite-texte-sur-clair); break-after: avoid; }
h3 { font-size: 10pt; margin: 3mm 0 1mm; break-after: avoid; }
p { margin: 1mm 0; }
.entete { display: flex; justify-content: space-between; align-items: center; padding-bottom: 3mm; border-bottom: 4px solid var(--ft-entite-primaire); }
.entete img.logo { height: 14mm; } .entete img.marque { height: 11mm; }
.identite { margin: 3mm 0; color: var(--ft-texte-doux); font-size: 9pt; }
.encadre { border-left: 3px solid var(--ft-entite-primaire); background: var(--ft-entite-fond-clair); padding: 2.5mm 3.5mm; margin: 2mm 0; break-inside: avoid; }
table { width: 100%; border-collapse: collapse; margin: 1.5mm 0 3mm; font-size: 8.5pt; break-inside: auto; }
th { text-align: left; font-weight: 600; color: var(--ft-texte-doux); border-bottom: 1px solid var(--ft-filet); padding: 1.2mm 1.5mm; }
td { border-bottom: 1px solid var(--ft-filet); padding: 1.2mm 1.5mm; vertical-align: top; }
tr { break-inside: avoid; }
table.matrice { font-size: 7.5pt; } table.matrice td, table.matrice th { padding: 1mm; text-align: center; } table.matrice th:first-child, table.matrice td:first-child { text-align: left; }
.etat { display: inline-flex; align-items: center; gap: 1mm; font-size: 7.5pt; font-weight: 600; border-radius: 1mm; padding: 0.3mm 1.2mm; white-space: nowrap; }
.etat img { width: 2.6mm; height: 2.6mm; }
.etat--critique { color: var(--ft-etat-critique); background: var(--ft-etat-critique-fond); }
.etat--alerte { color: var(--ft-etat-alerte); background: var(--ft-etat-alerte-fond); }
.etat--vigilance { color: var(--ft-etat-vigilance); background: var(--ft-etat-vigilance-fond); }
.etat--couvert { color: var(--ft-etat-couvert); background: var(--ft-etat-couvert-fond); }
.etat--information { color: var(--ft-etat-information); background: var(--ft-etat-information-fond); }
.a-valider { display: inline-block; margin-left: 1mm; font-family: var(--ft-police-titre); font-size: 6.5pt; font-style: italic; color: var(--ft-etat-information); }
.doux { color: var(--ft-texte-doux); font-size: 8pt; }
ol.messages { padding-left: 5mm; margin: 1mm 0; } ol.messages li { margin: 1.5mm 0; }
.action { border: 1px solid var(--ft-filet); border-radius: 1.5mm; padding: 2mm 3mm; margin: 1.5mm 0; break-inside: avoid; }
.responsable { font-family: var(--ft-police-titre); font-size: 7.5pt; font-weight: 600; border: 1px solid var(--ft-texte-doux); border-radius: 1mm; padding: 0 1.2mm; margin-left: 2mm; }
.limites { margin-top: 6mm; border-top: 1px solid var(--ft-filet); padding-top: 2mm; font-size: 8pt; color: var(--ft-texte-doux); break-inside: avoid; }
.signature { font-style: italic; color: var(--ft-violet-sante); }
.deux-colonnes { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
</style>
