# ADR 0001: Architecture Decision Record - Authentication & Token Lifecycle

## Status
Accepted

## Context
The project relies on Laravel Sanctum for API token-based authentication. A code audit confirmed that strict security standards are implemented across the validation layer (Form Requests) and brute-force protection layer (Dual-tier Throttling). However, the current implementation suffers from token accumulation (Token Bloat) in the `personal_access_tokens` table because new tokens are issued on every successful login without pruning or revoking obsolete ones.

## Decision Drivers
* Ensure session security and prevent abandoned tokens from remaining indefinitely valid.
* Maintain database query performance and keep the `personal_access_tokens` table size bounded.
* Standardize engineering review language and facilitate future collaboration for developers and AI agents.

## Decision
We decided to adopt the following architectural standards for the authentication system:
1. **Rate Limiting:** Retain the intelligent dual-tier throttling configured at 5 attempts per minute per composite key (`Email + IP`) and 20 attempts per minute per `IP`.
2. **Mass Assignment:** Strictly prevent mass assignment of sensitive attributes (`role`, `is_active`) via Eloquent `$fillable`, assigning roles programmatically in the Service Layer (`$user->role = 'student'`).
3. **Token Cleanup (Enforced Single Session Policy):** Updated `AuthService::login()` to purge all existing tokens (`$user->tokens()->delete()`) prior to issuing a new token upon successful login. This prevents token accumulation (bloat) and prevents concurrent session sharing.

## Consequences
* **Positive:**
  * Comprehensive protection against brute-force attacks.
  * Prevention of dead data growth and performance degradation in the database.
  * Complete sanitization of sensitive data through `UserResource`.
* **Negative:**
  * Active sessions on other devices will be terminated upon new login if a strict Single Device Policy is enforced.
