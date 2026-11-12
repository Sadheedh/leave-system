# Leave Request System

Staff request leave, managers approve or reject it, and leave balances update automatically.

This is a one-week project, built to learn PHP and Laravel. It covers role-based access, an approval workflow, database transactions and an audit history, and it has a small JSON API.

## Roles

| Role | What they can do |
|---|---|
| Staff | Submit a leave request, see their own requests and balances, cancel their own requests. |
| Manager | Everything staff can do, plus **Team Requests**: approve or reject pending requests from their direct reports only. |
| Admin | Can open **Team Requests** and approve or reject pending requests from their own direct reports only. Has a read-only **Admin page** at `/admin` (admins only) listing users and leave types. |

There is no admin bypass in the policy. An admin who is not the manager of the person who made a request gets a 403, and an admin cannot approve their own request.

The Team Requests pages are guarded by the `review-leave-requests` gate (managers and admins), and each individual decision is checked by `LeaveRequestPolicy`.

## Request workflow

```
submit                     -> PENDING
PENDING  --approve-------> APPROVED   (the requester's manager or admin)
PENDING  --reject--------> REJECTED   (the requester's manager or admin)
PENDING  --cancel-------> CANCELLED   (the requester)
APPROVED --cancel-------> CANCELLED   (the requester, only while start_date is after today)
```

Rejected and cancelled requests cannot be changed again.

## Business rules and where they are enforced

| Rule | Enforced in |
|---|---|
| Only the requester's direct manager can approve or reject a request. There is no admin bypass. | `LeaveRequestPolicy::approve()` and `reject()`; route gate in `routes/web.php` |
| Nobody can approve their own request, even a manager. | `LeaveRequestPolicy::approve()` |
| Only pending requests can be approved or rejected. The status is checked again after the row is locked (`lockForUpdate`), so a double click cannot approve twice. | `LeaveRequestPolicy` and `TeamLeaveRequestController::review()` inside `DB::transaction()` |
| Approving deducts the working days from the balance. Cancelling an approved request gives them back. The status change, balance change and history event happen in one transaction. | `TeamLeaveRequestController::review()` and `LeaveRequestController::cancel()` |
| The balance used is the one for the year of `start_date`. | `TeamLeaveRequestController::review()` and `LeaveRequestController::cancel()` |
| The balance is checked when the request is submitted and again at approval. If it is too low at approval, the approval is refused and the request stays pending. | `StoreLeaveRequestRequest::after()` and `TeamLeaveRequestController::review()` |
| Cancellation: a pending request can always be cancelled. An approved request can be cancelled only while its start date is after today. | `LeaveRequestPolicy::cancel()` |
| Every status change writes a row to `leave_request_events` (the history). | `LeaveRequestController::store()`, `cancel()` and `TeamLeaveRequestController::review()` |
| Working days are counted with both ends included, skipping Friday and Saturday. The count is stored in `leave_requests.days`. | `App\Services\WorkingDayCalculator`, called from `StoreLeaveRequestRequest::workingDays()` |
| Dates: start cannot be before today, end cannot be before start, and end cannot be after 31 December of the current year. A range with no working days is refused. | `StoreLeaveRequestRequest::rules()` and `after()` |
| Reason is required (max 1000 characters). The review note is optional (max 1000 characters). | `StoreLeaveRequestRequest` and `ReviewLeaveRequestRequest` |
| `role` and `manager_id` are not mass assignable, so a form cannot make someone an admin. On `LeaveRequest`, `user_id`, `status` and the review fields are also not mass assignable. | `App\Models\User` and `App\Models\LeaveRequest` (`#[Fillable]`) |
| The owner and the owner's manager can open a request's detail page. Nobody else can. | `LeaveRequestPolicy::view()` |

Because the end date cannot go past the end of the current year, every request falls within the current year. The submission check and the approval check therefore use the same balance year in practice.

## Data model

