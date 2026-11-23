# Sécurité — Matomo Tag Manager pour GLPI

Référentiels : OWASP Top 10 et ASVS niveau 2 ; RGPD et loi tunisienne 2004-63 (identité des utilisateurs envoyée à Matomo).

## Points d'entrée et authentification

| Point d'entrée | Méthode | Authentification | Autorisation | Contrôles |
|---|---|---|---|---|
| `front/config.php` (affichage) | GET | session GLPI | `config` en mise à jour (`Session::checkRight`) | valeurs affichées échappées (`htmlspecialchars`) |
| `front/config.php` (enregistrement) | POST | session GLPI | `config` en mise à jour, revérifié dans `saveConfig` | jeton CSRF du cœur de GLPI 11 (formulaire fermé par `Html::closeForm`) ; URL HTTPS de la forme d'un conteneur ; mode d'identité en liste blanche ; case booléenne stricte |
| Crochets `add_header_tag`, `add_javascript` (pages connectées) | tout, à chaque requête | session GLPI | aucun conteneur pour un droit privilégié de n'importe quel profil, une page d'administration ou une session usurpée | URL et référent sans secret ; échec fermé |
| Crochets `…_anonymous_page` (écran de connexion…) | tout | aucune (anonyme, délibéré) | désactivé par défaut (`track_anonymous`) ; même garde de contexte privilégié | aucune identité envoyée ; URL et référent sans secret |
| `public/js/mtm-loader.js` | GET statique | aucune (fichier public, sans donnée) | — | refait les contrôles côté navigateur ; refuse toute URL de conteneur hors forme |
| Chargement du conteneur | GET sortant du navigateur | aucune | — | l'hôte est celui que l'administrateur a réglé ; CSP conseillée |

Le greffon ne reçoit aucun paramètre d'URL ni d'API : il ne lit que les réglages et le contexte de la session.

## Données personnelles

