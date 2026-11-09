# ADR 0004: Pure Spatie Permission Integration and Unified Authorization Architecture

## Status
Accepted

## Context
ADR 0002 established a role-based access control (RBAC) foundation using the `UserRole` backed enum and basic policy ownership checks. However, administrative pre-authorization logic was duplicated across individual policies (`UserPolicy::before`, `CoursePolicy::before`, `LessonPolicy::before`), and orphaned policy artifacts (`AdminPolicy.php`) existed without route or model bindings.

Because this application is a greenfield project with zero legacy production data, retaining a dual-lookup backward-compatibility bridge and preserving the legacy `users.role` column with its PostgreSQL check constraint (`users_role_check CHECK (role IN ('student','admin'))`) introduces unnecessary technical debt and duplicate sources of truth. The platform therefore unifies access control directly onto pure `spatie/laravel-permission`.

## Decision Drivers
* **Pure Spatie RBAC Architecture:** Eliminate dual-lookup shims, relying 100% on Spatie's relational tables (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`).
* **Clean Database Schema:** Completely drop the legacy `users.role` column and PostgreSQL check constraint, removing obsolete schema constraints.
* **DRY Authorization Logic:** Eliminate duplicated `before()` methods across domain policies by centralizing super-admin bypass in a single global `Gate::before` callback (`$user->hasRole('admin')`).
* **Dead Code Elimination:** Remove obsolete and unreferenced policy classes (`AdminPolicy.php`) and custom trait overrides on `User`.
* **Architectural Separation of Concerns:** Reinforce the distinction between HTTP Perimeter Gates (Middleware) and Entity Policies (Domain access control).

## Decision

1. **Spatie Permission Schema Integration & Legacy Column Removal:**
   * Published `config/permission.php` and created migration for Spatie tables (`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`).
   * Created migration `2026_11_04_140000_drop_role_column_from_users_table.php` to drop `users_role_check` constraint (PostgreSQL) and drop the `role` column from `users`.
   * Seeded standard roles (`admin`, `student`) into Spatie's `roles` table.

2. **Pure Spatie User Model (`App\Models\User`):**
   * Incorporated `Spatie\Permission\Traits\HasRoles` into `User` without any custom overrides or method aliasing.
   * Defined query scopes using native Spatie builders:
     * `scopeStudents`: `$query->role('student')`
     * `scopeNonAdmin`: `$query->withoutRole('admin')`
   * Simplified helper methods:
     * `isAdmin()`: checks `$this->hasRole('admin')`
     * `isStudent()`: checks `$this->hasRole('student')`
   * Provided a clean `role` accessor returning `$this->getRoleNames()->first() ?? 'student'` to preserve API resource contracts (`UserResource`, `UserAdminResource`).

3. **Unified Global Super Admin Bypass (`AppServiceProvider`):**
   * Registered a centralized `Gate::before` callback in `AppServiceProvider::boot()`:
     ```php
     Gate::before(function ($user, string $ability) {
         if ($user instanceof User && $user->hasRole('admin')) {
             return true;
         }

         return null;
     });
     ```
   * Returns `true` unconditionally for administrative identities and `null` for other identities to allow downstream policy rules to evaluate normally.

4. **UserFactory & Seeder Modernization:**
   * Updated `UserFactory` with `admin()` and `student()` states that assign Spatie roles via `$user->syncRoles(...)`.
   * Added an `afterCreating` configure hook assigning `'student'` by default when no role has been explicitly assigned.
   * Updated `DatabaseSeeder` and `DemoSeeder` to assign Spatie roles instead of inserting into the legacy column.

5. **Policy Layer Cleanup:**
   * Redundant `before()` methods removed from `App\Policies\UserPolicy`, `App\Policies\CoursePolicy`, and `App\Policies\LessonPolicy`, delegating all admin pre-authorization to the global gate.
   * Deleted orphaned `App\Policies\AdminPolicy.php`.
   * Maintained explicit model-to-policy bindings in `AppServiceProvider`.

6. **Perimeter Gate vs Entity Policy Governance:**
   * **Perimeter Gates (Middleware):** `RoleMiddleware` and `AdminMiddleware` guard route ingress at HTTP boundaries using `$user->hasAnyRole(...)` and `$user->isAdmin()`.
   * **Entity Policies:** Domain policies (`UserPolicy`, `CoursePolicy`, `LessonPolicy`) govern individual model instances and resource ownership (`$user->id === $resource->user_id`).

## Consequences
* **Positive:**
  * Clean, single source of truth for roles and permissions backed by Spatie tables.
  * Single-point super-admin bypass eliminating duplicate logic across multiple policy files.
  * Removal of legacy database constraints and unnecessary bridge code.
  * Zero regressions across test suite with 100% pure Spatie semantics.
* **Negative:**
  * Requires managing Spatie permission cache lifecycle (`PermissionRegistrar::forgetCachedPermissions()`) during tests and seeders.