| Table | Purpose |
|---|---|
| `users` | Breeze's table, plus `role` (`staff`, `manager`, `admin`; default `staff`) and `manager_id` (nullable, points to `users.id`). |
| `leave_types` | `name` and `default_days`. |
| `leave_balances` | One row per user, leave type and year, with `days_remaining`. A unique index on (`user_id`, `leave_type_id`, `year`). |
| `leave_requests` | Dates, stored `days`, `reason`, `status` (`pending`, `approved`, `rejected`, `cancelled`), and the review fields (`reviewed_by`, `reviewed_at`, `review_note`). |
| `leave_request_events` | The history: `from_status`, `to_status`, `user_id` (who made the change), `note`. |

The Sanctum `personal_access_tokens` table and the Breeze/Laravel tables (sessions, cache, jobs) are also created by the migrations.

Web pages: `/dashboard`, `/leave-requests` (own requests), `/leave-requests/create`, `/leave-requests/{id}` (detail and history), `/balances` (current year only), `/team-requests` (managers and admins), `/admin` (admins only, read-only), and the Breeze profile page.

## Stack

| Part | Choice | Source |
|---|---|---|
| Language | PHP ^8.3 | `composer.json` |
| Framework | Laravel ^13.17 | `composer.json` |
| Login and registration | Laravel Breeze ^2.4, Blade views | `composer.json`, `resources/views` |
| API tokens | Laravel Sanctum ^4.0 | `composer.json` |
| Tests | Pest ^5.3 with `pestphp/pest-plugin-laravel` ^5.0 | `composer.json`, `tests/` |
| Database | MySQL for running the app; in-memory SQLite for tests | `.env.example`, `phpunit.xml` |
| Frontend build | Vite, Tailwind CSS, Alpine.js | `package.json` |

Tests run with `APP_ENV=testing` on an in-memory SQLite database, as set in `phpunit.xml`.

## Running it locally

These steps use MySQL. The `.env.example` file is set to SQLite by default, so the `.env` file must be changed (step 4).

Requirements: PHP 8.3 or later, Composer, Node.js with npm, and a running MySQL server.

1. Clone the repository and go into it:

   ```bash
   git clone https://github.com/Sadheedh/leave-system.git
   cd leave-system
   ```

2. Install the PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create the environment file and an application key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

   In PowerShell, `copy` works as written. In Git Bash or macOS/Linux shells, use `cp .env.example .env`.

