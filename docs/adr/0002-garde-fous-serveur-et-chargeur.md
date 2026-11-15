# 0002 — Garde-fous doublés : serveur d'abord, chargeur en seconde couche

- Statut : accepté
- Date : durcissement des 9 et 25 octobre 2026 (versions 1.0.5 et 1.0.6, voir CHANGELOG.md) ; consigné le 2026-11-15

## Contexte

Un conteneur MTM est du code tiers mutable exécuté dans l'origine GLPI. Il ne doit tourner ni pour
un administrateur, ni sur une page d'administration, ni sur une adresse qui porte un secret
(jeton de réinitialisation, d'API, de CSRF, code d'autorisation). Un administrateur peut aussi
changer de profil depuis la même page, ou revenir d'une session usurpée.

## Options

1. Ne contrôler que dans le chargeur JavaScript.
2. Ne contrôler que côté serveur.
3. Contrôler côté serveur (décisif : les balises ne sont pas émises) **et** refaire les contrôles de
   chemin, de secret et de forme dans le chargeur.

## Décision

Option 3, avec **échec fermé** : une vérification de droit qui lève une exception vaut « privilégié ».
Le serveur regarde tous les profils de la session, l'usurpation et le référent. Les deux motifs
(paramètres secrets, chemins privilégiés) sont copiés à l'identique en PHP et en JavaScript ; un test
échoue s'ils divergent.

## Conséquences

- Un défaut d'un seul côté ne suffit pas à exposer une session privilégiée.
- Le chargeur n'a que l'adresse du navigateur pour juger : il ne connaît pas les droits, d'où la
  nécessité de la décision serveur.
- Les motifs sont à tenir en double ; la liste des chemins d'administration est à relire à chaque
  version majeure de GLPI.
- Les utilisateurs privilégiés ne sont jamais mesurés, ce qui biaise la statistique : accepté.
