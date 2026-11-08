# Architectural Decisions: Users Section

| ID | Decision | Why | Rejected Alternative |
| :--- | :--- | :--- | :--- |
| **D1** | `bigint` identity PK | The frontend types use `id: number`, and the existing data is numeric. Switching to UUID rewrites every type, route and Sanctum morph. Edit `ai_rules.md` to match, or the rule file keeps contradicting the code. | **UUID:** Better against ID enumeration, but the cost is high and the Policies already guard access. |
| **D2** | `CHECK (email = lower(email))` plus a partial unique index | The DB enforces case-insensitivity and allows re-registration after archiving. | **citext:** Works, but adds an extension dependency. <br>**unique(lower(email)):** Still allows mixed-case storage. |
| **D3** | `role` as a CHECK-constrained `varchar` | Two roles today. | **A roles table:** Introduce it when you need more than two roles or real permissions. |
| **D4** | `is_active` means suspended (reversible, blocks login). <br><br>`deleted_at` means archived (hidden, and the row is never physically deleted). | Two different business meanings. | **A single status column:** Valid later. |
| **D5** | Drop `remember_token` | Unused with token auth. | **Keep it “just in case”.** |
| **D6** | `timestamptz`, `NOT NULL` with `now()` defaults | Time zones are not a guess, and the DB guarantees the columns exist even for raw inserts. | **Laravel’s defaults:** Nullable, no TZ. |
| **D7** | Keep `morphs('tokenable')` | Sanctum expects it. | **A real FK:** Impossible with polymorphism. |

## Status (2026-10-31)
- D2, D4, D5: accepted, implemented in migration `2026_10_31_120000_harden_users_table`.
- D1: bigint kept. Update `ai_rules.md` to match.
- D3, D6, D7: not part of the auth task.