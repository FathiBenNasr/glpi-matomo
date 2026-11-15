# Sécurité — Matomo Tag Manager

Référentiels : OWASP Top 10 (2021), OWASP ASVS niveau 2, RGPD et loi tunisienne 2004-63. Pour
signaler une faille : [../SECURITY.md](../SECURITY.md).

## Le risque principal : du code tiers dans l'origine GLPI

Un conteneur Matomo Tag Manager est du **JavaScript que ses publieurs peuvent modifier à tout
moment**, servi par un hôte tiers, et il s'exécute avec les privilèges de l'origine GLPI : il peut
lire la page, agir au nom de l'utilisateur et en voler la session visible du script. Quiconque peut
publier dans le conteneur, ou compromet l'hôte Matomo, agit comme n'importe quel utilisateur dont la
page le charge. Le greffon réduit ce risque ; il ne le supprime pas.

**Ce que le greffon fait :**

- ne charge **jamais** le conteneur pour une session qui détient, dans l'un quelconque de ses profils,
  un droit d'administration (liste dans « Menaces et mesures »), ni sur une page d'administration, ni
  pendant une usurpation de session (*Impersonate*) ;
- ne le charge jamais sur une adresse (ni après un référent) qui porte un secret : le traceur enregistre
  l'adresse complète, et un jeton de réinitialisation envoyé à Matomo permettrait à quiconque y lit de
  prendre le compte ;
- n'accepte qu'une URL de conteneur HTTPS de forme stricte, à l'enregistrement **et** à l'émission ;
- transmet les réglages comme données (balises `<meta>` échappées), jamais comme code ;
- **échoue fermé** : une vérification de droit qui lève une exception vaut « privilégié » ; sans sel,
  aucun pseudonyme, et jamais de repli sur l'identifiant ;
- refait les contrôles de chemin, de secret et de forme dans le chargeur (seconde couche).

