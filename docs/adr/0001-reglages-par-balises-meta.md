# 0001 — Transmettre les réglages par balises meta et un chargeur statique

- Statut : accepté
- Date : consigné le 2026-11-15 ; décision antérieure (introduction du chargeur statique : à vérifier dans CHANGELOG.md)

## Contexte

Le greffon doit dire au navigateur quelle URL de conteneur charger et, éventuellement, quelle identité
pousser. L'approche naïve génère un bloc `<script>` contenant l'URL et l'identifiant : toute valeur
mal échappée (URL saisie par un administrateur, identifiant d'un utilisateur) devient du code exécuté
dans l'origine GLPI.

## Options

1. Générer du JavaScript en ligne avec les valeurs interpolées.
2. Écrire un fichier `.js` par instance contenant l'URL.
3. Un fichier JavaScript **statique**, et les valeurs en **données** dans des balises `<meta>` émises
   par les crochets `add_header_tag`.

## Décision

Option 3. Le greffon enregistre des balises `glpi-matomo-container` et `glpi-matomo-uid` que le
gabarit de GLPI échappe, et un seul script statique, `public/js/mtm-loader.js`, qui les relit.

## Conséquences

- Aucune interpolation dans du code : une valeur hostile reste une chaîne d'attribut.
- Aucun fichier écrit à l'exécution, aucun JavaScript propre à une instance, donc rien à purger.
- Le chargeur peut être testé tel quel dans un bac à sable (`tests/loader.test.js`).
- Le chargeur doit revalider l'URL (forme `container_<id>.js`) : c'est la seconde couche de
  l'[ADR 0002](0002-garde-fous-serveur-et-chargeur.md).
- Dépendance aux crochets `add_header_tag` et `add_javascript` de GLPI 11 et 12 (leurs variantes
  `_anonymous_page` pour l'écran de connexion).
