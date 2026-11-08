# Laravel Project Guidelines

## Before Making Changes
- Inspect the relevant files before proposing or changing code.
- Confirm the Laravel and PHP versions from the project files; do not assume them.
- Do not assume that a model scope, relationship, service, cache key, database constraint, route, or frontend behavior exists. Verify it in the codebase first.
- Follow the project's existing conventions unless there is a clear reason to change them.
- Keep changes focused on the requested task.

## Controllers
- Keep controllers focused on coordinating HTTP requests and responses.
- Use Form Requests for request validation.
- Use API Resources to format API responses.
- Move reusable business rules and complex operations into an appropriate service or action.
- Do not create a service for every trivial operation; use the existing project structure where appropriate.
- Use dependency injection for services and actions.
- When using an API Resource, ensure the class exists, is properly imported (use statement), and contains all fields expected by the feature tests.

## Queries and Models
- Put reusable query constraints in model scopes or dedicated query classes when appropriate.
- Eager-load relationships when needed to prevent N+1 queries.
- Verify that relationships and scopes exist before calling them.
- Do not claim that deletes cascade unless the model configuration or database migrations confirm it.

## Database and Cache
- Use database transactions when multiple related writes must succeed or fail together.
- Use row locks only when they address a real concurrency concern, and verify that the database supports the required behavior.
- Add database constraints for invariants that must hold under concurrent requests.
- Verify cache keys and cache-reading code before adding cache invalidation.
- When a transaction is involved, ensure cache invalidation or other side effects happen only after the transaction commits.
- Apply row locks (lockForUpdate) inside transactions specifically when validating strict business invariants under concurrent requests (e.g., preventing duplicate creation or checking allowable status transitions).

## Testing
- Inspect the existing test setup and follow its conventions.
- Add or update tests for changed behavior, including validation failures and relevant edge cases.
- Test authorization and HTTP status codes for API endpoints where applicable.
- Test database constraints and concurrent-sensitive rules at the appropriate level.
- Run the relevant tests after making changes.
- If practical, run the full test suite and the project's formatter or static-analysis tools.
- Report which commands were run and whether they passed.
- Do not claim tests passed if they were not run.
- If a test cannot be run, state why and identify what still needs verification.

## Final Response
- Summarize the files changed and the purpose of each change.
- Mention important assumptions only if they were verified.
- Report test commands and their results.
- Clearly identify any remaining checks or limitations.
- Provide the exact file paths and line numbers (if applicable) for every change made.


