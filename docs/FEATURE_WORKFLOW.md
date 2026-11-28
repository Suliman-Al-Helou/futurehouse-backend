# Feature Development Workflow (Living Document)

> **Mandatory for every new feature** across `frontend-course` and `backendCourse`.  
> This is a **living document**: as we discover new patterns, constraints, or lessons learned while moving forward in the project, we append them here.

---

## Standard 4-Step Feature Lifecycle

1. **Grilling (`/grill-with-docs`)**
   - Resolve all architectural, security, and UX ambiguities before writing any code.
   - Do not assume decisions; verify library/framework best practices via `context7` and codebase state.

2. **To-Spec (`/to-spec`)**
   - Synthesize the agreed decisions into a structured spec (Problem Statement, Solution, User Stories, Implementation Decisions, Testing Decisions, Out of Scope).
   - **Dual-Sync Constraint**: Every spec MUST be saved **both**:
     - In the project under `docs/specs/`
     - In **Linear** (`@[plugin:linear]`) on the corresponding issue(s).

3. **To-Ticket (`docs/tickets/` + Linear)**
   - Break the spec into phased, actionable tickets in **Linear** (checking for existing issues first to avoid duplicates) and reference them in `docs/tickets/` when applicable.

4. **TDD — Tests First (`/tdd`)**
   - Write failing tests at the highest seams first (**Red**), implement the minimal code to pass (**Green**), and refactor cleanly (**Refactor**).

---

## Documentation & Linear Constraints
- Always check existing Linear issues before creating new ones; modify existing issues when they already cover the scope.
- Keep `Implementation Decisions` in specs free of volatile file paths or raw code snippets unless encoding a precise contract/schema.
- Every new rule discovered during development should be appended to this file so the workflow evolves with the project.
- **Edge-Cases-First TDD**: Write edge-case & boundary tests first (auth isolation, threshold boundaries, race/flush conditions) in both `frontend-course` and `backendCourse` before writing any implementation code.
- **Root-Cause Reproduction Test**: For bug-fix tickets, add a failing reproduction test at the exact boundary (backend policy + frontend event flush) before editing production code.
- **Dual-Sync Completion Gate**: A ticket is only considered complete when its tests pass AND both the Linear issue (`Done`) and `docs/specs/<ticket>.md` (`Status: Completed`) are updated.
- **Cross-Repo Contract Verification**: For any ticket touching API payloads or tenant/course settings, verify the backend Resource/FormRequest contract against the frontend TypeScript types before marking the ticket Done.
