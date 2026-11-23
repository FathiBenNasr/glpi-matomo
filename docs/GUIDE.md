# Guide d'utilisation — Matomo Tag Manager pour GLPI

**Français** · [English](GUIDE.en.md)

Pour l'administrateur de GLPI qui veut suivre l'usage de son instance dans Matomo. Il faut le droit de configuration de GLPI
(`config` en mise à jour).

## Régler le conteneur

![Page de réglages du greffon : champ de l'URL du conteneur rempli avec une adresse d'exemple, case du suivi de l'écran de connexion cochée, liste d'identité réglée sur Pseudonyme, et bouton Enregistrer](captures/01-settings.png)

1. Ouvrir **Configuration → Plugins**, puis le lien de configuration de **Matomo Tag Manager**.
2. Dans **Container URL**, coller l'adresse du conteneur, par exemple
   `https://stats.example.com/js/container_XXXXXXXX.js`.
3. Cliquer sur **Enregistrer**.

Résultat attendu : le message « Configuration saved » ; le conteneur est chargé sur la page suivante d'un utilisateur
connecté sans droit d'administration. Une adresse qui n'est pas en HTTPS, ou qui porte un identifiant, une requête ou un
fragment, est refusée et rien n'est enregistré.

## Suivre aussi l'écran de connexion

1. Sur la page de réglages, cocher **Also track the login screen and other anonymous pages**.
2. Cliquer sur **Enregistrer**.

Résultat attendu : le conteneur se charge aussi sur l'écran de connexion. Aucune identité n'y est envoyée, et une page dont
l'adresse porte un jeton (lien de réinitialisation de mot de passe) n'est jamais suivie. Décocher la case rétablit le
comportement par défaut.

## Envoyer l'identité de l'utilisateur à Matomo

1. Avant de commencer : avoir une base légale, informer les utilisateurs et mettre à jour la notice de vie privée.
2. Sur la page de réglages, choisir dans **Identity of the logged-in user** : **Pseudonym (stable, not reversible)**
   (recommandé) ou **GLPI login (personal data)**.
3. Cliquer sur **Enregistrer**.
4. Dans Matomo Tag Manager, créer une variable de couche de données `glpiUserId`, puis la donner comme **User ID** à la
   variable *Matomo Configuration*.

Résultat attendu : chaque page d'un utilisateur connecté pousse `glpiUserId` avant le démarrage de MTM. Choisir **Do not
send** arrête l'envoi.

## Vérifier que le suivi fonctionne

1. Ouvrir une session avec un compte **sans** droit d'administration.
2. Afficher une page quelconque (par exemple l'accueil) et ouvrir les outils du navigateur.
3. Dans la console, saisir `window._mtm` : le tableau doit contenir l'événement `mtm.Start` (et `glpiUserId` si une
   identité est choisie).

Résultat attendu : le conteneur est chargé. Avec un compte administrateur, ou sur une page de configuration, `window._mtm`
reste indéfini : c'est voulu (voir [SECURITE.md](SECURITE.md)).
