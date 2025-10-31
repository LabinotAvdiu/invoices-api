.PHONY: help up down build exec install migrate key-generate logs restart clean test

# Variables
CONTAINER_APP = invoices_app

help: ## Affiche l'aide
	@echo "Commandes disponibles:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Démarre les containers Docker
	docker compose up -d

down: ## Arrête les containers Docker
	docker compose down

build: ## Construit les images Docker
	docker compose build

restart: ## Redémarre les containers
	docker compose restart

exec: ## Entre dans le container app (bash)
	docker compose exec app bash

install: ## Installation complète (composer install + key-generate + migrate)
	@echo "🚀 Démarrage des containers..."
	@docker compose up -d
	@echo "⏳ Attente du démarrage des services (10 secondes)..."
	@sleep 10
	@echo "📦 Installation des dépendances Composer..."
	@docker compose exec app composer install
	@echo "🔧 Configuration des permissions..."
	@docker compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
	@docker compose exec app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
	@echo "🔑 Génération de la clé d'application..."
	@docker compose exec app php artisan key:generate
	@echo "🗄️  Exécution des migrations..."
	@docker compose exec app php artisan migrate
	@echo "✅ Installation terminée !"

update: ## Met à jour les dépendances Composer
	docker compose exec app composer update

migrate: ## Exécute les migrations
	docker compose exec app php artisan migrate

migrate-fresh: ## Réinitialise et exécute les migrations
	docker compose exec app php artisan migrate:fresh

key-generate: ## Génère la clé d'application Laravel
	docker compose exec app php artisan key:generate

setup: ## Alias pour make install (même fonction)
	$(MAKE) install

composer-install: ## Installe uniquement les dépendances Composer
	docker compose exec app composer install

logs: ## Affiche les logs des containers
	docker compose logs -f

logs-app: ## Affiche les logs du container app
	docker compose logs -f app

logs-nginx: ## Affiche les logs du container nginx
	docker compose logs -f nginx

logs-mysql: ## Affiche les logs du container mysql
	docker compose logs -f mysql

artisan: ## Exécute une commande artisan (usage: make artisan cmd="migrate:status")
	docker compose exec app php artisan $(cmd)

fix-permissions: ## Corrige les permissions des fichiers pour l'utilisateur hôte
	@echo "🔧 Correction des permissions de toute l'application..."
	@docker compose exec app chown -R $(shell id -u):$(shell id -g) /var/www/html
	@docker compose exec app find /var/www/html -type f -exec chmod 664 {} \;
	@docker compose exec app find /var/www/html -type d -exec chmod 775 {} \;
	@docker compose exec app chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
	@docker compose exec app chmod +x /var/www/html/artisan
	@echo "✅ Permissions corrigées pour toute l'application !"

sync-from-host: ## Synchronise les fichiers depuis l'hôte vers le conteneur (force l'écriture)
	@echo "🔄 Synchronisation des fichiers depuis l'hôte..."
	@echo "⚠️  Note: Les volumes Docker synchronisent automatiquement, cette commande force la mise à jour des timestamps"
	@find . -type f -name "*.php" -exec touch {} \;
	@make fix-permissions
	@echo "✅ Synchronisation terminée !"

test: ## Exécute tous les tests
	@echo "🧪 Exécution des tests..."
	@docker compose exec app php artisan test

cache-clear: ## Vide le cache
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

clean: ## Nettoie les containers, volumes et images
	docker compose down -v --rmi all

ps: ## Affiche l'état des containers
	docker compose ps

