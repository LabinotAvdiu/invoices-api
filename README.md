# Invoices API

API Laravel pour la gestion des factures.

## Prérequis

- Docker
- Docker Compose

## Installation

1. Cloner le projet
2. Construire et démarrer les containers Docker:
   ```bash
   make build
   make up
   ```

3. Installer les dépendances et configurer l'application:
   ```bash
   make setup
   ```

   Ou manuellement:
   ```bash
   make install
   make key-generate
   make migrate
   ```

## Commandes Make

Le projet inclut un Makefile pour simplifier les opérations courantes :

### Commandes principales
- `make help` - Affiche toutes les commandes disponibles
- `make up` - Démarre les containers Docker
- `make down` - Arrête les containers Docker
- `make build` - Construit les images Docker
- `make exec` - Entre dans le container app (bash)
- `make install` - Installe les dépendances Composer
- `make setup` - Configuration initiale complète (install + key-generate + migrate)

### Autres commandes utiles
- `make migrate` - Exécute les migrations
- `make migrate-fresh` - Réinitialise et exécute les migrations
- `make key-generate` - Génère la clé d'application Laravel
- `make logs` - Affiche les logs de tous les containers
- `make logs-app` - Affiche les logs du container app
- `make cache-clear` - Vide tous les caches
- `make artisan cmd="votre:commande"` - Exécute une commande artisan
- `make ps` - Affiche l'état des containers

## Accès

L'API est accessible sur: http://localhost:7778

**Note:** Si le port 7777 est déjà utilisé, vous pouvez le changer dans `docker-compose.yml` à la ligne 22.

## Endpoints

- `GET /` - Page d'accueil
- `GET /api/health` - Vérification de santé de l'API

## Commandes utiles

- Démarrer les containers: `docker-compose up -d`
- Arrêter les containers: `docker-compose down`
- Voir les logs: `docker-compose logs -f`
- Exécuter des commandes Artisan: `docker-compose exec app php artisan [command]`

