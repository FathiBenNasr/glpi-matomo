# 0002 — Aucun conteneur tiers pour les administrateurs ni sur les pages d'administration

- État : acceptée (décision prise dans les versions 1.0.4 à 1.0.6 ; consignée le 2026-11-23)
- Date : 2026-11-23

## Contexte

Un conteneur Matomo Tag Manager est du JavaScript que ses éditeurs peuvent changer à tout moment. Chargé dans une page de
GLPI, il s'exécute avec les droits de l'origine GLPI : il peut agir comme l'utilisateur de la page. Quiconque publie dans le
conteneur, ou compromet l'hôte Matomo, pourrait donc agir comme un administrateur GLPI qui ouvre une page suivie.

## Options envisagées

1. **Charger partout** et s'en remettre à la confiance dans Matomo. Refusée : un seul compte d'édition de Matomo
   deviendrait une porte vers l'administration de GLPI.
2. **Exclure seulement les pages d'administration.** Refusée : un administrateur qui navigue sous un autre profil peut
   revenir au profil d'administration depuis la même page.
3. **Exclure les pages d'administration et toute session qui détient un droit capable de faire monter quelqu'un en
   privilèges, dans n'importe quel profil de la session, ainsi que les sessions usurpées ; échouer fermé.** Retenue.

## Décision

L'option 3 : le serveur ne pose aucune balise et le chargeur ne charge rien lorsque la page est une page d'administration
(motif `PRIVILEGED_PATH_PATTERN`), lorsque la session peut atteindre un droit de `PRIVILEGED_RIGHTS` (configuration,
profils, utilisateurs, authentification, usurpation, règles d'affectation, notifications), lorsque la session est une
usurpation d'identité, ou lorsque le contrôle d'un droit échoue.

## Conséquences

- Les administrateurs n'apparaissent jamais dans Matomo : c'est le prix de la parade, à expliquer aux utilisateurs de
  Matomo.
- La liste des chemins et des droits est une liste d'exclusion à tenir à jour à chaque montée de version de GLPI (nouvelles
  routes d'administration). Le test `testLoaderUsesTheSamePatterns` garde le chargeur aligné sur le serveur.
- Le risque résiduel pour les utilisateurs non privilégiés reste : voir [SECURITE.md](../SECURITE.md#écarts-connus).
