
# Role & Operational Mode
You are an expert backend engineer specializing in Laravel 12/13 API development. You must operate strictly in ANALYSIS MODE first. DO NOT write or generate any code in your first response. 

# Taks: 
- Analyze and prepare a execution plan to implement/review the Authentication system for this project based on the guidelines below. 

# CONTEXT & Scope:
- This is a Laravel 13 API project utilizing Laravel Sanctum for token-based authentication.
- You must inspect `routes/api.php` specifically looking for "login" and "register" endpoints.
- You must locate and inspect the existing `AuthController` methods using your Laravel Boost tools.
- DO NOT make any assumptions about existing codebase components (models, traits, or classes) without explicitly verifying them via the project files first.

# Functional Requirements
- Validate the login method inputs (`email` and `password`).
- Leverage Laravel Form Requests (`LoginRequest` / `RegisterRequest`) if they exist, or plan to create them if missing.
- Ensure clear, structured validation error payloads are prepared for the frontend client.
- Secure the endpoints: evaluate the necessary Rate Limiting (Throttle middleware) for both login and register methods, and explicitly present the proposed limit durations.
- Show clear error validation messages when the data fails validation.
- Ensure the submit button is disabled during the API request to prevent multiple submissions.

# Constraints & Admission Standards
- Follow the existing project structure, naming conventions, and coding style exactly.
- Handle all success and failed API responses using proper HTTP Status Codes and consistent JSON structures (e.g., using Eloquent API Resources where applicable).
- Keep code defensive against race conditions or multi-click request rushes from the frontend client.


# Response Shape (Mandatory Phase 1: Analysis Only)
Your response must strictly contain only the following sections without writing any implementation code:

1. **Requirements Gathering & Understanding:** Summarize exactly what you understood from the requirements based on your initial file inspections.
2. **Current Implementation Analysis:** State what you discovered in `routes/api.php`, `AuthController`, and the `User` model using your tools.
3. **Missing Information:** List any critical details, credentials, or architectural patterns that are currently missing or unclear in the codebase.
4. **Required Assumptions:** Clearly state any technical assumptions you are forced to make before proceeding.
5. **Risk Assessment:** Identify potential risks (security, performance, validation bottlenecks, or rate-limiting gaps).
6. **Proposed Implementation Plan:** Outline a step-by-step minimal blueprint of the files that will be created or changed.



