# Captures d'écran

Produites par le banc navigateur (`capture.js`, Puppeteer + Chrome dans le conteneur
`puppeteer-test`) contre le banc GLPI 11 de `/opt/docker/GLPI12-banc`. **Jamais prises à
la main.**

| Fichier | Ce qu'il montre |
|---|---|
| `01-settings.png` | La page de réglages : URL du conteneur (fictive), suivi de l'écran de connexion activé, identité en mode *pseudonyme*. |

Le script sert aussi de test en vrai navigateur. Il échoue :
- si MTM ne démarre pas sur l'écran de connexion ;
- si une identité apparaît sur cette page anonyme ;
- si, une fois connecté, `glpiUserId` n'est pas poussé dans `_mtm` **avant** `mtm.Start`.

Toute requête hors du banc est bloquée, l'URL du conteneur étant fictive.

## Les refaire

Pré-requis : le banc GLPI 11 joignable par `puppeteer-test`
(`podman network connect glpi12_banc puppeteer-test`), le greffon réglé en mode
pseudonyme avec suivi de l'écran de connexion.

```bash
podman cp docs/captures/capture.js puppeteer-test:/app/scripts/matomo/capture.js
podman exec -e GLPI_URL=http://10.89.20.11 -e GLPI_USER=banc12 -e GLPI_PASS="…" \
  -e OUT=/app/screenshots/matomo puppeteer-test node /app/scripts/matomo/capture.js
podman cp puppeteer-test:/app/screenshots/matomo/01-settings.png docs/captures/
```
