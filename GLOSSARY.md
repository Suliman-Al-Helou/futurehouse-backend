# Authentication & Authorization Glossary

Canonical domain terminology and architectural concepts governing identity verification, token lifecycles, and authorization layers across the backend platform.

## Authentication Terms

**Sanctum Token**:
A cryptographically secure, hashed bearer token issued via Laravel Sanctum to authenticate API consumers without server-side session state.
_Avoid_: API Key, Session Cookie, JWT

**Token Bloat**:
The uncontrolled accumulation of obsolete or abandoned authentication tokens within the persistence store due to issuing credentials without pruning.
_Avoid_: Memory leak, Token overflow, Session clutter

**Single Session Policy**:
An authentication lifecycle rule that purges all existing active tokens for a user before issuing a new token upon successful authentication.
_Avoid_: Session concurrency, Multi-login, Sticky session

**Dual-Tier Throttling**:
A compound rate-limiting strategy that restricts incoming requests concurrently by a specific identity credential and by origin IP address.
_Avoid_: Simple throttling, IP-only rate limit

## Authorization Terms

**RBAC (Role-Based Access Control)**:
An access management model where operational privileges and system capabilities are assigned strictly based on defined user roles rather than individual user identities.
_Avoid_: User-level permissions, Ad-hoc privilege assignment

**Role Enum**:
A strictly-typed PHP backed enumeration (`UserRole`) that defines all valid user classifications and prevents invalid role states in the domain model. In the pure Spatie architecture, enum cases map directly to Spatie role names (`roles.name`).
_Avoid_: Hardcoded role strings, Magic strings, Role constants

**Resource Policy**:
A domain authorization class (`UserPolicy`) encapsulating entity-level permission rules and resource ownership boundaries before permitting state changes.
_Avoid_: Controller permission checks, In-method role assertions, Hardcoded role checking

**HTTP Middleware Gate**:
A request boundary interceptor (`RoleMiddleware`, `AdminMiddleware`) that validates role prerequisites at the HTTP routing layer prior to controller execution.
_Avoid_: Inline route gates, Manual request guards

**Permission**:
A granular, atomic operational privilege (e.g., `'courses.create'`, `'users.view'`) assigned to roles via Spatie Laravel Permission, governing specific actions within the system independently of high-level user classifications.
_Avoid_: User-level hardcoded flags, Ad-hoc boolean column permissions, Role-only checks

**Super Admin Bypass**:
A centralized pre-authorization hook (`Gate::before`) that evaluates authenticated identity privileges before any individual policy method executes, granting administrative users universal access across all abilities while returning `null` for non-administrators to cascade to standard policy evaluations.
_Avoid_: Repetitive policy `before()` hooks, In-controller admin if-checks, Blanket authorization disabling

**Perimeter Gate vs Entity Policy**:
The architectural separation of concerns between HTTP routing boundaries and domain data integrity: Perimeter Gates (Middleware such as `RoleMiddleware`, `AdminMiddleware`, `auth:sanctum`) evaluate coarse request-level access before controller dispatch, whereas Entity Policies (`UserPolicy`, `CoursePolicy`, `LessonPolicy`) enforce fine-grained, state-dependent resource ownership rules on specific Eloquent models.
_Avoid_: Checking resource ownership in middleware, In-controller role validation, Blurring route gates with data policies

## BFF & Frontend Integration Terms

**BFF Token Isolation**:
An architectural pattern where upstream backend bearer tokens (Sanctum) are securely trapped and encrypted within the server-side Next.js BFF layer (`iron-session`), exposing only sanitized identity state and opaque HttpOnly cookies to client runtimes.
_Avoid_: LocalStorage token storage, Client-side Bearer header injection

**Hydration Gate**:
A server-side route boundary enforcement pattern (Server Component Layout) that validates authentication and role claims before streaming HTML markup to the client, preventing visual flicker (flash of unauthenticated content) during page loads.
_Avoid_: Client-only auth checks, UseEffect route guards, FOUC-inducing redirects

**Edge Session Guard**:
A deterministic session invalidation mechanism implemented in proxy handlers that captures upstream `401 Unauthorized` responses and synchronizes state by immediately dispatching expired cookie invalidation headers (`Set-Cookie: max-age=0`) to the browser.
_Avoid_: Stale client cookies, Asynchronous zombie sessions, Silent proxy passthrough

**Session Resealing**:
The automatic decryption, mutation, and re-encryption of HttpOnly session cookies during in-flight state mutations (such as user profile updates) without forcing user re-authentication.
_Avoid_: Stale session cache, Manual re-login, Client cookie editing

**Role-Based Post-Login Dispatcher**:
A deterministic navigation handler that evaluates authenticated identity claims to direct users to their privileged root interface (`/admin` vs `/dashboard`) while respecting sanitized redirection intents.
_Avoid_: Hardcoded dashboard redirect, Blind route dispatching