| Donnée | Finalité | Base légale | Durée de conservation | Destinataires |
|---|---|---|---|---|
| Pseudonyme (HMAC de l'identifiant GLPI), si activé | suivre l'usage par personne dans Matomo | à définir par l'exploitant (intérêt légitime ou consentement) ; reste une donnée personnelle | celle de Matomo | l'hôte Matomo réglé |
| Identifiant GLPI (login) en clair, si activé | idem | idem, avec une justification plus forte | celle de Matomo | l'hôte Matomo réglé |
| Adresse de la page, référent, titre de page, adresse IP, agent utilisateur (collectés par le suivi, pas par le greffon) | mesure d'audience | idem | celle de Matomo | l'hôte Matomo réglé |
| Sel du pseudonyme | clé du HMAC | — (non personnel) | jusqu'à la désinstallation | stocké chiffré dans la base de GLPI |

Aucune donnée personnelle n'est écrite par le greffon dans un journal ni dans un fichier. L'exploitant informe les
utilisateurs, met à jour sa notice de vie privée, et règle l'anonymisation des adresses IP dans Matomo.

## Menaces et mesures

| Menace (OWASP Top 10) | Mesure en place | Exigence ASVS | Test qui le prouve |
|---|---|---|---|
| A08 Code tiers exécuté dans l'origine GLPI sous une identité administrateur | aucun conteneur pour un droit privilégié, en n'importe quel profil de la session | V1, V14 | `testPrivilegedSessionGetsNoContainer`, `testAdministratorUnderSelfServiceProfileGetsNoContainer` |
| A08 idem, par une page d'administration | chemins d'administration exclus, comparés après décodage | V4 | `testPrivilegedPathsAreRecognised`, `testAdministrationPagesGetNoContainer` |
| A08 idem, par une session usurpée | aucun conteneur pendant une usurpation | V4 | `testImpersonatedSessionGetsNoContainer` |
| A01 Contrôle d'accès : un contrôle de droit qui échoue | échec fermé : vaut « privilégié » | V4 | `testPrivilegedSessionsFailClosed`, `testFailingRightCheckInjectsNothing` |
| A01 Page anonyme ouverte par un administrateur connecté | même garde sur les crochets anonymes | V4 | `testAnonymousPageOpenedByAnAdministratorGetsNoContainer` |
| A02 Fuite d'un jeton (réinitialisation, API, CSRF, SSO) vers le journal de Matomo | aucune balise sur une URL ou un référent qui porte un secret | V3, V8 | `testUrlCarryingASecretIsDetected`, `testNoTrackingOnPasswordResetUrl`, `testNoTrackingWhenTheReferrerCarriesASecret` |
| A02 Pseudonyme réversible ou clé maîtresse réutilisée | HMAC-SHA256 avec le sel propre du greffon, chiffré ; sans sel aucun pseudonyme | V6 | `testPseudonymDoesNotUseGlpiEncryptionKey`, `testPseudonymWithoutSaltSendsNoIdentity`, `testPseudonymRevealsNeitherIdNorLogin`, `testPseudonymSaltIsOwnSealedAndStable` |
| A03 Injection par l'URL du conteneur | URL HTTPS de la forme attendue, caractères dangereux refusés, à l'enregistrement puis à l'émission | V5 | `testContainerUrlHostAllowlist`, `testHttpUrlIsRejectedAndNotStored`, `testNonHttpsSchemesAreRejected`, `testNonHttpsStoredUrlIsNeverEmitted` |
| A03 XSS par un réglage affiché | balises `<meta>` échappées par GLPI ; formulaire échappé | V5 | `testConfigFormEscapesTheStoredUrl` |
| A05 Mode d'identité inconnu ou corrompu | liste blanche, repli sur « ne pas envoyer » | V5 | `testUnknownUserIdModeIsRejected`, `testCorruptStoredModeFallsBackToNone` |
| A04 Suivi de l'écran de connexion activé sans le vouloir | désactivé par défaut | V8 | `testAnonymousPagesAreNotTrackedByDefault`, `testAnonymousTrackingIsOffWhenKeyMissing` |
| A01 Réglage par un non-administrateur | `config` en mise à jour | V4 | `testSavingRequiresTheConfigurationRight` |
| Dérive entre serveur et chargeur | motifs identiques mot pour mot | V14 | `testLoaderUsesTheSamePatterns` |
| Chargeur : page privilégiée, secret, référent | le chargeur refuse aussi | V14 | tests du chargeur `injects nothing on a privileged page, even if the tags reached it`, `injects nothing when the URL carries a secret`, `injects nothing when the referrer carries a secret`, `refuses a non-https container even if one reached the page` (`tests/loader.test.js`) |
| A09 Journaux | le greffon n'écrit aucun journal et ne consigne aucun secret | V7 | — (absence de code de journalisation) |

## Secrets

Aucun secret dans le dépôt. Le seul secret du greffon est le sel du pseudonyme : aléatoire (`random_bytes(32)`), créé à
l'installation, stocké chiffré par la clé de GLPI, jamais envoyé au navigateur. L'URL du conteneur n'est pas un secret.

## Écarts connus

- **Le conteneur reste du code tiers pour tous les utilisateurs non privilégiés** : qui le publie, ou compromet l'hôte
  Matomo, agit comme ces utilisateurs (par exemple lit leurs tickets). Le greffon réduit la surface mais ne la supprime pas ;
  à la charge de l'exploitant : droit de publication restreint, politique CSP, ou suivi par un script statique épinglé
  par somme d'intégrité (SRI). Aucune CSP n'est posée par le greffon.
- **Liste d'exclusion** : les chemins et droits privilégiés sont énumérés. Une nouvelle route d'administration de GLPI absente
  de `PRIVILEGED_PATH_PATTERN` reste suivie pour un utilisateur sans droit privilégié (un administrateur, lui, est exclu
  par ses droits). À revoir à chaque montée de version de GLPI.
- **Pas de rotation du sel** : `ensurePseudonymSalt()` ne le crée qu'une fois ; aucune interface ne le renouvelle (il suffit
  de supprimer la clé puis de réinstaller : à vérifier), ce qui change tous les pseudonymes.
- **Pas de trace d'audit des changements de réglage** : `saveConfig` ne consigne rien dans le journal d'événements de GLPI
  (aucun appel de journalisation dans `src/` ni `front/`) ; seul l'historique du cœur, s'il existe, en garde la trace
  (à vérifier).
- **Le jeton CSRF de l'enregistrement repose sur le cœur de GLPI 11** et n'est pas éprouvé par un test de bout en bout
  dans ce dépôt (les tests bouchonnent GLPI).
- **Titres de pages et adresses collectés par le suivi** : le suivi de Matomo peut envoyer le titre de la page (nom d'un
  ticket, d'une personne) ; ce que Matomo collecte exactement dépend de la configuration du conteneur (à vérifier côté
  Matomo).
- **Identifiant en clair** (mode `login`) : donnée personnelle, option à réserver à un besoin documenté.
- **Les affichages du formulaire** concatènent `__()` et `$webdir` sans échappement (`showConfigForm`) : les chaînes de
  traduction et `root_doc` viennent de GLPI et ne sont pas contrôlables par un utilisateur ; correctif possible, à faire
  avec son test.
- **Aucune politique anti-clic ni CSP** n'est posée par le greffon.

## Signalement

Voir [SECURITY.md](../SECURITY.md).
