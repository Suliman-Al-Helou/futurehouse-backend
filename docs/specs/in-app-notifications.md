# In-App Notifications System (Bell Icon)

**Status:** In Progress  
**Repos:** `backendCourse`, `frontend-course`

## Problem Statement
Authenticated users (Students, Instructors, Admins) currently have no in-app notification center to alert them about course enrollments, upcoming Zoom meetings, or newly published lessons.

## Solution
Implement a database-backed in-app notification system using Laravel's built-in `notifications` table (`database` channel) and a `NotificationBell` component in Next.js powered by TanStack Query v5 smart polling (`refetchInterval: 60_000`).

## User Stories
- As a Student, I receive an in-app notification when my course enrollment is confirmed, when a Zoom meeting is scheduled/updated, or when a new lesson is published in an enrolled course.
- As an Authenticated User (Student, Instructor, Admin), I see an unread badge on the Bell icon, can open the dropdown to view recent notifications, mark individual notifications as read, or mark all as read.

## Implementation Decisions
- **Delivery:** Smart polling via TanStack Query (`refetchInterval: 60_000`, `refetchOnWindowFocus: true`) — no WebSocket server required for MVP.
- **API Contract (`auth:sanctum`):**
  - `GET /api/notifications` -> `{ data: NotificationItem[], meta: { unread_count: number, current_page: number, last_page: number } }` (max 20 per page).
  - `POST /api/notifications/{id}/read` -> marks a single notification owned by `auth()->id()` as read.
  - `POST /api/notifications/read-all` -> marks all unread notifications owned by `auth()->id()` as read.
- **Notification Payload (`data` JSON):** `{ title: string, body: string, type: 'enrollment' | 'zoom_meeting' | 'lesson', action_url?: string }`.
- **Placement:** `NotificationBell` rendered in Student, Instructor, and Admin headers.

## Testing Decisions (Edge-Cases-First TDD)
- **Backend (`backendCourse`):**
  - Unauthenticated requests to `/api/notifications*` return `401`.
  - User A cannot mark User B's notification as read (`404` / `403`).
  - `per_page` is strictly bounded (max 20).
  - `read-all` only marks the authenticated user's notifications.
  - Events (Enrollment, ZoomMeeting, Lesson) dispatch database notifications to the target enrolled students.
- **Frontend (`frontend-course`):**
  - `NotificationBell` renders unread count badge when `unread_count > 0` and hides it when `0`.
  - Clicking an unread notification triggers `markAsRead` mutation.
  - Clicking "Mark all as read" triggers `markAllAsRead` mutation.

## Out of Scope
- Real-time WebSockets (Laravel Reverb / Pusher).
- Push notifications (FCM / Web Push).
- Email digests.
