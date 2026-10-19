// Brouillon local de saisie mobile (ENF-ACC-03, chapitre 6.1) : une perte de réseau ou un rechargement au silo ne fait
// pas perdre la saisie. Les champs `wire:model` du formulaire sont gardés dans le navigateur (localStorage), chiffrés
// en AES-GCM avec une clé propre au compte (fournie par la page), proposés à la reprise, purgés quand l'enregistrement
// réussit (événement Livewire « brouillon-purge »).
const encodeur = new TextEncoder();
const decodeur = new TextDecoder();
const enBase64 = (octets) => btoa(String.fromCharCode(...new Uint8Array(octets)));
const deBase64 = (texte) => Uint8Array.from(atob(texte), (c) => c.charCodeAt(0));

async function cle(secret) {
    const empreinte = await crypto.subtle.digest('SHA-256', encodeur.encode(secret));
    return crypto.subtle.importKey('raw', empreinte, 'AES-GCM', false, ['encrypt', 'decrypt']);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('brouillon', (identifiant, secret) => ({
        disponible: false,
        date: '',
        stockage: 'ft-brouillon:' + identifiant,
        minuterie: null,

        init() {
            if (!window.crypto?.subtle) {
                return; // contexte non sécurisé : pas de brouillon plutôt qu'un brouillon en clair
            }
            const existant = this.lire();
            if (existant) {
                this.disponible = true;
                this.date = new Date(existant.date).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
            }
            this.$el.addEventListener('input', () => this.planifier());
            this.$el.addEventListener('change', () => this.planifier());
            window.addEventListener('brouillon-purge', (e) => {
                if ((e.detail?.cle ?? e.detail?.[0]?.cle) === identifiant) {
                    this.oublier();
                }
            });
        },

        lire() {
            try {
                return JSON.parse(localStorage.getItem(this.stockage) ?? 'null');
            } catch {
                return null;
            }
        },

        champs() {
            const valeurs = {};
            this.$el.querySelectorAll('[wire\\:model], [wire\\:model\\.live], [wire\\:model\\.blur]').forEach((champ) => {
                const nom = champ.getAttribute('wire:model') ?? champ.getAttribute('wire:model.live') ?? champ.getAttribute('wire:model.blur');
                if (champ.type === 'radio') {
                    if (champ.checked) valeurs[nom] = champ.value;
                } else {
                    valeurs[nom] = champ.type === 'checkbox' ? champ.checked : champ.value;
                }
            });
            return valeurs;
        },

        planifier() {
            clearTimeout(this.minuterie);
            this.minuterie = setTimeout(() => this.enregistrer(), 500);
        },

        async enregistrer() {
            if (!window.crypto?.subtle) return;
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const chiffre = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, await cle(secret), encodeur.encode(JSON.stringify(this.champs())));
            localStorage.setItem(this.stockage, JSON.stringify({ iv: enBase64(iv), donnees: enBase64(chiffre), date: Date.now() }));
        },

        async reprendre() {
            const existant = this.lire();
            if (!existant) return;
            try {
                const clair = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: deBase64(existant.iv) }, await cle(secret), deBase64(existant.donnees));
                for (const [nom, valeur] of Object.entries(JSON.parse(decodeur.decode(clair)))) {
                    this.$wire.set(nom, valeur, false);
                }
                await this.$wire.$refresh();
            } catch {
                this.oublier(); // brouillon d'un autre compte ou altéré
            }
            this.disponible = false;
        },

        oublier() {
            localStorage.removeItem(this.stockage);
            this.disponible = false;
        },
    }));
});
