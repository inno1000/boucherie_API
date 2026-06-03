# CLAUDE.md — Guide développement Butcher Backend API

## Présentation du projet

API REST Laravel 10 pour la gestion d'une boucherie : achats fournisseurs, abattages, stocks, ventes, distributions, versements. Consommée par une **application mobile** (pas de frontend web).

## Commandes essentielles

```bash
# Tests (toujours lancer avant de commit)
php vendor/bin/pest --no-coverage

# Un fichier spécifique
php vendor/bin/pest tests/Feature/V1/VenteTest.php --no-coverage

# Migrations
php artisan migrate
php artisan migrate:fresh --seed   # reset complet

# Lancer le serveur local
php artisan serve
```

## Architecture

Flux d'une requête : `FormRequest → Middleware (auth:sanctum + role) → Controller → Service → Repository → JsonResource`

```
app/Http/Controllers/Api/V1/   Thin — délèguent tout aux services
app/Http/Requests/             Validation (FormRequest). authorize() retourne true, la logique est dans les middlewares.
app/Http/Resources/            JsonResource — formatage JSON des réponses
app/Services/                  Logique métier, transactions DB
app/Repositories/              Queries Eloquent
app/Models/                    Eloquent + HasUuids sur tous les modèles
app/Policies/                  Contrôle d'accès par ressource (authorize() dans les controllers)
```

## Rôles (Spatie Permission)

Trois rôles : `admin`, `boucher`, `fournisseur`. Créés au seeding et dans `tests/Pest.php`.

- Les routes sont protégées par `middleware('role:admin|boucher')` etc.
- Les Policies affinent l'accès par ressource (ex : un boucher ne voit que sa boucherie).
- **L'endpoint `/auth/register` n'attribue aucun rôle.** La création de comptes avec rôle passe par `POST /api/v1/users` (admin uniquement).

## Modèles importants

| Modèle | Table | Notes |
|---|---|---|
| `User` | `users` | `boucherie_id` nullable (null pour fournisseur) |
| `Fournisseur` | `fournisseurs` | Entité liée à un User via `user_id` |
| `AchatFournisseur` | `achats_fournisseurs` | Achat + liste d'animaux |
| `Animal` | `animaux` | Créé automatiquement à l'achat |
| `Abattage` | `abattages` | Déclenche la mise à jour du stock |
| `Stock` | `stocks` | Unique sur `(boucherie_id, produit_id)` |
| `Distribution` | `distributions` | Fournisseur → Boucherie |
| `Versement` | `versements` | Paiement Boucherie → Fournisseur |
| `EnumValeur` | `enum_valeurs` | Référentiels (espèces, catégories, statuts…) |

Tous les modèles utilisent des UUIDs (`HasUuids`).

## Tests

### Configuration globale (`tests/Pest.php`)

Les rôles sont créés dans le `beforeEach` **chaîné** à `uses()` — pas standalone. Si tu ajoutes un `beforeEach` global, il doit être chaîné :

```php
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        // ici, pas dans un beforeEach() séparé
    })
    ->in('Feature', 'Unit');
```

### Helpers disponibles dans les tests

```php
adminUser(?string $boucherieId)   // Crée un User avec rôle admin
boucherUser(Boucherie $boucherie) // Crée un User avec rôle boucher
fournisseurUser(?Fournisseur $f)  // Crée un User avec rôle fournisseur
```

### Pièges courants

- **Unique constraint stocks** : `stocks` a une contrainte unique sur `(boucherie_id, produit_id)`. Ne jamais créer plusieurs stocks avec le même produit pour la même boucherie dans un test.
- **EnumValeur requis** : plusieurs FormRequests valident les champs contre `enum_valeurs` (ex: `categorie_produit`, `unite_produit`, `type_mouvement`, `statut_vente`, `type_vente`). Les créer dans le `beforeEach` du test si nécessaire.
- **Scoping Pest** : les propriétés `$this->xxx` définies dans un `beforeEach` d'un `describe()` parent ne sont **pas accessibles** dans les `it()` d'un `describe()` enfant. Utiliser des `beforeEach` à plat (fichier level).
- **SQLite + migrations** : les migrations avec `->change()` ne fonctionnent pas sur SQLite. Ajouter un bypass `if (DB::connection()->getDriverName() === 'sqlite') return;`.

### Base de données de test

SQLite in-memory (`.env.testing` ou `phpunit.xml`). `RefreshDatabase` recrée le schéma à chaque test.

## Sécurité — règles à respecter

### Ne jamais faire

- Accepter un rôle dans un endpoint public
- Créer une Policy sans gérer le cas `null` pour `boucherie_id` (deux fournisseurs avec `boucherie_id = null` auraient `null === null` → `true`)
- Laisser un champ calculé (montant, total) soumis par le client sans recalcul serveur
- Ajouter un champ dans `$fillable` sans vérifier l'impact sur les Policies existantes
- Faire confiance à `request()->input()` sans passer par un FormRequest validé

### Patterns corrects

**Calcul côté serveur :**
```php
// Toujours recalculer, ignorer la valeur soumise
unset($data['montant_total']);
$montant = array_sum(array_column($items, 'prix'));
$model->update(['montant_total' => $montant]);
```

**Policy avec nullable :**
```php
// Mauvais — null === null est true
return $user->boucherie_id === $resource->boucherie_id;

// Correct
return $user->boucherie_id !== null
    && $resource->boucherie_id !== null
    && $user->boucherie_id === $resource->boucherie_id;
```

**Ownership check → 403, pas 422 :**
```php
// Mauvais — révèle l'existence de la ressource
throw ValidationException::withMessages(['x' => ['Non autorisé']]);

// Correct
abort(403, 'Action non autorisée.');
```

**Réponse 401 pour mauvais credentials :**
```php
// Mauvais — retourne 422 (sémantique incorrecte)
throw ValidationException::withMessages(['email' => ['Mauvais mot de passe']]);

// Correct
abort(401, 'Identifiants invalides.');
```

## Hub « Aujourd’hui »

- `GET /api/v1/dashboard/today` — résumé, tâches prioritaires et activité récente (scoping rôle).
- Service : `DashboardService` ; stats du jour via `StatsService` avec `periode=jour`.
- Listes : `?statut=` et `?limit=` (max 100) sur animaux, distributions, versements.

### Flux métier canonique

```text
Fournisseur : Achat → Abattage → Distribution → (Boucher réceptionne) → Stock → Vente
Boucher     : Réception → Stock → Vente → Versement fournisseur
```

## Variables d'environnement clés

```env
# Sécurité
SANCTUM_TOKEN_EXPIRATION=43200   # 30 jours — ne pas mettre null en prod
ALLOWED_ORIGINS=http://localhost:3000   # Pas critique pour une API mobile

# BDD locale
DB_CONNECTION=mysql
DB_DATABASE=butcher_db

# Production (Render + PostgreSQL)
DB_CONNECTION=pgsql
APP_ENV=production
APP_DEBUG=false
```

## Déploiement Docker / Render

Le `Dockerfile` contient un stage `tester` qui lance `pest --no-coverage`. Si un test échoue → le build Docker échoue → le déploiement Render est bloqué. C'est intentionnel.

Toujours vérifier que `php vendor/bin/pest --no-coverage` passe avant de pousser.

## CORS

CORS est un mécanisme **navigateur uniquement**. Cette API étant consommée par une application mobile, CORS n'a pas d'impact sécurité. La configuration dans `config/cors.php` est maintenue pour d'éventuels outils de développement web, mais n'est pas une surface d'attaque pour une app mobile.
