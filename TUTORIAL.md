# Laravel 12 API Authentication with Sanctum

> **Subject:** Integrative Programming and Technologies
> **Topic:** Building a Token-Based REST API with Laravel Sanctum

---

## Table of Contents

1. [Background: What Are We Building and Why?](#1-background)
2. [Prerequisites](#2-prerequisites)
3. [Step 1 — Set Up the MySQL Database](#step-1--set-up-the-mysql-database)
4. [Step 2 — Configure the Environment File](#step-2--configure-the-environment-file)
5. [Step 3 — Install Sanctum with `php artisan install:api`](#step-3--install-sanctum)
6. [Step 4 — Run the Migrations](#step-4--run-the-migrations)
7. [Step 5 — Add the `HasApiTokens` Trait to the User Model](#step-5--update-the-user-model)
8. [Step 6 — Create the AuthController](#step-6--create-the-authcontroller)
9. [Step 7 — Define the API Routes](#step-7--define-the-api-routes)
10. [Step 8 — Testing with Postman](#step-8--testing-with-postman)
11. [Common Errors and Fixes](#common-errors-and-fixes)
12. [Summary and Next Steps](#summary-and-next-steps)

---

## 1. Background

### What is an API?

An **API (Application Programming Interface)** is a way for two software systems to communicate with each other. When you build a mobile app, a single-page React app, or a third-party integration, those clients do not render HTML — they ask your server for **data**, and you respond with **JSON**.

A **REST API** is the most common style: the client sends an HTTP request to a URL (called an **endpoint**), and the server returns a JSON response.

```
Mobile App  ──POST /api/login──►  Laravel Server  ──► JSON response
```

### What is Authentication and Why Do We Need Tokens?

When a user logs in on a website, a **session cookie** is stored in the browser. The browser automatically sends that cookie on every request — this is how traditional web apps know who you are.

But **APIs don't use cookies**. A mobile app or a JavaScript SPA (Single Page Application) cannot rely on browser session cookies. Instead, we use **tokens**.

The flow works like this:

```
1. Client sends: POST /api/login  {email, password}
2. Server checks the credentials
3. Server responds: { "token": "1|abc123xyz..." }
4. Client stores the token (in memory or local storage)
5. On every future request, the client sends:
      Authorization: Bearer 1|abc123xyz...
6. Server reads the token, looks it up, and knows who the user is
```

The token is like a **temporary ID card**. It proves you are who you say you are, without re-sending your password every time.

### What is Laravel Sanctum?

**Laravel Sanctum** is an official Laravel package that provides a simple token-based authentication system for APIs. It:

- Generates **plain-text tokens** and stores their hashed versions in the `personal_access_tokens` database table
- Provides the `auth:sanctum` middleware to protect routes — any request to a protected route must include a valid token
- Gives the User model a `createToken()` method and a `tokens()` relationship

Sanctum is the recommended choice for **mobile apps** and **SPAs** communicating with a Laravel backend.

---

## 2. Prerequisites

Before starting, make sure you have the following installed:

| Tool | Purpose | Download |
|---|---|---|
| **Laravel Herd** | Local PHP/Laravel environment | [herd.laravel.com](https://herd.laravel.com) |
| **MySQL** | Database (included with Herd Pro, or use MAMP/XAMPP) | bundled with Herd |
| **Postman** | API testing client | [postman.com](https://www.postman.com/downloads/) |
| **A code editor** | VS Code recommended | [code.visualstudio.com](https://code.visualstudio.com) |

You also need a fresh Laravel 12 project with **no starter kit** selected. If you haven't created one yet:

```bash
# In your Herd sites directory:
laravel new api-authentication
# When asked for a starter kit: choose None
# When asked for the testing framework: choose PHPUnit
```

---

## Step 1 — Set Up the MySQL Database

> **Why this step?** Laravel needs an existing database to connect to. Unlike SQLite (which uses a single file), MySQL requires you to create the database first.

### Option A: Using a GUI tool (TablePlus, DBeaver, phpMyAdmin)

1. Open your database manager
2. Connect to MySQL using:
   - Host: `127.0.0.1`
   - Port: `3306`
   - Username: `root`
   - Password: `password`
3. Create a new database named **`api-demo`**
4. Set the character set to `utf8mb4` and collation to `utf8mb4_general_ci` or `utf8mb4_unicode_ci`

### Option B: Using the MySQL CLI

```sql
mysql -u root -p
-- Enter your password when prompted --

CREATE DATABASE `api-demo` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
EXIT;
```

**Verify:** You should see `api-demo` listed in your databases.

---

## Step 2 — Configure the Environment File

> **Why this step?** The `.env` file is Laravel's configuration hub. It stores sensitive settings — like database credentials — outside of your code so you never accidentally commit them to Git. We need to point Laravel at our MySQL database instead of the default SQLite.

Open the `.env` file at the root of your project and find the database section. It will look like this by default:

```dotenv
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

Replace it with:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api-demo
DB_USERNAME=root
DB_PASSWORD=password
```

**What each line means:**

| Key | Value | Meaning |
|---|---|---|
| `DB_CONNECTION` | `mysql` | Use the MySQL driver instead of SQLite |
| `DB_HOST` | `127.0.0.1` | MySQL is running on this same machine (localhost) |
| `DB_PORT` | `3306` | The default MySQL port |
| `DB_DATABASE` | `api-demo` | The database we just created |
| `DB_USERNAME` | `root` | MySQL username |
| `DB_PASSWORD` | `password` | MySQL password |

> **Note:** In a real production app, never use `root` with a simple password. This setup is for local development only.

---

## Step 3 — Install Sanctum

> **Why this step?** In Laravel 12, the API scaffolding is not included by default. The `install:api` command adds everything we need in one go: it installs Sanctum via Composer, creates the `routes/api.php` file, and registers the API middleware.

Run this command in your project directory:

```bash
php artisan install:api
```

When it asks *"Would you like to run all pending database migrations?"*, type **`no`** and press Enter. We will run migrations manually in the next step so we can see exactly what is being created.

**What this command does behind the scenes:**

1. **Runs `composer require laravel/sanctum`** — downloads the Sanctum package
2. **Creates `routes/api.php`** — your new file for all API routes
3. **Updates `bootstrap/app.php`** — wires up `routes/api.php` and registers Sanctum's middleware
4. **Publishes a new migration** — creates a migration file for the `personal_access_tokens` table

After running the command, open `bootstrap/app.php` and you should see that `api.php` has been added:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',   // ← added by install:api
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

---

## Step 4 — Run the Migrations

> **Why this step?** Migrations are PHP files that describe your database tables. Running `migrate` executes those files and creates the actual tables in MySQL. Without this step, Laravel will crash when it tries to read from or write to tables that don't exist yet.

```bash
php artisan migrate
```

You should see output like this:

```
INFO  Running migrations.

  0001_01_01_000000_create_users_table ............... DONE
  0001_01_01_000001_create_cache_table ............... DONE
  0001_01_01_000002_create_jobs_table ................ DONE
  2026_03_17_xxxxxx_create_personal_access_tokens_table ... DONE
```

**The key table for us is `personal_access_tokens`.**

Open your database manager and inspect it — you will see these columns:

| Column | Purpose |
|---|---|
| `id` | Auto-increment primary key |
| `tokenable_type` | The model type (e.g., `App\Models\User`) |
| `tokenable_id` | The ID of the user this token belongs to |
| `name` | The token's name (we set it to `'authToken'`) |
| `token` | The **hashed** version of the token (the plain-text is only shown once) |
| `abilities` | What the token is allowed to do (we use `['*']` = everything) |
| `last_used_at` | Timestamp of the last API call with this token |
| `expires_at` | Optional expiry date |
| `created_at / updated_at` | Standard timestamps |

> **Important:** Sanctum only stores the **hash** of the token, not the plain text. Once you receive a token from the API, save it — you cannot retrieve it again.

---

## Step 5 — Update the User Model

> **Why this step?** The `HasApiTokens` trait is what gives a User the ability to create tokens. Without it, calling `$user->createToken(...)` will throw a "method not found" error because the method simply does not exist on a plain `Authenticatable` model.

Open `app/Models/User.php` and make two changes:

**1. Add the import** at the top with the other `use` statements:

```php
use Laravel\Sanctum\HasApiTokens;
```

**2. Add `HasApiTokens` to the `use` line** inside the class:

```php
use HasApiTokens, HasFactory, Notifiable;
```

Your complete `User.php` should now look like this:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;           // ← added

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // ← HasApiTokens added

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

> **What does `'password' => 'hashed'` in `casts()` do?**
> Whenever you set `$user->password = 'somevalue'`, Laravel automatically runs `bcrypt()` on it before storing it in the database. This means you don't need to manually call `Hash::make()` in your controller — it's handled by the model.

---

## Step 6 — Create the AuthController

> **Why this step?** We need a controller to handle the logic for each endpoint. We put it inside an `API/` subfolder to keep API controllers separate from any future web controllers. This is a common convention in Laravel projects.

**Generate the file:**

```bash
php artisan make:controller API/AuthController
```

This creates `app/Http/Controllers/API/AuthController.php`.

Now replace its contents with the full implementation:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
```

### Method-by-Method Explanation

#### `register()`

```php
$request->validate([...]);
```
Laravel's built-in validation. If any rule fails, it automatically returns a **422 Unprocessable Content** response with a JSON `errors` object — no extra code needed.

- `unique:users` — checks the `users` table to make sure the email isn't already taken
- `confirmed` — requires a `password_confirmation` field that matches `password`

```php
$user = User::create([...]);
```
Creates a new row in the `users` table. Because `password` is in the model's `casts()` as `'hashed'`, it gets bcrypt-hashed automatically.

```php
$token = $user->createToken('authToken')->plainTextToken;
```
This is the Sanctum magic:
- `createToken('authToken')` creates a new record in `personal_access_tokens` and returns a `NewAccessToken` object
- `.plainTextToken` gives you the raw token string (e.g., `1|abc123...`) — this is the only time you ever see the plain text

```php
return response()->json([...], 201);
```
Returns JSON. The `201` status code means **Created** — the standard HTTP code for a successful resource creation.

---

#### `login()`

```php
if (! Auth::attempt($request->only('email', 'password'))) {
    throw ValidationException::withMessages([...]);
}
```
`Auth::attempt()` looks up the user by email and compares the provided password against the stored bcrypt hash. If it fails, we throw a `ValidationException` which Laravel renders as a **422** response.

---

#### `user()`

```php
return response()->json(['user' => $request->user()]);
```
`$request->user()` returns the authenticated user that Sanctum resolved from the Bearer token. This only works because this route is behind `auth:sanctum` middleware.

---

#### `logout()`

```php
$request->user()->tokens()->delete();
```
This deletes **all** tokens belonging to this user from the `personal_access_tokens` table — logging them out on every device simultaneously. If you want single-device logout, use `$request->user()->currentAccessToken()->delete()` instead.

---

## Step 7 — Define the API Routes

> **Why this step?** Routes connect URLs to controller methods. We need to tell Laravel which controller method to call when a specific HTTP request comes in. We also need to protect certain routes so only authenticated users can access them.

Open `routes/api.php` and replace everything with:

```php
<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes — no token required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected routes — valid Sanctum token required
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user',    [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

**Your complete API endpoint table:**

| Method | URL | Auth Required | Controller Method | Description |
|---|---|---|---|---|
| POST | `/api/register` | No | `register()` | Create a new account |
| POST | `/api/login` | No | `login()` | Log in and get a token |
| GET | `/api/user` | Yes (Bearer token) | `user()` | Get the authenticated user |
| POST | `/api/logout` | Yes (Bearer token) | `logout()` | Revoke all tokens |

> **Note:** All routes in `routes/api.php` are automatically prefixed with `/api`. So `Route::post('/register', ...)` becomes `POST /api/register`.

> **Why `auth:sanctum` and not just `auth`?** Laravel has multiple authentication guards. The default `auth` guard uses sessions (for web apps). `auth:sanctum` tells Laravel to use the Sanctum token guard — it reads the `Authorization: Bearer ...` header instead of a session cookie.

---

## Step 8 — Testing with Postman

Postman is a tool that lets you send HTTP requests to your API without building a frontend. It's the standard tool for API development and testing.

### Setting Up

1. Open Postman
2. Click **"New"** → **"Collection"** → name it `Laravel Sanctum Auth`
3. Inside the collection, create separate requests for each test below

> **Critical setting for every request:**
> Go to the **Headers** tab and add:
> ```
> Key:   Accept
> Value: application/json
> ```
> Without this header, when Laravel encounters a validation error it returns an **HTML page** (because it assumes you're a browser). With this header, it returns clean **JSON**.

---

### Test 1 — Register (Happy Path)

| Setting | Value |
|---|---|
| Method | `POST` |
| URL | `http://api-authentication.test/api/register` |
| Headers | `Accept: application/json` |
| Body | Raw → JSON |

**Request body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Expected response — Status `201 Created`:**
```json
{
    "message": "User registered successfully.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2026-03-17T10:00:00.000000Z",
        "updated_at": "2026-03-17T10:00:00.000000Z"
    },
    "token": "1|abc123def456ghi789..."
}
```

> **Copy the token value** — you will need it for the protected routes.

---

### Test 2 — Register with Duplicate Email (Negative Test)

Send the exact same request as Test 1 again (same email).

**Expected response — Status `422 Unprocessable Content`:**
```json
{
    "message": "The email has already been taken.",
    "errors": {
        "email": [
            "The email has already been taken."
        ]
    }
}
```

> **Discussion point:** Notice how the validation error response is automatically structured and includes which field failed. This is all handled by Laravel's validation system — we wrote zero error-handling code for this.

---

### Test 3 — Register with Missing `password_confirmation`

```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "password123"
}
```

**Expected response — Status `422`:**
```json
{
    "message": "The password field confirmation does not match.",
    "errors": {
        "password": [
            "The password field confirmation does not match."
        ]
    }
}
```

> **Why is `password_confirmation` required?** The `confirmed` validation rule protects users from typos when setting their password. It requires you to type the password twice and checks they match.

---

### Test 4 — Login (Happy Path)

| Setting | Value |
|---|---|
| Method | `POST` |
| URL | `http://api-authentication.test/api/login` |

**Request body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Expected response — Status `200 OK`:**
```json
{
    "message": "Login successful.",
    "user": { ... },
    "token": "2|xyz789abc123..."
}
```

> **Notice:** A **new token** is issued on each login. The previous token from registration is still valid. Check the `personal_access_tokens` table — you should see two rows for this user now.

---

### Test 5 — Login with Wrong Password (Negative Test)

```json
{
    "email": "john@example.com",
    "password": "wrongpassword"
}
```

**Expected response — Status `422`:**
```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": [
            "The provided credentials are incorrect."
        ]
    }
}
```

---

### Test 6 — Get Authenticated User (Protected Route)

| Setting | Value |
|---|---|
| Method | `GET` |
| URL | `http://api-authentication.test/api/user` |

**Headers:**
```
Accept:         application/json
Authorization:  Bearer 1|abc123def456ghi789...
```
(Paste your actual token from Test 1 or Test 4)

**In Postman:** You can go to the **Authorization** tab, select **Bearer Token**, and paste your token there instead of manually typing the header.

**Expected response — Status `200 OK`:**
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        ...
    }
}
```

---

### Test 7 — Get User Without Token (Negative Test)

Same as Test 6 but **remove the Authorization header entirely**.

**Expected response — Status `401 Unauthorized`:**
```json
{
    "message": "Unauthenticated."
}
```

> **Discussion point:** This demonstrates that the `auth:sanctum` middleware is working. Laravel checked for a valid token, found none, and returned 401 automatically — we wrote no code for this.

---

### Test 8 — Logout

| Setting | Value |
|---|---|
| Method | `POST` |
| URL | `http://api-authentication.test/api/logout` |

**Headers:**
```
Accept:         application/json
Authorization:  Bearer 1|abc123def456ghi789...
```

No request body needed.

**Expected response — Status `200 OK`:**
```json
{
    "message": "Logged out successfully."
}
```

> Check the `personal_access_tokens` table — all rows for this user should now be deleted.

---

### Test 9 — Use Token After Logout (Negative Test)

Repeat Test 6 using the **same token you just logged out with**.

**Expected response — Status `401 Unauthorized`:**
```json
{
    "message": "Unauthenticated."
}
```

> **Why?** The token no longer exists in the `personal_access_tokens` table. Sanctum looked it up, found nothing, and rejected the request.

---

### Full Test Summary

| # | Request | Expected Status | What It Tests |
|---|---|---|---|
| 1 | POST /api/register (valid data) | 201 | Happy path registration |
| 2 | POST /api/register (same email) | 422 | Duplicate email validation |
| 3 | POST /api/register (no confirmation) | 422 | Password confirmation validation |
| 4 | POST /api/login (correct password) | 200 | Happy path login |
| 5 | POST /api/login (wrong password) | 422 | Failed authentication |
| 6 | GET /api/user (with token) | 200 | Protected route access |
| 7 | GET /api/user (no token) | 401 | Middleware blocking unauthenticated request |
| 8 | POST /api/logout (with token) | 200 | Token revocation |
| 9 | GET /api/user (revoked token) | 401 | Confirms token was deleted |

---

## Common Errors and Fixes

| Error Message | Likely Cause | Fix |
|---|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running | Start MySQL via Herd or your DB manager |
| `SQLSTATE[HY000] [1049] Unknown database 'api-demo'` | Database not created yet | Create the `api-demo` database (see Step 1) |
| `SQLSTATE[HY000] [1045] Access denied for user 'root'` | Wrong MySQL credentials | Double-check `.env` username and password |
| Returns HTML instead of JSON errors | Missing `Accept: application/json` header | Add the header in Postman |
| `401 Unauthenticated` on protected route | Wrong or missing Bearer token | Copy the token exactly, no extra spaces or quotes |
| `Method not found: createToken()` | `HasApiTokens` trait not added | Verify User model has `use HasApiTokens` |
| `Route [api.xxx] not defined` | Route not in `routes/api.php` | Check spelling and that `AuthController` is imported |
| `Class 'App\Http\Controllers\API\AuthController' not found` | Wrong namespace or file not saved | Verify file is at `app/Http/Controllers/API/AuthController.php` |
| `422 password field confirmation does not match` | Forgot `password_confirmation` in Postman | Add the `password_confirmation` key to your JSON body |

---

## Summary and Next Steps

### What We Built

You now have a fully functioning token-based API authentication system:

```
POST /api/register  →  Create account + get token
POST /api/login     →  Authenticate + get token
GET  /api/user      →  Protected: see who is logged in
POST /api/logout    →  Protected: revoke all tokens
```

### How It All Connects

```
Postman Request
    │
    ▼
routes/api.php        ← decides which controller to call
    │
    ▼
AuthController        ← runs validation, business logic
    │
    ▼
User Model            ← HasApiTokens enables token creation
    │
    ▼
personal_access_tokens table  ← Sanctum stores token hashes here
```

### What You Can Explore Next

- **Token expiration** — set `expiration` in `config/sanctum.php` to auto-expire tokens after N minutes
- **Token abilities** — create tokens with specific permissions: `createToken('editor', ['posts:edit'])`
- **Per-device logout** — use `currentAccessToken()->delete()` instead of `tokens()->delete()`
- **Adding more resources** — create `ProductController`, `PostController`, etc. following the same pattern
- **API versioning** — prefix routes with `/v1/` for future-proof APIs
- **Laravel Passport** — for OAuth2 flows (more complex than Sanctum, used for third-party auth)

---

*Built for Integrative Programming and Technologies — Laravel 12 + Sanctum v4*
