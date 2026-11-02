# ADR 0002: Role-Based and Resource-Ownership Authorization Architecture

## Status
Accepted

## Context
The application relies on Laravel Sanctum for API token authentication. Previously, role validation depended on loose string comparisons (`'admin'`, `'student'`) scattered across queries and ad-hoc middleware, with no formal layer protecting entity-level resource ownership (e.g., student modifying another student's profile). To guarantee robust multi-tenant boundaries and prevent privilege escalation, a multi-tiered authorization architecture was required to separate route perimeter defense from domain-level resource access control.

## Decision Drivers
* Standardize role authorization using strictly-typed PHP Backed Enums to eliminate magic strings and typos.
* Enforce Separation of Concerns (SoC) between HTTP perimeter gates and entity-level policy checks.
* Return standardized `403 Forbidden` JSON payloads for any unauthorized operation without information leakage.
* Ensure automated testability through comprehensive end-to-end Feature tests.

## Decision
We implemented a three-tier authorization architecture:

1. **Domain Layer (`App\Enums\UserRole`):**
   * Defined a backed enumeration (`UserRole: string`) encapsulating `ADMIN = 'admin'` and `STUDENT = 'student'`.
   * Bound the enum directly to `User::$casts` (`'role' => UserRole::class`).
   * Updated model helper methods (`isAdmin()`, `isStudent()`) and Eloquent query scopes (`scopeStudents()`, `scopeNonAdmin()`) to evaluate against the enum.

2. **Middleware Layer (`App\Http\Middleware\RoleMiddleware`):**
   * Intercepts incoming requests at the HTTP routing boundary before controller dispatch.
   * Rejects unauthenticated requests with `401 Unauthorized` and unauthorized roles with `403 Forbidden`.
   * Registered route middleware aliases (`admin`, `role`) in `bootstrap/app.php`.

3. **Policy Layer (`App\Policies\UserPolicy`):**
   * Encapsulates fine-grained resource ownership checks (`$user->id === $target->id`).
   * Implements a super-admin bypass via `before(User $user, string $ability)` granting administrators universal access.
   * Bound `User::class` to `UserPolicy::class` in `AppServiceProvider`.

4. **Rate Limiting Architectural Recommendation:**
   * User update endpoints (`PUT /api/users/{user}`) should enforce a throttle limit of `15 requests/minute` per authenticated identity to mitigate resource-exhaustion and brute-force update attacks.

## Consequences
* **Positive:**
  * Centralized, type-safe access control across routing and domain layers.
  * Complete elimination of magic strings and brittle conditional role checks.
  * Strict resource-ownership enforcement preventing cross-user profile tampering.
  * 100% automated test coverage in `tests/Feature/Auth/AuthorizationTest.php`.
* **Negative:**
  * Incremental maintenance overhead from dedicated files (`UserRole`, `RoleMiddleware`, `UserPolicy`, `UpdateUserRequest`).
  * Requires explicit unit/feature policy tests for any newly introduced domain resource.

