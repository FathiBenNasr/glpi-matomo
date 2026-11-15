# Administration — Matomo Tag Manager

**Français** · [English](ADMINISTRATION.en.md)

Ce document s'adresse à l'administrateur de GLPI : installer le greffon, le régler, l'exploiter, le
mettre à jour, le dépanner. Les tâches de réglage pas à pas sont dans [GUIDE.md](GUIDE.md) ; les
risques dans [SECURITE.md](SECURITE.md).

## Installation

### Où vit le greffon

Le greffon existe en trois endroits, qui divergent normalement :

| Emplacement | Rôle |
|---|---|
| Source : dépôt `glpi-matomo` (GitHub, <https://github.com/FathiBenNasr/glpi-matomo>) | le code, les tests, la documentation |
| Déploiement : `<GLPI>/plugins/matomo/` | ce que GLPI exécute |
| Archive de version | ce que l'on télécharge pour installer |

**Le nom du dossier déployé est `matomo`**, différent de celui du dépôt (`glpi-matomo`) : un autre
nom empêche GLPI de reconnaître le greffon. Les dossiers `tests/` et `docs/` n'ont pas à être
déployés (ils sont exclus de l'archive par `.gitattributes`).

### Procédure

Prérequis : GLPI 11.x, PHP 8.2 ou plus, une URL de conteneur Matomo Tag Manager (facultative à
l'installation).

1. Placez le greffon dans `<GLPI>/plugins/matomo/` (archive de version, ou **Git Plugin Installer**).
2. Rétablissez les contextes SELinux si le serveur les applique (`restorecon -R <GLPI>/plugins/matomo`).
3. Installez et activez en tant qu'utilisateur du serveur web — **jamais en root**, qui créerait des
   caches illisibles pour Apache :

   ```bash
   cd <GLPI>
   sudo -u apache php bin/console plugin:install matomo
   sudo -u apache php bin/console plugin:activate matomo
   sudo -u apache php bin/console cache:clear
   ```

4. Vérifiez : le greffon est **Activé** dans **Configuration → Plugins**, et sa page de réglages s'ouvre.

L'installation ne crée **aucune table** : elle génère seulement le sel du pseudonyme (voir plus bas).

## Configuration

La page de réglages est `front/config.php`, ouverte depuis **Configuration → Plugins → Matomo Tag
Manager**. Elle exige le droit `config` en mise à jour (profil super-administrateur en général) ; le
greffon n'enregistre **aucun droit propre**.

| Réglage | Clé stockée | Défaut | Valeurs et effet |
|---|---|---|---|
| **Container URL** | `container_url` | vide | `https://hôte[/chemin]/container_<id>.js` ; l'identifiant est composé de lettres, chiffres et `_`. Vide = aucune mesure. Refusée : HTTP, identifiant ou mot de passe dans l'URL, requête, fragment, espaces ou guillemets, segment commençant par un point. |
| **Also track the login screen and other anonymous pages** | `track_anonymous` | `0` | `1` charge le conteneur sur l'écran de connexion et les pages anonymes (sans identité, sans jeton dans l'adresse). |
| **Identity of the logged-in user** | `user_id_mode` | `none` | `none` · `pseudonym` (HMAC-SHA256 de l'identifiant, tronqué à 32 caractères hexadécimaux, clé = sel du greffon) · `login` (identifiant GLPI en clair). Une valeur inconnue retombe sur `none`. |
| (interne) sel du pseudonyme | `pseudonym_salt` | créé à l'installation | 64 caractères hexadécimaux aléatoires, **chiffrés avec la clé de GLPI** ; jamais affiché. |

Les réglages sont écrits dans la configuration du cœur, contexte `plugin:matomo` (table `glpi_configs`
de GLPI, que le greffon ne manipule que par l'API du cœur). Les libellés de la page sont en anglais.

**En-tête de sécurité recommandé** sur l'hôte virtuel de GLPI, pour borner le code tiers :

```text
Content-Security-Policy: script-src 'self' https://stats.example.com; object-src 'none'; base-uri 'self'
```

Côté Matomo : restreindre le droit *Publier* du conteneur à son propriétaire, anonymiser les adresses
IP, respecter *Do Not Track* ou proposer une désinscription, et exclure les paramètres
`password_forget_token`, `token` et `_glpi_csrf_token` dans les réglages du site.

## Exploitation

- **Journaux** : le greffon n'écrit aucun journal ni aucun fichier. Les erreurs PHP vont dans les
  journaux de GLPI (`files/_log/php-errors.log` sous le dossier de données de GLPI, ou l'emplacement
  configuré par votre installation — à vérifier).
- **Supervision** : rien à superviser côté GLPI. Côté navigateur, la console doit montrer le script du
  conteneur chargé sur une page d'utilisateur sans droit d'administration, et rien sur une page
  d'administration. La disponibilité de Matomo est indépendante : si Matomo est injoignable, le script
  asynchrone échoue sans bloquer la page.
- **Sauvegarde** : rien de propre au greffon. Les réglages et le sel suivent la sauvegarde de la base
  de GLPI ; le sel est chiffré avec la clé de GLPI, qu'il faut donc sauvegarder aussi. Sans cette clé,
  le pseudonyme n'est plus produit (échec fermé).
- **Droits des fichiers** : le greffon n'écrit nulle part à l'exécution ; lecture seule suffit pour
  l'utilisateur du serveur web.

## Mise à jour et désinstallation

**Mise à jour.** Remplacez les fichiers de `plugins/matomo/` par ceux de la nouvelle version, puis
**réinstallez** — surtout si la version change : un décalage entre le code et l'état enregistré fait
désactiver le greffon par GLPI, et ses pages tombent en 404.

```bash
cd <GLPI>
sudo -u apache php bin/console plugin:install --force matomo
sudo -u apache php bin/console cache:clear
```

Les versions 1.0.5 et 1.1.3 (et suivantes) exigent cette réinstallation : elle crée le sel du
pseudonyme. Les pseudonymes produits par 1.0.3 / 1.1.1 changent une fois. Le détail de chaque version
est dans [CHANGELOG.md](../CHANGELOG.md).

**Désinstallation.** `sudo -u apache php bin/console plugin:uninstall matomo` supprime les réglages
(`container_url`, `track_anonymous`, `user_id_mode`, sel du pseudonyme). Le sel étant supprimé, une
réinstallation ultérieure produira d'autres pseudonymes. Pour une simple suspension, videz **Container
URL** au lieu de désinstaller.

## Dépannage

| Symptôme | Cause probable | Remède |
|---|---|---|
| Rien n'est chargé sur une page d'utilisateur | URL vide ; greffon inactif ; la page est exclue ; l'adresse ou le référent porte un paramètre `token`, `passw`, `secret`, `code` ou `state` ; session usurpée (*Impersonate*) | Vérifier l'URL, l'état du greffon et le compte de test |
| Rien n'est chargé avec un compte « technicien » | Il détient, dans l'un de **ses profils** (même inactif), un droit d'administration listé dans [SECURITE.md](SECURITE.md#menaces-et-mesures) | Comportement voulu ; tester avec un profil sans ces droits |
| Une vérification de droit échoue (exception) | Échec fermé : le greffon traite la session comme privilégiée | Chercher l'erreur dans le journal PHP |
| « Container URL must be an https:// … » | Forme d'URL refusée | Coller l'adresse exacte du conteneur |
| « Unknown user identity mode » | Requête forgée ou formulaire altéré | Réenregistrer depuis la page |
| Pseudonyme absent en mode `pseudonym` | Sel absent ou illisible (clé GLPI changée) | `plugin:install --force matomo` |
| Pages du greffon en 404 après une montée de version | Code et base décalés ; GLPI a désactivé le greffon | Réinstaller (`--force`), réactiver |
| Rien ne change après une modification du JavaScript | Cache du navigateur ou de GLPI | `cache:clear` ; recharger sans cache |
| `glpiUserId` n'arrive pas dans Matomo | Variable *Data Layer* non créée ou non affectée au User ID | Voir [GUIDE.md](GUIDE.md#exploiter-lidentité-dans-matomo) |
| Violation de la CSP dans la console | L'hôte du conteneur n'est pas dans `script-src` | Ajouter l'hôte exact de Matomo |

Signalement et support : <https://github.com/FathiBenNasr/glpi-matomo/issues>, ou
contact@convergent.tn pour une faille ([SECURITY.md](../SECURITY.md)).
