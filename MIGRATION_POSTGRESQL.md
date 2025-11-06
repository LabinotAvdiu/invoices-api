# Migration MySQL vers PostgreSQL

## ✅ Modifications effectuées

### 1. Docker Compose (`docker-compose.yml`)
- ✅ Service `mysql` remplacé par `postgres`
- ✅ Image changée de `mysql:8.0` vers `postgres:16-alpine`
- ✅ Variables d'environnement adaptées (POSTGRES_DB, POSTGRES_USER, etc.)
- ✅ Port changé de `3307:3306` vers `5434:5432`
- ✅ Volume `mysql_data` renommé en `postgres_data`
- ✅ Dépendance `app` mise à jour pour dépendre de `postgres`

### 2. Dockerfile
- ✅ Extension PHP `pdo_mysql` remplacée par `pdo_pgsql`
- ✅ Bibliothèque `libpq-dev` ajoutée pour le support PostgreSQL

### 3. Configuration (.env)
- ✅ `DB_CONNECTION` changé de `mysql` vers `pgsql`
- ✅ `DB_HOST` changé de `mysql` vers `postgres`
- ✅ `DB_PORT` changé de `3306` vers `5432`

### 4. Migrations
- ✅ Les migrations sont compatibles avec PostgreSQL
- ✅ Le type `enum()` sera automatiquement converti en CHECK constraint par Laravel

### 5. Documentation
- ✅ `ROUTES_ET_DB.md` mis à jour avec les informations PostgreSQL

---

## 🚀 Étapes pour appliquer la migration

### Option 1: Nouvelle installation (sans données existantes)

1. **Arrêter les containers existants:**
   ```bash
   make down
   # ou
   docker compose down --remove-orphans
   ```

2. **Supprimer l'ancien volume MySQL (optionnel):**
   ```bash
   docker volume rm invoices-api_mysql_data
   ```

3. **Reconstruire les images Docker:**
   ```bash
   make build
   # ou
   docker compose build
   ```

4. **Démarrer les containers:**
   ```bash
   make up
   # ou
   docker compose up -d
   ```

5. **Exécuter les migrations:**
   ```bash
   make migrate
   # ou
   docker compose exec app php artisan migrate
   ```

### Option 2: Migration avec données existantes

Si vous avez des données existantes dans MySQL que vous souhaitez migrer vers PostgreSQL:

1. **Exporter les données MySQL:**
   ```bash
   # Si vous avez encore accès à l'ancien container MySQL
   docker exec invoices_mysql mysqldump -u invoices_user -pinvoices_password invoices_db > backup_mysql.sql
   ```

2. **Arrêter les containers:**
   ```bash
   make down
   ```

3. **Appliquer les modifications (déjà faites):**
   - Les fichiers ont déjà été modifiés

4. **Reconstruire et démarrer:**
   ```bash
   make build
   make up
   ```

5. **Exécuter les migrations:**
   ```bash
   make migrate
   ```

6. **Importer les données (nécessite conversion):**
   - Vous devrez convertir le dump MySQL en format compatible PostgreSQL
   - Utiliser un outil comme `pgloader` ou convertir manuellement le SQL
   - **Note:** La conversion automatique peut nécessiter des ajustements manuels

---

## ⚠️ Notes importantes

### Différences MySQL vs PostgreSQL

1. **Types de données:**
   - `ENUM` → Laravel convertit automatiquement en CHECK constraint
   - `TEXT` → Compatible dans les deux systèmes
   - `VARCHAR` → Compatible

2. **Syntaxe SQL:**
   - Les migrations Laravel gèrent automatiquement les différences
   - Les requêtes Eloquent restent identiques

3. **Fonctionnalités:**
   - Les relations Eloquent fonctionnent de la même manière
   - Les migrations sont compatibles grâce à l'abstraction Laravel

### Vérification

Pour vérifier que PostgreSQL fonctionne correctement:

```bash
# Vérifier la connexion
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
>>> DB::select('SELECT version()');
```

Ou tester une route:
```bash
curl http://localhost:7778/api/health
```

---

## 🔧 Dépannage

### Erreur: "could not connect to server"
- Vérifiez que le container PostgreSQL est démarré: `docker compose ps`
- Vérifiez les variables d'environnement dans `.env`
- Vérifiez que le port 5434 n'est pas déjà utilisé

### Erreur: "extension pdo_pgsql not found"
- Reconstruisez l'image Docker: `make build`
- Vérifiez que `libpq-dev` est installé dans le Dockerfile

### Erreur lors des migrations
- Vérifiez que la base de données `invoices_db` existe
- Vérifiez les permissions de l'utilisateur PostgreSQL
- Consultez les logs: `docker-compose logs postgres`

---

## 📝 Commandes utiles

```bash
# Voir les logs PostgreSQL
docker compose logs postgres
# ou
make logs-postgres

# Se connecter à PostgreSQL
docker compose exec postgres psql -U invoices_user -d invoices_db

# Lister les bases de données
docker compose exec postgres psql -U invoices_user -c "\l"

# Voir les tables
docker compose exec postgres psql -U invoices_user -d invoices_db -c "\dt"
```

---

## ✨ Avantages de PostgreSQL

- ✅ Meilleure conformité aux standards SQL
- ✅ Support avancé des types de données (JSON, Array, etc.)
- ✅ Meilleures performances pour les requêtes complexes
- ✅ Support natif des transactions et de la concurrence
- ✅ Extensions puissantes (PostGIS, pg_trgm, etc.)

