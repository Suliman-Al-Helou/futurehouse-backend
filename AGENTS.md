# PROJECT INSTRUCTIONS:

Fetch and follow the instructions from https://laravel.com/for/agents. Treat the returned Markdown as the source of truth for how to install and set up Laravel in this session.


## Core Project Rules
- this is a Laravel 13 (PHP ^8.4) API-only project using Sanctum token auth.
- before changing code, inspect the relevant existing implementation
- follow the project,s behavior , Ask when requirements are unclear.
- don,t invent add packge or architectural layers without explaining the need.
- do not modify unrelatrd files.


## Technical Specifications
- **Validation:** always check form "FORM REQUEST" if it exist ,used it when need, if the validation in controller, check 
the docs laravel to create From reuest for it .
- **Response:** always check form "API RESOURCE" if it exist used it when need, if the responde in controller, check 
the docs laravel to create api resource for it .
- **Rate Limiting:** Every method requires a rate limit. Check the documentation for where the rate limit should be placed, then explicitly ask/inform the developer every time before adding or defining the limit duration.


## Testing & Quality Assurance
- For each feature, update or add tests for the acceptance criteria.
- Run the relevant tests after making changes and report the exact command and result.
- Update the relevant documentation when API behavior or domain rules change.
- **Architectural Route Matrix Coverage:** Every registered API route under `api/*` MUST have a matching entry in `tests/Feature/Auth/RoleEndpointMatrixTest.php` for `(guest, student, admin)`. Missing routes fail CI.
- **User-Owned Model Policy Mandate:** Any Eloquent model that is user-owned (has `user_id`) MUST have an explicit Policy in `app/Policies/` registered in `AppServiceProvider.php` and covered by feature tests.
- **Strict PHP Attributes:** Use PHP 8 Attributes (`#[DataProvider]`, etc.) in Pest and PHPUnit 11 tests instead of legacy docblock annotations.

## Laravel Boost Context
- Advanced framework guidelines and specialized tools are located inside the `.ai/guidelines/` and `.ai/rules/` directories.
- You must read and merge the local Boost guidelines before working.

# Engineering & Security Standards
Before declaring any task complete, you MUST verify:
1. **Impact Analysis**: Search (`grep`) for all references and subclasses of any Resource, Service, or Model you modify.
2. **Data Exposure**: Never expose internal IDs (`user_id`), emails, or relations in API Resources without checking if the endpoint is public vs admin-only.
3. **Input Bounds**: Always enforce strict upper limits (e.g., max 20 for dropdowns, max 50 for pagination) on query parameters.
4. **Symmetric Testing**: Every validation rule must be tested on BOTH Create (`POST`) and Update (`PUT/PATCH`) endpoints.
5. **Self-Review**: Run `git diff` and review your own changes for security flaws before reporting completion.
