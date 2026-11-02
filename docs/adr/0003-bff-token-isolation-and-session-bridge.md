# ADR 0003: BFF Token Isolation and Session Bridge Architecture

## Status
Accepted

## Context
The platform architecture consists of a Laravel 11 API backend (powered by Laravel Sanctum with enforced Single Session Policy per ADR 0001 and RBAC per ADR 0002) and a Next.js 15 App Router frontend. 

Exposing Sanctum Bearer tokens directly to browser JavaScript (via `localStorage` or accessible cookies) creates severe security vulnerabilities (XSS token theft) and makes single-device session invalidation difficult to synchronize. Furthermore, client-side route protection triggers authentication flashes (FOUC / flash of unauthorized content) while layouts mount and verify credentials asynchronously.

## Decision Drivers
* **Token Isolation:** Prevent any client-side JavaScript execution environment from ever accessing raw Sanctum Bearer tokens.
* **Zero-Flash Authorization:** Enforce authentication and role permissions strictly on the server boundary (Next.js Server Components / Layouts) before streaming bytes to the browser.
* **Deterministic Session Purge:** Ensure that whenever Laravel invalidates a token (returning `401 Unauthorized`), the Next.js BFF layer unconditionally purges the encrypted session cookie in the exact same HTTP response cycle (`Set-Cookie: Max-Age=0`).
* **Live Session Integrity:** Guarantee that disabled accounts (`is_active = false`) and permission updates take effect dynamically through upstream verification.
* **Continuous State Synchronization:** Keep server session state, database records, and client store in sync during profile mutations.

## Decision

1. **Backend-For-Frontend (BFF) Token Isolation:**
   * Sanctum tokens are received exclusively by Next.js Route Handlers (`/api/auth/login`, `/api/auth/register`).
   * The token is encrypted inside an `iron-session` cookie (`fh_session`) with `httpOnly: true`, `secure: true`, and `sameSite: 'lax'`.
   * Client-side JavaScript receives only sanitized identity state (`User` object containing `id`, `name`, `email`, `role`).

2. **Server-Side Layout Route Gates:**
   * `src/app/(student)/layout.tsx`: Evaluates `getIronSessionData()` on the server. If no active session exists, it issues a server-side `redirect('/login?redirect=/dashboard')`.
   * `src/app/(admin)/layout.tsx`: Evaluates `getIronSessionData()` on the server. If the user does not possess `role === 'admin'`, it intercepts execution and redirects the request away from administrative interfaces before rendering.
   * Deprecated and deleted orphaned `src/proxy.ts` to maintain Server Component Layouts as the sole perimeter defense.

3. **Atomic Session Eviction on 401 & Logout:**
   * The proxy Route Handler (`src/app/api/[...path]/route.ts`) inspects upstream Laravel responses.
   * If Laravel returns `401 Unauthorized` (e.g. session terminated on another device via ADR 0001), the proxy destroys the internal iron-session and explicitly appends an expired `Set-Cookie` header (`response.cookies.delete()`) to the outgoing `NextResponse`.
   * The logout Route Handler (`src/app/api/auth/logout/route.ts`) calls Laravel to revoke the token, destroys the local session, and attaches the explicit `Set-Cookie: Max-Age=0` header.

4. **Live `/auth/me` Revalidation:**
   * Next.js `/api/auth/me` verifies the encrypted token against Laravel's `/auth/me` endpoint upon client bootstrap.
   * If the account is deactivated (`is_active: false`) or the token is invalidated, the session is purged immediately.
   * If active, latest profile attributes (name, email, role) synchronize automatically into the encrypted session.

5. **Profile Mutation Session Resealing:**
   * Successful `PUT/PATCH /users/*` profile update responses passing through the BFF proxy automatically extract updated user models and reseal the `iron-session` cookie in transit.

6. **Post-Login Role Dispatcher & Sanitized Redirect Query:**
   * Login and registration forms validate the `?redirect=` query parameter against open redirect attacks (ensuring a leading `/` without `//`).
   * If absent, navigation dispatches automatically based on verified user role (`admin` -> `/admin`, `student` -> `/dashboard`).

## Consequences
* **Positive:**
  * Immune to client-side token exfiltration attacks via XSS.
  * Complete elimination of layout hydration flashes and visual flicker during navigation.
  * Immediate and synchronized session termination across both frontend and backend upon credential revocation or deactivation.
  * Seamless state continuity when users modify profile attributes without requiring re-login.
  * Eliminates dual-hop redirects for administrators logging in.
* **Negative:**
  * Requires Next.js server runtime to decrypt and proxy requests (incompatible with static SPA export).
  * Route handlers introduce a sub-millisecond proxy hop for API requests forwarded to Laravel.