**Ce que le greffon ne fait pas** : il ne contrôle pas le contenu du conteneur, n'épingle pas de
condensat (SRI : impossible, le conteneur change) et ne recueille aucun consentement. À la charge de
l'organisme : restreindre le droit *Publier* du conteneur à son propriétaire, poser une politique de
sécurité de contenu (voir [ADMINISTRATION.md](ADMINISTRATION.md#configuration)), héberger Matomo soi-même
si possible. Un traceur statique épinglé par SRI supprimerait le risque, au prix de la souplesse de
Tag Manager.

## Points d'entrée et authentification

Le greffon n'a **aucun point d'entrée anonyme** ni aucune route AJAX. Il enregistre seulement des
crochets de rendu et une page de réglages.

| Point d'entrée | Méthode | Authentification | Autorisation | Contrôles |
|---|---|---|---|---|
| `front/config.php` (affichage) | GET | session GLPI | droit `config` en mise à jour | `Session::checkRight('config', UPDATE)` avant tout rendu ; la valeur de l'URL est échappée (`htmlspecialchars`) dans le champ |
| `front/config.php` (enregistrement) | POST | session + jeton CSRF (écouteur de GLPI 12) | droit `config` en mise à jour, revérifié dans `saveConfig()` | URL : HTTPS, forme `container_<id>.js`, sans identifiant, requête, fragment, espace ni guillemet ; mode d'identité en liste blanche ; case anonyme strictement `0`/`1` |
| Crochets `add_header_tag`, `add_javascript` | rendu de page | session (pages connectées) | aucun droit requis : s'applique aux utilisateurs **sans** privilège | décision serveur : secret, usurpation, chemins et droits privilégiés ; balises émises seulement si l'URL est valide |
| Crochets `…_anonymous_page` | rendu de page | aucune (écran de connexion…) | option `track_anonymous` explicite | mêmes garde-fous ; aucune identité ; jamais pour un utilisateur privilégié ouvrant une page anonyme |
| `public/js/mtm-loader.js` | GET statique | aucune (fichier public, sans secret) | sans objet | refuse chemin privilégié, secret dans l'adresse ou le référent, conteneur de forme invalide |

## Données personnelles

Le greffon ne stocke aucune donnée d'utilisateur. Il en **fait sortir** vers Matomo, par le navigateur
et selon la configuration du conteneur :

| Donnée | Finalité | Base légale | Durée | Destinataires |
|---|---|---|---|---|
| Adresse des pages vues, référent, adresse IP, agent utilisateur du navigateur (collectés par Matomo) | mesure d'audience de GLPI | à décider par l'organisme (intérêt légitime ou consentement) — à vérifier avec le DPO | fixée par la rétention de Matomo, hors du greffon — à vérifier | l'instance Matomo de l'organisme |
| Pseudonyme `glpiUserId` (mode **Pseudonym**) | relier les visites d'une même personne sans révéler son compte | idem ; **reste une donnée personnelle** (RGPD, art. 4 §5) | idem | idem |
| Identifiant GLPI en clair (mode **GLPI login**) | idem, identification directe | idem, justification renforcée | idem | idem |
| Sel du pseudonyme | clé de l'HMAC | sans objet (donnée technique, restée sur le serveur) | jusqu'à la désinstallation | aucun (chiffré avec la clé de GLPI) |

Obligations de l'organisme : informer les utilisateurs et mettre à jour la notice de confidentialité
**avant** d'activer un mode d'identité ; recueillir un consentement si la base légale l'exige (le
greffon n'en fournit pas) ; anonymiser les adresses IP, respecter *Do Not Track* ou offrir une
désinscription côté Matomo ; accomplir les formalités auprès de l'autorité tunisienne de protection
des données personnelles que la loi 2004-63 impose, le cas échéant (à vérifier avec le DPO). La
mesure des pages anonymes est désactivée par défaut et n'envoie jamais d'identité. Le journal de GLPI
n'enregistre rien de ce flux.

## Menaces et mesures

| Menace (OWASP / ASVS) | Mesure | Test qui le prouve |
|---|---|---|
| Code tiers exécuté pour un administrateur (A08, V5, V14) | `isPrivilegedContext()` : chemins d'administration et droits `config`, `profile`, `user` (UPDATE, UPDATEAUTHENT, IMPERSONATE), `rule_ldap`, `rule_import`, `notification` dans **tous** les profils de la session | `testPrivilegedSessionGetsNoContainer`, `testAdministratorUnderSelfServiceProfileGetsNoContainer`, `testAdministrationPagesGetNoContainer`, `testPrivilegedPathsAreRecognised` |
| Passage d'un profil restreint au profil d'administration depuis la même page | tous les profils de `glpiprofiles` comptent | `testAdministratorUnderSelfServiceProfileGetsNoContainer` |
| Retour d'une session usurpée vers la session d'origine | aucun conteneur pendant l'usurpation | `testImpersonatedSessionGetsNoContainer` |
| Page anonyme ouverte par un administrateur connecté | même garde sur les crochets anonymes | `testAnonymousPageOpenedByAnAdministratorGetsNoContainer` |
| Jeton de réinitialisation ou d'API envoyé à Matomo (A01, A07, V3) | secret dans l'adresse ou le référent : rien n'est injecté, serveur et chargeur | `testNoTrackingOnPasswordResetUrl`, `testNoTrackingWhenTheReferrerCarriesASecret`, `testUrlCarryingASecretIsDetected`, `loader.test.js` (adresse, référent) |
| Vérification de droit défaillante (échec ouvert) | exception = privilégié | `testPrivilegedSessionsFailClosed`, `testFailingRightCheckInjectsNothing` |
| URL de conteneur hostile : HTTP, script arbitraire, injection de balise (A03, A10, V5) | forme stricte à l'enregistrement et à l'émission ; échappement dans le formulaire et par GLPI | `testHttpUrlIsRejectedAndNotStored`, `testNonHttpsSchemesAreRejected`, `testNonHttpsStoredUrlIsNeverEmitted`, `testConfigFormEscapesTheStoredUrl`, `testContainerUrlHostAllowlist`, `loader.test.js` (non-HTTPS refusé) |
| Modification des réglages sans droit ou par CSRF (A01, V4) | `checkRight('config', UPDATE)` dans la page et dans `saveConfig()` ; CSRF par l'écouteur de GLPI 12 | `testSavingRequiresTheConfigurationRight`, `testNoObsoleteCsrfCompliantHook` |
| Valeur de mode ou d'option forgée | liste blanche ; booléen strict ; valeur stockée corrompue retombe sur `none` | `testUnknownUserIdModeIsRejected`, `testTrackAnonymousIsStrictlyBoolean`, `testCorruptStoredModeFallsBackToNone` |
| Identité diffusée par défaut ou sur page anonyme (RGPD) | mode `none` par défaut ; aucune identité anonyme ; anonyme désactivé par défaut | `testNoIdentityByDefault`, `testAnonymousSessionHasNoIdentity`, `testAnonymousPagesAreNotTrackedByDefault` |
| Pseudonyme réversible ou lié à la clé maîtresse (A02, V6) | HMAC-SHA256 avec un sel propre, chiffré par `GLPIKey` ; pas de repli sur l'identifiant | `testPseudonymRevealsNeitherIdNorLogin`, `testPseudonymDoesNotUseGlpiEncryptionKey`, `testPseudonymWithoutKeySendsNothing`, `testUnreadableSaltGivesNoSecret` |
| Les deux motifs PHP et JavaScript divergent | motifs copiés à l'identique | `testLoaderUsesTheSamePatterns` |

Les tests sont dans `tests/ConfigTest.php`, `tests/SetupTest.php` et `tests/loader.test.js`
(`php tests/run.php` ; `node --test tests/*.test.js`).

## Secrets

Aucun secret dans le dépôt ni dans le code. Le seul secret est le sel du pseudonyme : généré par
`random_bytes(32)` à l'installation, stocké chiffré par `GLPIKey` (la clé de GLPI), jamais affiché ni
journalisé. L'URL du conteneur n'est pas un secret mais elle est publique côté navigateur. Aucun
jeton n'est lu ni écrit par le greffon.

## Écarts connus

- **Pas de consentement intégré** : le greffon mesure dès qu'une URL est saisie ; recueillir le
  consentement relève de l'organisme (bandeau, notice).
- **Pas de SRI** sur le conteneur : le contenu en est mutable par nature.
- **Contenu du conteneur non contrôlé** : un conteneur piégé peut agir pour les utilisateurs non
  privilégiés dont il charge la page.
- **Liste de chemins privilégiés** à relire à chaque version majeure de GLPI.
- **Pas de mesure des utilisateurs privilégiés** : choix de sécurité, qui biaise la statistique.
- **Rétention et IP** : relèvent de la configuration de Matomo, hors de ce greffon.

## Signalement

Voir [../SECURITY.md](../SECURITY.md) : contact@convergent.tn, objet « Sécurité — glpi-matomo ».
