# Future House — Backend API

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/PostgreSQL-16%2F17-4169E1?style=flat-square&logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![Security Rating](https://img.shields.io/badge/SonarCloud-Security%20A-brightgreen?style=flat-square&logo=sonarcloud)](https://sonarcloud.io)
[![CI/CD Pipeline](https://img.shields.io/badge/CI%2FCD-GitHub%20Actions-2088FF?style=flat-square&logo=githubactions&logoColor=white)](https://github.com)

A high-performance, domain-driven RESTful API backend engineered for the **Future House** educational ecosystem. The platform coordinates curriculum distribution, student progression tracking, interactive task assessment, and live cohort Zoom classroom attendance with strict architectural guarantees.

---

## 🏛️ Architectural Overview & Problem-Solving Design

This platform was built to solve specific distributed learning and data-integrity challenges rather than relying on standard monolithic controller patterns:

```
[ HTTP Request ]
       │
       ▼
[ Perimeter Gate / Rate Limiting ] ──► (Throttled per operation: login, register, password-reset)
       │
       ▼
[ Sanctum Token Authentication ] ────► (State-isolated Bearer tokens / BFF Session Bridge)
       │
       ▼
[ Form Request Validation ] ─────────► (Strict type & payload sanitization before business logic)
       │
       ▼
[ Domain Controller Layer ] ─────────► (Thin orchestrators delegating directly to Services)
       │
       ├──► [ Policy / Gate Check ] ─► (Role/Ownership authorization + Super-Admin Bypass)
       │
       ├──► [ Business Service ] ────► (CourseService, TaskService, ZoomService, StatsService)
       │         │
       │         ├──► [ Query Objects ] ──► (StudentDashboardQuery, complex scopes)
       │         └──► [ PostgreSQL DB ] ──► (Hardened schemas, FK indexes, CHECK constraints)
       │
       ▼
[ API Resource Formatter ] ──────────► (Predictable contract responses, no raw model leakage)
```

### 1. Hardened Authentication & Perimeter Authorization
- **Token Isolation**: Uses Laravel Sanctum token-based authentication tailored for API-only and BFF (Backend-For-Frontend) architectures ([ADR 0001](file:///home/suliman/Documents/courses/backendCourse/docs/adr/0001-authentication-token-lifecycle.md) – [0003](file:///home/suliman/Documents/courses/backendCourse/docs/adr/0003-bff-token-isolation-and-session-bridge.md)).
- **Dual-Layer Authorization ([ADR 0004](file:///home/suliman/Documents/courses/backendCourse/docs/adr/0004-permission-and-authorization-architecture.md))**:
  - *Perimeter Gates* (`AdminMiddleware`, `RoleMiddleware`): Fast rejection at routing boundaries.
  - *Entity Policies* (`UserPolicy`, `CoursePolicy`, `LessonPolicy`): Granular, record-level ownership checks with a centralized `Gate::before` super-admin bypass.
- **Spatie RBAC Integration**: Dynamic permission and role resolution (`UserRole` backed enum) without fragile string column mutations.
- **Strict Rate Limiting**: Dedicated rate-limit throttles configured per sensitive endpoint (`register`, `login`, `forgot-password`, `reset-password`).

### 2. Database Integrity & Concurrency Guarantees
- **Case-Insensitive Uniqueness**: Native PostgreSQL `CHECK (email = lower(email))` guarantees canonical email storage and prevents casing-attack duplicate accounts.
- **Partial Unique Indexes**: Soft-deleted records (`deleted_at`) do not lock emails forever; active uniqueness is guaranteed while preserving historical auditing.
- **Query Optimization**: High-frequency relationship lookups (`course_id`, `user_id`, `lesson_id`) are indexed to eliminate full table scans during concurrent access.

### 3. Decoupled Service & Query Architecture
- **Zero Fat Controllers**: Monolithic logic was decomposed into single-responsibility administrative and student controllers.
- **Domain Services**: Encapsulated business flows (`CourseService`, `EnrollmentService`, `InstructorService`, `TaskService`, `ZoomService`, `StatsService`).
- **Dedicated Query Objects**: Complex statistical calculations (e.g., student progress metrics, completion ratios) are isolated into reusable query classes (`StudentDashboardQuery`).
- **Smart Cache Invalidation**: Automatic multi-layer cache purging (`ClearsCacheOnChange`, `ContentCache`, `FrontendCache`) supporting ISR on frontend clients.

---

## ⚡ Core Platform Capabilities

### 📚 Curriculum & Learning Engine
- **Hierarchical Course Tree**: Courses $\rightarrow$ Sections $\rightarrow$ Lessons $\rightarrow$ Interactive Tasks.
- **Access Control & Enrollment**: Automatic gating based on enrollment status, supporting both free discovery courses and restricted premium tracks.
- **Granular Lesson Progression**: Tracking of individual student lesson completions with real-time course progress summaries.

### 📝 Assessment & Task Submissions
- **Task Lifecycle**: Lesson-bound tasks supporting student submissions, file/link deliveries, attempt tracking, and review status management.

### 📹 Live Cohort & Zoom Attendance
- **Course-Bound Live Meetings**: Scheduled Zoom sessions linked to courses with strict access prerequisites.
- **Fraud-Resistant Attendance**: Automated verification of student enrollment before attendance logging, cohort roster reconciliation, and real-time attendance summaries.

### 👨‍🏫 Instructor & FAQ Portals
- **Instructor Profiles**: Bio, specialization, and course associations.
- **Curated Knowledge Base**: Categorized and searchable course FAQs.

### 📊 Operational & Student Analytics
- **Student Dashboard**: Aggregated personal stats (enrolled courses, active tasks, attendance rate, total progress).
- **Admin Control Tower**: High-level platform KPIs covering platform enrollments, user activity, course performance, and attendance rosters.

---

## 🛠️ Technology Stack

| Layer | Technology | Purpose |
| :--- | :--- | :--- |
| **Runtime & Framework** | PHP 8.4 / Laravel 13.x | High-performance modern backend runtime |
| **Authentication** | Laravel Sanctum | Token-based API authorization with strict lifecycle rules |
| **Role & Permissions** | Spatie Laravel Permission | Dynamic RBAC with centralized policies |
| **Database** | PostgreSQL 16+ | ACID transactional storage, check constraints, partial indexes |
| **Cache & Queue** | Redis / Database driver | Sub-millisecond data caching and async worker execution |
| **Observability** | Laravel Telescope & Sentry | Deep query introspection, error tracking, and APM |
| **Code Standards** | Laravel Pint | Automated PSR-12 and Laravel code style enforcement |
| **Static Analysis & Security** | SonarQube Cloud & Semgrep | Automated security rating, code smells, and vulnerability scanning |
| **CI / CD & Containers** | GitHub Actions & Docker | Multi-stage testing, PostgreSQL container parity, and GHCR builds |

---

## 🚀 Getting Started

### Prerequisites
- **PHP**: `^8.4` (with `pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `pcntl`)
- **Composer**: `^2.8`
- **PostgreSQL**: `^16` or higher
- **Node.js & NPM**: for auxiliary tooling (optional)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Suliman-Al-Helou/futurehouse-backend.git
   cd futurehouse-backend
   ```

2. **Install dependencies:**
   ```bash
   composer install --prefer-dist --optimize-autoloader
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Configure your PostgreSQL credentials in `.env` (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, etc.).*

4. **Execute migrations & seeders:**
   ```bash
   php artisan migrate --seed
   ```

5. **Start local development server:**
   ```bash
   php artisan serve --port=8000
   ```
   *The API health check will be live at `GET http://localhost:8000/api/health`.*

---

## 🧪 Testing & Quality Assurance

The suite guarantees database parity and feature verification via **PHPUnit** and **Laravel Pint**:

```bash
# Run automated feature & unit tests
php artisan test

# Verify code style and formatting
vendor/bin/pint --test

# Run security dependency audit
composer audit
```

---

## 📦 Containerization & Deployment

The application includes an optimized multi-stage [Dockerfile](file:///home/suliman/Documents/courses/backendCourse/Dockerfile) designed for minimal image footprint and fast cold starts:

```bash
# Build production Docker image
docker build -t futurehouse-backend:latest .

# Run container locally
docker run -d -p 8000:8000 --env-file .env futurehouse-backend:latest
```

Continuous Deployment (CD) automatically packages and pushes tagged production images to **GitHub Container Registry (`ghcr.io`)** upon merging to `main`.

---

## 📄 License
This project is open-sourced software licensed under the [MIT License](LICENSE).
