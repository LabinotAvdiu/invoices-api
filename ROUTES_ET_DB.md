# Routes API et Relations Base de Données

## 📋 Table des matières
1. [Configuration de la Base de Données](#configuration-de-la-base-de-données)
2. [Routes API](#routes-api)
3. [Structure de la Base de Données](#structure-de-la-base-de-données)
4. [Relations entre Modèles](#relations-entre-modèles)
5. [Mapping Routes ↔ Modèles](#mapping-routes--modèles)

---

## 🗄️ Configuration de la Base de Données

**Système de gestion de base de données:** **PostgreSQL 16**

- **Image Docker:** `postgres:16-alpine`
- **Connexion:** `pgsql` (configurée dans `.env` avec `DB_CONNECTION=pgsql`)
- **Port:** `5434:5432` (host:container)
- **Base de données:** `invoices_db`
- **Utilisateur:** `invoices_user`
- **Charset:** `utf8`
- **Search Path:** `public`

---

## 🛣️ Routes API

### Routes Publiques (sans authentification)

| Méthode | Endpoint | Contrôleur | Action | Description |
|---------|----------|------------|--------|-------------|
| `GET` | `/` | Closure | - | Page d'accueil de l'API |
| `GET` | `/api/health` | Closure | - | Vérification de santé de l'API |
| `POST` | `/api/register` | `AuthController` | `register` | Inscription d'un nouvel utilisateur |
| `POST` | `/api/login` | `AuthController` | `login` | Connexion d'un utilisateur |

### Routes Protégées (authentification requise via Sanctum)

| Méthode | Endpoint | Contrôleur | Action | Description |
|---------|----------|------------|--------|-------------|
| `GET` | `/api/user` | `UserController` | `show` | Récupérer l'utilisateur authentifié |
| `POST` | `/api/logout` | `AuthController` | `logout` | Déconnexion de l'utilisateur |
| `GET` | `/api/companies` | `CompanyController` | `index` | Liste paginée des entreprises (15 par page) |
| `POST` | `/api/companies` | `CompanyController` | `store` | Créer une nouvelle entreprise |
| `GET` | `/api/companies/{id}` | `CompanyController` | `show` | Afficher une entreprise spécifique |
| `PUT/PATCH` | `/api/companies/{id}` | `CompanyController` | `update` | Mettre à jour une entreprise |
| `DELETE` | `/api/companies/{id}` | `CompanyController` | `destroy` | Supprimer une entreprise |

---

## 🗄️ Structure de la Base de Données

### Table: `users`
**Modèle:** `App\Models\User`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `first_name` | string | Prénom |
| `last_name` | string | Nom |
| `email` | string (unique) | Email (unique) |
| `email_verified_at` | timestamp | Date de vérification email |
| `password` | string | Mot de passe (hashé) |
| `phone` | string (nullable) | Téléphone |
| `address` | string (nullable) | Adresse |
| `city` | string (nullable) | Ville |
| `zip` | string (nullable) | Code postal |
| `country` | string (nullable) | Pays |
| `remember_token` | string | Token de session |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de mise à jour |

### Table: `companies`
**Modèle:** `App\Models\Company`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `type` | enum('issuer', 'customer') | Type d'entreprise (émetteur/client) |
| `name` | string | Nom de l'entreprise |
| `legal_form` | string (nullable) | Forme juridique (SARL, SAS, SA, etc.) |
| `siret` | string(14) (nullable) | Numéro SIRET (14 chiffres) |
| `address` | text (nullable) | Adresse du siège social |
| `zip_code` | string(10) (nullable) | Code postal |
| `city` | string (nullable) | Ville |
| `country` | string (nullable) | Pays |
| `phone` | string (nullable) | Téléphone |
| `email` | string (nullable) | Email |
| `creation_date` | date (nullable) | Date de création de l'entreprise |
| `sector` | string (nullable) | Secteur d'activité |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de mise à jour |

### Table: `company_user` (Table pivot)
**Relation:** Many-to-Many entre `users` et `companies`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `company_id` | bigint (FK) | Référence à `companies.id` |
| `user_id` | bigint (FK) | Référence à `users.id` |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de mise à jour |

**Contraintes:**
- `unique(['company_id', 'user_id'])` - Empêche les doublons
- `onDelete('cascade')` - Suppression en cascade

### Table: `attachments`
**Modèle:** `App\Models\Attachment` (Polymorphique)

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Clé primaire |
| `name` | string | Nom du fichier |
| `type` | string (nullable) | Type MIME |
| `size` | bigint (nullable) | Taille en octets |
| `path` | string | Chemin/URL du fichier |
| `extension` | string (nullable) | Extension du fichier |
| `model_id` | bigint | ID du modèle associé |
| `model_type` | string | Type du modèle associé (ex: `App\Models\Company`) |
| `created_at` | timestamp | Date de création |
| `updated_at` | timestamp | Date de mise à jour |

**Usage:** Utilisé pour stocker les logos des entreprises (relation polymorphique)

---

## 🔗 Relations entre Modèles

### User ↔ Company (Many-to-Many)
```php
// Dans User.php
public function companies()
{
    return $this->belongsToMany(Company::class);
}

// Dans Company.php
public function users()
{
    return $this->belongsToMany(User::class);
}
```

**Table pivot:** `company_user`

**Comportement:**
- Lors de la création d'une entreprise de type `customer`, elle est automatiquement attachée à l'utilisateur authentifié
- Les entreprises de type `issuer` ne sont pas automatiquement attachées

### Company ↔ Attachment (Polymorphique)
```php
// Dans Company.php
public function logo()
{
    return $this->morphOne(Attachment::class, 'model');
}

public function attachments()
{
    return $this->morphMany(Attachment::class, 'model');
}
```

**Comportement:**
- Une entreprise peut avoir **un seul logo** (`morphOne`)
- Une entreprise peut avoir **plusieurs attachments** (`morphMany`)
- Lors de la suppression d'une entreprise, son logo est automatiquement supprimé (y compris le fichier physique)
- Lors de l'upload d'un nouveau logo, l'ancien est automatiquement supprimé

---

## 🔄 Mapping Routes ↔ Modèles

### Routes d'Authentification

| Route | Modèle | Table | Opération |
|-------|--------|-------|-----------|
| `POST /api/register` | `User` | `users` | **CREATE** - Crée un nouvel utilisateur |
| `POST /api/login` | `User` | `users` | **READ** - Vérifie les credentials |
| `POST /api/logout` | `PersonalAccessToken` | `personal_access_tokens` | **DELETE** - Supprime le token |

### Routes Utilisateur

| Route | Modèle | Table | Opération |
|-------|--------|-------|-----------|
| `GET /api/user` | `User` | `users` | **READ** - Récupère l'utilisateur authentifié |

### Routes Entreprises

| Route | Modèle | Table | Opération | Relations chargées |
|-------|--------|-------|-----------|-------------------|
| `GET /api/companies` | `Company` | `companies` | **READ** - Liste paginée | `logo` |
| `POST /api/companies` | `Company` | `companies` | **CREATE** - Crée une entreprise | `logo` |
| | `Company` | `company_user` | **CREATE** - Attache à l'utilisateur (si customer) | |
| | `Attachment` | `attachments` | **CREATE** - Crée le logo (si fourni) | |
| `GET /api/companies/{id}` | `Company` | `companies` | **READ** - Affiche une entreprise | `logo` |
| `PUT/PATCH /api/companies/{id}` | `Company` | `companies` | **UPDATE** - Met à jour une entreprise | `logo` |
| | `Attachment` | `attachments` | **UPDATE/DELETE** - Met à jour le logo (si fourni) | |
| `DELETE /api/companies/{id}` | `Company` | `companies` | **DELETE** - Supprime une entreprise | |
| | `Attachment` | `attachments` | **DELETE** - Supprime le logo (automatique) | |
| | `Company` | `company_user` | **DELETE** - Supprime les relations (cascade) | |

---

## 📊 Schéma des Relations

```
┌─────────────┐
│    users    │
│─────────────│
│ id          │◄────┐
│ first_name  │     │
│ last_name   │     │
│ email       │     │
│ password    │     │
│ ...         │     │
└─────────────┘     │
                    │
                    │ Many-to-Many
                    │ (via company_user)
                    │
┌─────────────┐     │
│  companies  │     │
│─────────────│     │
│ id          │─────┘
│ type        │
│ name        │
│ siret       │
│ ...         │
└─────────────┘
      │
      │ Polymorphique
      │ (morphOne/morphMany)
      │
      ▼
┌─────────────┐
│ attachments │
│─────────────│
│ id          │
│ name        │
│ path        │
│ model_id    │
│ model_type  │
│ ...         │
└─────────────┘
```

---

## 🔐 Authentification

- **Middleware:** `auth:sanctum`
- **Token:** Généré via `createToken('auth_token')`
- **Stockage:** Table `personal_access_tokens`
- **Format:** Bearer token dans le header `Authorization`

---

## 📝 Notes Importantes

1. **Pagination:** La route `GET /api/companies` retourne 15 entreprises par page
2. **Logo:** 
   - Stocké dans `storage/app/public/logos/`
   - Format: `company-{id}-{timestamp}.{extension}`
   - Suppression automatique lors de la mise à jour ou suppression de l'entreprise
3. **Attachement automatique:** Les entreprises de type `customer` sont automatiquement attachées à l'utilisateur qui les crée
4. **Scopes:** Le modèle `Company` inclut des scopes `issuer()` et `customer()` pour filtrer par type

