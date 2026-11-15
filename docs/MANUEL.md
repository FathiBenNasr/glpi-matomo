# Matomo Tag Manager — manuel d'utilisation

Version déployée : **1.0.6**

## À quoi sert le greffon

Matomo Tag Manager relie GLPI à un service de mesure d'audience Matomo. Quand il est configuré, le navigateur des utilisateurs charge, à chaque page, un petit script (le « conteneur ») fourni par l'organisation, qui enregistre des statistiques de fréquentation de GLPI. Le greffon n'ajoute aucun écran ni aucun menu pour les techniciens et les gestionnaires : cette page explique ce qu'il mesure et ce que cela implique pour la vie privée.

## Où le trouver

Le greffon n'a **pas** de menu et n'ajoute **aucun onglet** aux fiches GLPI. Sa page de réglages est réservée aux personnes qui peuvent modifier la configuration de GLPI ; elle s'ouvre depuis la liste des plugins (Configuration → Plugins), par l'icône de configuration du plugin. Les libellés de cette page sont en anglais.

## Droits nécessaires

Aucun droit propre au greffon. Régler le greffon exige le droit de **modification de la configuration** de GLPI (droit « Configuration », niveau mise à jour). Les autres utilisateurs n'ont rien à faire : le greffon agit automatiquement dans leur navigateur.

## Ce que le greffon mesure

- **Rien tant qu'une adresse de conteneur n'est pas saisie.** Sans elle, aucune mesure n'a lieu.
- **Pages concernées** : les pages vues par les utilisateurs connectés (interface standard et interface simplifiée). La page de connexion et les autres pages publiques ne sont mesurées que si l'option **Also track the login screen and other anonymous pages** est cochée. Elle est décochée par défaut.
- **Identité de l'utilisateur** : le réglage **Identity of the logged-in user** a trois valeurs.

| Valeur | Effet |
|---|---|
| **Do not send** (défaut) | Aucune identité n'est transmise : les visites ne sont pas rattachées à une personne |
| **Pseudonym (stable, not reversible)** | Un code stable et non réversible est transmis à la place du nom : il permet de suivre les visites d'une même personne sans que Matomo connaisse son identifiant |
| **GLPI login (personal data)** | Le nom de connexion GLPI est transmis en clair |

Aucune identité n'est jamais transmise depuis les pages publiques.

## Vie privée : ce que cela implique

- La mesure d'audience est effectuée par un script chargé dans votre navigateur, fourni par le service Matomo de l'organisation. Matomo reçoit les adresses des pages consultées.
- Avec le pseudonyme ou le nom de connexion, les visites sont rattachées à une personne. Le pseudonyme reste une donnée personnelle ; le nom de connexion l'est plus encore.
- Le greffon limite lui-même la collecte. Le script **ne se charge pas** :
  - sur les pages d'administration (configuration, utilisateurs, profils, entités, authentification, notifications, règles, plugins, etc.) ;
  - pour une personne dont un profil permet de modifier la configuration, les profils ou les utilisateurs ;
  - sur une page dont l'adresse contient un secret (jeton, mot de passe, code d'authentification), ni sur une page atteinte depuis un tel lien (par exemple un lien de réinitialisation de mot de passe).
- Les utilisateurs concernés doivent être informés de cette mesure (notice de protection des données). Les choix d'identité relèvent de l'administrateur et de la direction : pour savoir lequel est actif, contactez le support.

## Les tâches, pas à pas

### Savoir si votre navigation est mesurée

1. Vous êtes connecté avec un profil qui n'a pas de droits d'administration.
2. Demandez au support si une adresse de conteneur est configurée et quel réglage d'identité est choisi.

Résultat : vous savez si vos pages GLPI sont mesurées et si Matomo peut vous identifier. Il n'existe pas de réglage individuel dans GLPI pour s'y opposer ; pour exercer vos droits, contactez le support.

### Modifier les réglages (administrateurs de configuration)

1. Ouvrez **Configuration → Plugins** et cliquez sur l'icône de configuration de « Matomo Tag Manager ».
2. Dans **Container URL**, saisissez l'adresse https du conteneur, de la forme `https://…/container_XXXXXXXX.js`. Une adresse qui n'a pas cette forme est refusée. Videz le champ pour arrêter toute mesure.
3. Cochez ou décochez **Also track the login screen and other anonymous pages**.
4. Choisissez l'**Identity of the logged-in user**.
5. Cliquez sur **Save**.

Résultat : « Configuration saved » s'affiche ; le changement s'applique au chargement suivant des pages.

## Ce que voient ou reçoivent les utilisateurs

Rien de visible : pas de courriel, de notification ni de ticket. La mesure se fait en arrière-plan dans le navigateur.

## Tâches automatiques

Aucune.

## Questions fréquentes et problèmes courants

| Symptôme | Que faire |
|---|---|
| Je ne trouve aucun écran Matomo dans GLPI | C'est normal : seul le réglage des plugins le propose, aux personnes qui gèrent la configuration. |
| Le message « Container URL must be an https:// Matomo Tag Manager container » apparaît | L'adresse n'a pas la forme exigée (https, finissant par `/container_….js`, sans identifiant, paramètre ni ancre). Corrigez-la ou contactez le support. |
| Les statistiques n'apparaissent pas dans Matomo | Contacter le support : la configuration du conteneur côté Matomo relève de l'administrateur. |
| Je suis administrateur et rien n'est mesuré pour moi | C'est voulu : le script ne se charge pas pour les profils d'administration ni sur les pages d'administration. |

## Support

contact@convergent.tn
