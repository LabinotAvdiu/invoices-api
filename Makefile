.PHONY: help up down build exec install migrate key-generate logs restart clean

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

test: ## Exécute les tests
	docker compose exec app php artisan test

cache-clear: ## Vide le cache
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

clean: ## Nettoie les containers, volumes et images
	docker compose down -v --rmi all

ps: ## Affiche l'état des containers
	docker compose ps