4. Open `.env` and change the database settings to MySQL. The `.env.example` file sets `DB_CONNECTION=sqlite` and has the MySQL lines commented out:

   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=leave_system
   DB_USERNAME=your_mysql_user
   DB_PASSWORD=your_mysql_password
   ```

   Use your own MySQL username and password. The `.env` file is listed in `.gitignore`, so the credentials are not committed.

5. Create an empty database called `leave_system` in MySQL, for example:

   ```sql
   CREATE DATABASE leave_system;
   ```

6. Create the tables and load the seed data. This drops and recreates all tables, so it is safe to re-run on this project database:

   ```bash
   php artisan migrate:fresh --seed
   ```

7. Start the app with the `dev` script from `composer.json`:

   ```bash
   composer run dev
   ```

   Then open http://127.0.0.1:8000 and log in with one of the seeded accounts below.

Fallback: run the server and the Vite dev server in two terminals:

```bash
php artisan serve
npm run dev
```

### Running the tests

```bash
php artisan test
```

## Seeded accounts

The seeder (`database/seeders/DatabaseSeeder.php`) creates these users. **The password for every account is `password`.**

| Name | Email | Role | Reports to |
|---|---|---|---|
| Ahmed Admin | admin@example.com | admin | (none) |
| Mariyam Manager | manager1@example.com | manager | Ahmed Admin |
| Ibrahim Manager | manager2@example.com | manager | Ahmed Admin |
| Staff One | staff1@example.com | staff | Mariyam Manager |
| Staff Two | staff2@example.com | staff | Mariyam Manager |
| Staff Three | staff3@example.com | staff | Mariyam Manager |
| Staff Four | staff4@example.com | staff | Ibrahim Manager |
| Staff Five | staff5@example.com | staff | Ibrahim Manager |
| Staff Six | staff6@example.com | staff | Ibrahim Manager |

The seeder also creates three leave types, each with a balance for every user for the year the seeder runs (the calendar year of `now()`):

| Leave type | Default days |
|---|---|
| Annual | 30 |
| Sick | 30 |
| Family Responsibility | 10 |

Each user starts with a balance equal to the default days for each type. That is 9 users x 3 types = 27 balance rows.

## API

The API uses Sanctum bearer tokens. There is no login endpoint. Create a token for a seeded user in Tinker:

```bash
php artisan tinker
```

Then, at the Tinker prompt:

```php
App\Models\User::where('email', 'staff1@example.com')->first()->createToken('demo')->plainTextToken;
```

Copy the returned token and use it in place of `<token>` below. Do not commit a real token.

| Method | Path | Returns |
|---|---|---|
| GET | `/api/leave-requests` | The signed-in user's own leave requests, newest first. |
| GET | `/api/leave-balances` | The signed-in user's own balances for every year, newest year first, then by leave type. |
| GET | `/api/user` | The signed-in user. This is the default route from the Sanctum install. |

Both list endpoints return a `data` array. Each item has these fields:

`GET /api/leave-requests`: `id`, `leave_type` (name), `start_date`, `end_date` (YYYY-MM-DD), `days`, `reason`, `status`, `reviewed_at` (ISO 8601, or null), `review_note`, `reviewer` (name, or null).

`GET /api/leave-balances`: `leave_type` (name), `year`, `days_remaining`.

Example request:

```bash
curl -H "Accept: application/json" -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/leave-requests
```

In Windows PowerShell 5.1, `curl` is an alias for `Invoke-WebRequest`, so use `curl.exe` with the same arguments.

A request without a valid token gets a 401 response:

```json
{"message":"Unauthenticated."}
```

## Tests

Run with `php artisan test`. The tests are in `tests/Feature` and `tests/Unit`.

| File | What it covers |
|---|---|
| `Feature/LeaveRequestTest.php` | Submitting a request (working days stored, pending event written); refusals for a request over the balance, a weekend-only range, a leave type with no balance, and invalid input; users see only their own requests; guests are sent to login. |
| `Feature/LeaveRequestLifecycleTest.php` | Approval deducts days; rejection does not; cancelling an approved request restores them; cancelling a pending request does not touch the balance; a full submit, approve, cancel run with three history events; approval refused when the balance is too low or missing; cancellation refused for others, for rejected or cancelled requests, and for approved requests that start today or earlier; detail page visible only to the owner and the owner's manager; a repeat approval is refused. |
| `Feature/TeamLeaveRequestTest.php` | Team Requests access; managers see only their direct reports' pending requests; staff cannot approve or reject; a manager who is not the requester's manager gets a 403; self-approval refused; non-pending requests cannot be reviewed; admins can approve a manager's request but not their own; review note length limit; 404 for an unknown request. |
| `Feature/LeaveBalanceTest.php` | The `/balances` page shows the current year and hides other years. |
| `Feature/LeaveCoreRulesAndApiTest.php` | Core rules (submit, team approval, cross-team refusal, self-approval, deduction and restore) and the API: 401 without a token, own data only, exact JSON field lists, and eager loading. |
| `Feature/AdminPageTest.php` | The `/admin` page: guests redirected to login, staff and managers refused, admins allowed; it lists users with role and manager, and lists leave types with default days. |
| `Feature/Auth/*.php` | Breeze's authentication tests: login, logout, registration, password reset, email verification, password confirmation and password update. |
| `Feature/ProfileTest.php` | Breeze's profile page tests. |
| `Feature/ExampleTest.php` | Laravel's sample feature test. |
| `Unit/WorkingDayCalculatorTest.php` | Working-day counts for several ranges, for example Sunday to Thursday is 5 days and a Friday-to-Saturday range is 0. |
| `Unit/ExampleTest.php` | Pest's sample unit test. |

## Out of scope

The project deliberately leaves these out:

- Email or SMS notifications
- Public holiday calendar
- Half-day leave
- File attachments (for example medical certificates)
- A React or Vue frontend
- Multi-level approval chains
- .NET

## AI assistance

This project was built with AI assistance (Claude Code). The repository contains `CLAUDE.md` and `AGENTS.md`, which are Laravel Boost guideline files for AI coding tools. Laravel Boost is listed as a dev dependency in `composer.json`.
