# Publier Pathly for Joomla sur le JED (étapes pour Simon)

Le JED (Joomla Extensions Directory, https://extensions.joomla.org) ne garde qu'une fiche : le fichier est téléchargé depuis notre lien, et une copie est déposée pour la revue. Rien n'a été créé ni soumis : tout ce qui suit est à faire par Simon.

## Avant de commencer

1. **Rendre public le dépôt** `Pathly-Monitoring/pathly-joomla` : le lien de téléchargement, la documentation et le serveur de mises à jour (`updates/pkg_pathly.xml`, lu sur `raw.githubusercontent.com`) doivent répondre sans compte.
2. **Publier la version 0.1.0** : fusionner la PR « chore(main): release 0.1.0 » ouverte par release-please. Le workflow « Release » joint `pkg_pathly-0.1.0.zip` à la version GitHub.
3. Vérifier que ces liens répondent :
   - https://github.com/Pathly-Monitoring/pathly-joomla/releases/download/v0.1.0/pkg_pathly-0.1.0.zip
   - https://raw.githubusercontent.com/Pathly-Monitoring/pathly-joomla/main/updates/pkg_pathly.xml
4. Option recommandée : une page `https://pathlyhq.com/joomla` (présentation et lien de téléchargement). Le JED refuse les liens vers des sites de partage de fichiers. Une page de versions GitHub est en principe acceptée (non vérifié) : une page sur notre site évite la question.

## Compte

5. Créer le compte sur https://extensions.joomla.org avec **simon@pathlyhq.com** (bouton « Register »), puis confirmer l'adresse par le courriel reçu. Un seul compte, au nom de Simon.
6. Lire et accepter les conditions du JED (https://extensions.joomla.org/tos) : c'est à Simon de le faire.

## Soumission

7. « Profile » en haut du JED, puis **« Submit extension »**.
8. Remplir le formulaire avec `publication/fiche-en.md` :
   - nom : **Pathly for Joomla** (règle du JED : « X for Joomla », jamais « Joomla X » ni de numéro de version) ;
   - type : **Free**. Le JED accepte « Free » pour un service en ligne qui a une offre gratuite permanente : c'est le cas de l'offre Solo ;
   - licence : GPL-2.0-or-later ;
   - compatibilité : Joomla 4 et Joomla 5 ;
   - catégorie proposée : « Site Management » ou « Site Security » (au choix de Simon) ;
   - liens : site, téléchargement, documentation (README), support (`contact@pathlyhq.com`). Pas de lien de démo : il n'y a pas de site de démonstration public ;
   - images : `publication/assets/icon-512.png` (logo) et les captures de `publication/screenshots/`. La taille exacte demandée n'est pas documentée sur le JED (non vérifié) : redimensionner si le formulaire le demande ;
   - **déposer une copie** de `pkg_pathly-0.1.0.zip` (obligatoire quand l'extension est disponible ailleurs).
9. « Save » : la fiche passe « pending » et rejoint la file de revue.

## Revue

- Quatre contrôles dans l'ordre : soumission, marque, licence, installation et fonctionnement.
- Le JED Checker (outil officiel) passe sans erreur ni avertissement : contrôlé à chaque CI sur Joomla 4.4 et 5 (`tests/integration/run.sh`).
- Points déjà tenus : installation par l'installeur Joomla, garde `_JEXEC` en tête de chaque fichier PHP, serveur de mises à jour, aucun droit 777, aucune erreur après désinstallation.
- Délai : non publié par le JED (non vérifié). Compter plusieurs semaines.
- Frais : aucun frais de soumission annoncé (non vérifié sur le formulaire).
- Limite : 3 fiches en attente au plus par développeur.

## Après la publication

- Chaque nouvelle version : fusionner la PR de release-please. Le serveur de mises à jour est mis à jour dans la même PR : les sites installés voient la nouvelle version dans Joomla.
- Mettre à jour la version et le journal des changements sur la fiche du JED.
- Ne solliciter aucun avis (règle des comptes et des communautés de Pathly).

## Captures et visuels

| Fichier | Contenu |
|---|---|
| `screenshots/1-dashboard-connect.png` | Encart avant la clé : lien « Create a free account » ou clé |
| `screenshots/2-settings.png` | Réglages du plugin : clé enregistrée, jamais réaffichée, badge |
| `screenshots/3-dashboard-lumi.png` | Lumi a rejoué deux Flow, l'administrateur en enregistre un |
| `screenshots/4-dashboard-failure.png` | Ping OK, Flow en échec, dernier échec avec sa capture |
| `screenshots/5-badge.png` | Badge `{pathly-badge}` dans un article |
| `assets/icon-512.png`, `assets/logo.svg` | Icône |
| `assets/banner-1544x500.png` | Bannière |

Les captures sont faites sur le Joomla 5 de test en Docker, avec une API Pathly simulée (organisation « Studio Test », aucune donnée réelle). Pour les refaire : `KEEP=1 tests/integration/run.sh`, puis `node tests/integration/screenshots.mjs`.

## Sources (consultées le 02/11/2026)

- https://extensions.joomla.org/support/knowledgebase/for-jed-developers/submitting-an-extension
- https://extensions.joomla.org/support/knowledgebase/submission-requirements/jed-entries-checklists
