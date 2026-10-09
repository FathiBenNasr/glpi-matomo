# Captures d'écran

Produites par le banc navigateur (`capture.js`, Puppeteer + Chrome dans le conteneur
`puppeteer-test`) contre un banc GLPI 11 de test. **Jamais prises à
la main.**

| Fichier | Ce qu'il montre |
|---|---|
| `01-settings.png` | La page de réglages : URL du conteneur (fictive), suivi de l'écran de connexion activé, identité en mode *pseudonyme*. |

Le script sert aussi de test en vrai navigateur. Il échoue :
- si MTM ne démarre pas sur l'écran de connexion ;
- si une identité apparaît sur cette page anonyme ;
- si le conteneur se charge pour le compte administrateur (`GLPI_USER`), ou sur la page de
  réglages : depuis la 1.0.5 / 1.1.3, ni une session d'administration ni une page
  d'administration ne l'exécutent ;
- si, avec un compte sans droit d'administration (`TRACKED_USER`, facultatif), `glpiUserId`
  n'est pas poussé dans `_mtm` **avant** `mtm.Start`.

Toute requête hors du banc est bloquée, l'URL du conteneur étant fictive.

## Les refaire

Pré-requis : le banc GLPI joignable par le conteneur `puppeteer-test`, le greffon réglé en
mode pseudonyme avec suivi de l'écran de connexion.

Les identifiants restent dans l'environnement de l'appelant : `podman exec -e NOM` (sans
`=valeur`) recopie la variable sans qu'elle apparaisse dans les arguments du processus.

```bash
export GLPI_URL=https://glpi-bench.example GLPI_USER=… GLPI_PASS=…   # compte administrateur
export TRACKED_USER=… TRACKED_PASS=…                                 # facultatif : compte sans droit d'administration
export OUT=/app/screenshots/matomo
podman cp docs/captures/capture.js puppeteer-test:/app/scripts/matomo/capture.js
podman exec -e GLPI_URL -e GLPI_USER -e GLPI_PASS -e TRACKED_USER -e TRACKED_PASS -e OUT \
  puppeteer-test node /app/scripts/matomo/capture.js
podman cp puppeteer-test:/app/screenshots/matomo/01-settings.png docs/captures/
```
