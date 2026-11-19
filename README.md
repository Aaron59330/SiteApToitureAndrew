# Site AP Toiture

Site vitrine avec espace administrateur : modification de tous les textes et images, et galerie de réalisations.

## Démarrage

```bash
npm install
npm run set-admin     # crée votre compte (e-mail + mot de passe, 10 caractères minimum)
npm start             # http://localhost:3000
```

Connexion : `/admin` (lien « Administration » en bas de chaque page).

## Utilisation

- Une fois connecté, une barre apparaît en bas de chaque page.
- Cliquez sur un texte pour le modifier, sur une image pour la remplacer, puis **Enregistrer**.
- « Modification : non » permet de naviguer sur le site sans modifier.
- Page **Réalisations** : bouton « Ajouter des photos », puis cliquez sous chaque photo pour saisir titre et description.
  Flèches pour réordonner, croix pour supprimer.

## Hébergement

Ce site a besoin d'un serveur Node.js (GitHub Pages ne suffit pas). Il faut un disque persistant,
car les modifications et les photos sont stockées dans le dossier `data/` (à sauvegarder régulièrement).

Variables d'environnement utiles :

| Variable | Rôle |
|---|---|
| `PORT` | port d'écoute (défaut 3000) |
| `DATA_DIR` | dossier de données (à placer sur le disque persistant) |
| `ADMIN_EMAIL` + `ADMIN_PASSWORD` (ou `ADMIN_PASSWORD_HASH`) | compte admin sans passer par `set-admin` |
| `SESSION_SECRET` | clé de signature des sessions (sinon générée dans `data/`) |
| `TRUST_PROXY=1` | derrière un reverse proxy / HTTPS géré par l'hébergeur |
| `NODE_ENV=production` | cookie de session en mode « Secure » (HTTPS) |

## Structure

- `public/` : pages HTML, CSS, JS, images (valeurs par défaut du site)
- `server/` : serveur, authentification, rendu des pages
- `tools/build.py` : régénère les pages HTML par défaut (`python3 tools/build.py`)
- `data/` : modifications, photos, compte admin (non versionné)
