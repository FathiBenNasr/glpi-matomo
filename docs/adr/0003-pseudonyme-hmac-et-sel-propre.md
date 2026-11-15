# 0003 — Pseudonyme par HMAC-SHA256 avec un sel propre au greffon

- Statut : accepté
- Date : version 1.0.5, 2026-10-09 (voir CHANGELOG.md) ; consigné le 2026-11-15

## Contexte

Pour suivre les visites d'une même personne sans livrer son identifiant à Matomo, il faut un
pseudonyme stable. Un condensat nu de l'identifiant numérique se retrouve par énumération
(les identifiants GLPI sont des entiers courts). Utiliser la clé maîtresse de GLPI comme clé du
pseudonyme réutiliserait pour un autre usage la clé qui protège tous les identifiants stockés.

## Options

1. Condensat simple (SHA-256) de l'identifiant.
2. HMAC-SHA256 avec la clé de chiffrement de GLPI.
3. HMAC-SHA256 avec un **sel aléatoire propre au greffon**, créé à l'installation et stocké chiffré
   par `GLPIKey`.

## Décision

Option 3. Pseudonyme = les 32 premiers caractères hexadécimaux de
`HMAC-SHA256("glpi-matomo-uid:" + identifiant, sel)`. Sans sel lisible, **aucun pseudonyme** n'est
produit, et le greffon ne retombe jamais sur l'identifiant.

## Conséquences

- Stable entre sessions, non réversible sans le sel, sans signification hors de l'instance.
- Une montée de version vers 1.0.5 impose la réinstallation (qui crée le sel) ; les pseudonymes
  d'avant changent une fois.
- Désinstaller supprime le sel : une réinstallation produit d'autres pseudonymes.
- Si la clé de GLPI change, le sel devient illisible et le pseudonyme s'arrête (échec fermé).
- Le pseudonyme reste une donnée personnelle au sens du RGPD : voir [SECURITE.md](../SECURITE.md).
