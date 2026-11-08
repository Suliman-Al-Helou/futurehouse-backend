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

## Laravel Boost Context
- Advanced framework guidelines and specialized tools are located inside the `.ai/guidelines/` and `.ai/rules/` directories.
- You must read and merge the local Boost guidelines before working.