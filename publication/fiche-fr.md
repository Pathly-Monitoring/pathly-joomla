# Fiche JED — français

Le JED publie les fiches en anglais. Ce texte sert à la relecture et aux pages en français (site, documentation).

## Titre

Pathly for Joomla

## Résumé court (une ligne)

Un Ping sur votre site, un Flow de votre formulaire de contact ou de votre connexion préparé par Lumi, rejoué après chaque mise à jour de Joomla.

## Description

_Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold._

Un site peut répondre 200 alors que son formulaire de contact ou sa connexion sont cassés. Pathly for Joomla surveille les deux.

- **Ping** : dès que vous enregistrez votre clé d'API Pathly, la page d'accueil du site est surveillée. Si un Ping surveille déjà cette adresse, l'extension s'y rattache, sans doublon.
- **Flow préparé par Lumi** : Lumi, l'assistant Flow de Pathly, reconnaît Joomla et prépare un Flow de votre formulaire de contact ou de votre connexion. Chaque Flow proposé est rejoué une fois avant de vous être montré. Vous choisissez celui à garder, en un clic.
- **Rejeu après une mise à jour** : après une mise à jour de Joomla ou d'une extension, Pathly relance votre Flow, une seule fois, quand les mises à jour sont finies.
- **Encart du tableau de bord** : état du Ping et du Flow, date et capture du dernier échec, offre et quotas, lien vers Pathly.
- **Badge de disponibilité** : `{pathly-badge}` dans un article affiche l'état du site. Éteint par défaut.

Boutiques : Lumi n'a pas de modèle de panier pour VirtueMart ni pour HikaShop. Sur ces boutiques, une IA en UE lit la structure et le texte de la page pour préparer le Flow du panier, jamais les captures, sans entraînement.

Données : l'extension n'appelle que `api.pathlyhq.com`, et seulement après l'enregistrement d'une clé par un administrateur. Elle envoie l'adresse du site et les versions de Joomla et de l'extension. Aucune donnée de client, aucune commande. La clé n'est plus jamais affichée après l'enregistrement. Réglages et actions réservés aux Super Utilisateurs.

Prérequis : Joomla 4.4 (PHP 7.4 ou plus) ou Joomla 5 (PHP 8.1 ou plus), un compte Pathly. L'offre Solo est gratuite, sans limite de durée.

## FAQ

**Faut-il une offre payante ?**
Non. L'offre Solo est gratuite, sans limite de durée, avec des Ping et un Flow. Les offres payantes ajoutent des Ping et des Flow, et des intervalles plus courts.

**Lumi achète-t-il quelque chose ou envoie-t-il mes formulaires ?**
Non. Lumi ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Un Flow de panier s'arrête à la page de paiement. Un Flow de contact s'arrête avant l'envoi.

**Un Flow peut-il déclencher mes relances de panier abandonné ?**
Oui, un panier laissé par un Flow le peut. Excluez ces sessions de test de vos relances si besoin.

**Qu'est-ce qui part vers Pathly ?**
L'adresse du site, la version de Joomla et la version de l'extension. Rien avant l'enregistrement d'une clé.

**Que se passe-t-il à la désinstallation ?**
Les réglages et l'état partent avec l'extension. Votre Ping et votre Flow restent dans Pathly jusqu'à ce que vous les supprimiez là-bas.

**Mon site est hors ligne ou protégé par un mot de passe.**
Pathly regarde votre site depuis Internet, comme un visiteur. Un site que les visiteurs n'atteignent pas ne peut pas être surveillé.

## Journal des changements

### 0.1.0

- Première version pour Joomla 4.4 et 5 : clé d'API dans le gestionnaire de plugins, Ping du site, Flow préparé par Lumi, rejeu après chaque mise à jour de Joomla ou d'une extension, encart du tableau de bord, badge de disponibilité (éteint par défaut).

## Liens

- Site : https://pathlyhq.com
- Téléchargement : https://github.com/Pathly-Monitoring/pathly-joomla/releases (public une fois le dépôt publié)
- Documentation : https://github.com/Pathly-Monitoring/pathly-joomla#readme
- Support : contact@pathlyhq.com
- Licence : GPL-2.0-or-later
