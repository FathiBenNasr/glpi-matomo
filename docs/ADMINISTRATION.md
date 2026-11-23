# Administration — Matomo Tag Manager pour GLPI

**Français** · [English](ADMINISTRATION.en.md)

Pour l'administrateur système de GLPI. Le dossier déployé s'appelle `matomo` (clé du greffon), quel que soit le nom du dépôt.

## Installation

Pré-requis : GLPI 11.x (`setup.php` refuse hors de 11.0.0 – 11.99.99), l'URL d'un conteneur Matomo Tag Manager.

1. Placer le greffon dans `<racine GLPI>/plugins/matomo/` (archive de la [page des versions](https://github.com/FathiBenNasr/glpi-matomo/releases),
   ou Git Plugin Installer). Le nom du dossier doit être `matomo`.
2. Rendre les fichiers lisibles par le serveur web ; sur un système avec SELinux, rétablir les contextes
   (`restorecon -R <racine GLPI>/plugins/matomo`), faute de quoi le serveur ne lit rien, sans message.
3. Installer, activer, vider le cache, **sous l'identité du serveur web** (jamais en `root`, qui crée des caches
   inaccessibles au serveur) :
   ```bash
   cd <racine GLPI>
   sudo -u apache php bin/console plugin:install matomo
   sudo -u apache php bin/console plugin:activate matomo
   sudo -u apache php bin/console cache:clear
   ```
4. Régler le greffon (voir le [guide](GUIDE.md)).

L'installation crée le sel du pseudonyme (`Config::ensurePseudonymSalt()`, appelé par `hook.php`). Aucune table n'est créée.

## Configuration

| Clé (contexte `plugin:matomo`) | Défaut | Effet |
|---|---|---|
| `container_url` | vide | URL HTTPS du conteneur ; vide = aucun suivi |
| `track_anonymous` | `0` | `1` : charge aussi le conteneur sur les pages anonymes |
| `user_id_mode` | `none` | `none`, `pseudonym` ou `login` ; toute autre valeur stockée retombe sur `none` |
| `pseudonym_salt` | créé à l'installation | sel aléatoire (64 caractères hexadécimaux), chiffré par la clé de GLPI ; ne se règle pas à la main |

Modification : page de réglages, réservée au droit `config` en mise à jour. Aucune option n'est lue depuis un fichier.

## Exploitation

- **Journaux** : le greffon n'écrit aucun journal. Les erreurs PHP vont dans les journaux de GLPI et du serveur web
  (emplacement selon l'installation : à vérifier).
- **Supervision** : le suivi se vérifie depuis un navigateur (voir « Vérifier que le suivi fonctionne » du
  [guide](GUIDE.md#vérifier-que-le-suivi-fonctionne)).
- **Sauvegarde** : les réglages sont dans la configuration de GLPI (contexte `plugin:matomo` de la table de
  configuration), donc dans la sauvegarde de la base. Sans la clé de chiffrement de GLPI, le sel n'est plus lisible et le
  pseudonyme n'est plus produit (les pseudonymes changent).
- **Politique CSP** conseillée sur l'hôte virtuel de GLPI, par exemple
  `Content-Security-Policy: script-src 'self' https://stats.example.com; object-src 'none'; base-uri 'self'`.
- **Matomo** : restreindre le droit de *publier* du conteneur, activer l'anonymisation des adresses IP, exclure les
  paramètres `password_forget_token`, `token` et `_glpi_csrf_token`.

## Mise à jour et désinstallation

**Mise à jour** : remplacer les fichiers, puis **réinstaller** :

```bash
sudo -u apache php bin/console plugin:install --force matomo
sudo -u apache php bin/console cache:clear
```

Sans réinstallation, GLPI voit une version de code différente de la version enregistrée, désactive le greffon, et toutes ses
pages répondent 404. Les versions 1.0.4 à 1.0.6 demandent expressément cette réinstallation (voir le
[CHANGELOG](../CHANGELOG.md)).

**Désinstallation** : `sudo -u apache php bin/console plugin:uninstall matomo`. Elle supprime les clés `container_url`,
`track_anonymous`, `user_id_mode` et `pseudonym_salt` (`hook.php`) ; supprimer ensuite le dossier du greffon.

## Dépannage

| Symptôme | Cause probable | Remède |
|---|---|---|
| Rien ne se charge pour un administrateur | Voulu : aucun conteneur pour un droit privilégié ou une page d'administration | Tester avec un compte sans droit d'administration |
| Rien ne se charge pour personne | `container_url` vide, greffon inactif, ou politique CSP qui bloque l'hôte Matomo | Vérifier les réglages, `plugin:activate matomo`, la console du navigateur |
| Rien pendant une session « Impersonate » | Voulu : le conteneur ne tourne jamais dans une session usurpée | Se reconnecter normalement |
| Rien sur une page précise | Son adresse ou son référent porte un secret (`*token*`, `*passw*`, `*secret*`, `code`, `state`) | Aucun : c'est la parade contre la fuite d'un jeton |
| URL refusée à l'enregistrement | Forme attendue : `https://hôte[:port]/…/container_<id>.js` | Corriger l'adresse |
| Pseudonyme absent | Sel absent ou illisible : le greffon n'envoie rien (échec fermé) | `plugin:install --force matomo` ; si le sel reste illisible, la clé de GLPI a changé |
| 404 sur les pages du greffon | GLPI a désactivé le greffon après une montée de version | Réinstaller (voir plus haut) puis `plugin:activate matomo` |
| Erreur 500 partout après un déploiement | Fichiers copiés avec de mauvais droits ou contextes SELinux | Droits du serveur web, `restorecon -R` ; consulter les journaux |
