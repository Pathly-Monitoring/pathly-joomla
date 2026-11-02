# Pathly pour Joomla

_Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold._

Ce paquet relie un site Joomla 4.4 ou 5 à [Pathly](https://pathlyhq.com). English version: [README.md](README.md).

- **Ping** sur la page d'accueil du site, créé dès que la clé d'API est enregistrée. Si un Ping surveille déjà la même adresse, le paquet s'y rattache, sans doublon.
- **Flow préparé par Lumi**, l'assistant Flow de Pathly. Lumi reconnaît Joomla et a des modèles pour le formulaire de contact et la connexion. Chaque Flow proposé est rejoué une fois avant de vous être montré, et vous choisissez celui à garder en un clic.
- **Rejeu après une mise à jour** : quand Joomla ou une extension est mis à jour (ou qu'une version plus récente est installée à la main), Pathly relance le Flow, une seule fois, à la fin de la mise à jour.
- **Encart du tableau de bord** de l'administration : état du Ping et du Flow, date et capture du dernier échec, offre et quotas, liens vers Pathly.
- **Badge de disponibilité** pour les articles (`{pathly-badge}`), **éteint par défaut**.

## Prérequis

- Joomla 4.4 (PHP 7.4 ou plus) ou Joomla 5 (PHP 8.1 ou plus).
- Un compte Pathly. L'offre Solo est gratuite, sans limite de durée : [créer un compte](https://pathlyhq.com/fr/login?mode=signup&ref=joomla&utm_source=joomla&utm_medium=module).
- Un site joignable depuis Internet. Un site hors ligne, protégé par un mot de passe ou sur une adresse locale ne peut pas être surveillé.

## Installer

1. Téléchargez `pkg_pathly-x.y.z.zip` depuis les [versions publiées](https://github.com/Pathly-Monitoring/pathly-joomla/releases).
2. Dans l'administration : **Système → Installer → Extensions → Envoi de paquet**.

Le paquet installe trois extensions : le plugin **Système - Pathly**, le plugin **Contenu - Badge Pathly** et l'encart **Pathly** du tableau de bord. Rien n'est envoyé à Pathly tant qu'un administrateur n'a pas enregistré de clé d'API.

## Configurer

1. Dans Pathly, **Réglages** : créez une clé d'API. Préférez une clé limitée à un Espace, avec ces portées : `org:read`, `scenarios:read`, `scenarios:write`, `runs:read`, `runs:trigger`, `assistant:run`, `assistant:save`, `status:read`, `status:write`.
2. Dans Joomla, **Système → Plugins → Système - Pathly** : collez la clé et enregistrez. La clé est vérifiée et le Ping est créé.
3. Sur le tableau de bord, l'encart Pathly propose **Préparer le Flow avec Lumi**. Actualisez au bout d'une minute, puis enregistrez le Flow à surveiller.

La clé n'est plus jamais affichée après l'enregistrement. Laissez le champ vide pour la garder, ou utilisez **Déconnecter** dans l'encart pour la retirer du site.

### Boutiques (VirtueMart, HikaShop)

Lumi n'a pas de modèle de panier pour VirtueMart ni pour HikaShop. Sur une telle boutique, Lumi passe à son IA pour le panier : une IA en UE (Vertex AI, europe-west1) lit la structure et le texte de la page, jamais les captures, sans entraînement. Vous validez toujours le Flow avant de l'enregistrer.

### Paniers de test

Un Flow parcourt votre site comme un visiteur. Un Flow de panier ajoute un produit au panier et s'arrête à la page de paiement. Un Flow de contact remplit le formulaire et s'arrête avant l'envoi. Lumi ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Un panier laissé par un Flow peut tout de même déclencher une relance de panier abandonné dans votre boutique : excluez ces sessions de test de vos relances si besoin.

## Rejeu après une mise à jour

Le plugin système écoute les événements Joomla `onExtensionAfterUpdate` (gestionnaire de mises à jour), `onExtensionAfterInstall` (version plus récente envoyée à la main) et `onJoomlaAfterUpdate` (Joomla lui-même). Mettre à jour plusieurs extensions d'un coup ne demande qu'un seul passage, à la fin de la requête, une fois toutes les mises à jour installées. Le passage est `POST /v1/scenarios/{id}/run` sur le Flow, ou sur le Ping tant qu'il n'y a pas de Flow. **Lancer maintenant** dans l'encart fait la même chose à la main.

## Badge de disponibilité

Allumez **Badge de disponibilité** dans les réglages du plugin Système - Pathly. Cela publie la page de statut Pathly de votre organisation (ou de l'Espace de la clé). Écrivez ensuite `{pathly-badge}` dans un article. Tant que le badge est éteint, le code est retiré de la page. Le navigateur de vos visiteurs charge l'image du badge depuis `api.pathlyhq.com`.

## Données et sécurité

- L'extension n'appelle que `https://api.pathlyhq.com`. Elle n'appelle jamais une adresse saisie par un utilisateur.
- Elle envoie l'adresse du site, la version de Joomla et la version de l'extension (dans le `User-Agent`). Aucune donnée de client, aucune commande.
- Rien n'est envoyé avant qu'un administrateur ait enregistré une clé d'API.
- La clé est rangée dans les paramètres du plugin, dans la base de Joomla. Elle n'est jamais réécrite dans une page ni dans un journal.
- Les réglages et les actions de l'encart sont réservés aux Super Utilisateurs, et chaque action est protégée par le jeton de formulaire de Joomla (CSRF).
- La désinstallation retire les réglages et l'état avec les extensions. L'extension ne crée ni table ni tâche planifiée. Votre Ping et votre Flow restent dans Pathly : supprimez-les là-bas si vous n'en avez plus besoin.
- Mises à jour : comme toute extension Joomla qui a un serveur de mises à jour, Joomla interroge `raw.githubusercontent.com` quand vous cherchez des mises à jour.

## Développement

Voir la section « Development » du [README.md](README.md) : mêmes commandes.

## Licence

GPL-2.0-or-later. Voir [LICENSE](LICENSE).
