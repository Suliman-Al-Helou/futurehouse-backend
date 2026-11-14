# 📋 Feature Specification: Zoom Meetings & Video Assets Integration

## 1. Intent & Business Value
Enables administrators to attach external video replays (YouTube, Bunny, Cloudflare) to courses and completed Zoom meetings. Decouples live meeting scheduling from persisted video media, allowing reusability in course curricula and post-meeting student replay.

## 2. Implemented Architecture
- **Persistence Store (`video_assets` table):**
  - `course_id`: Foreign key to `courses` (cascade on delete).
  - `zoom_meeting_id`: Nullable foreign key to `zoom_meetings` (null on delete).
  - `title`: Video asset title.
  - `description`: Optional notes/summary.
  - `provider`: Video host (`youtube` [default], `bunny`, `cloudflare`).
  - `provider_video_id`: Clean sanitized external provider ID (e.g. 11-char YouTube ID).
  - `duration_seconds`: Video duration in seconds (default: 0).
  - `type`: Asset category (`recording` | `lesson`).
  - `created_by`: Admin user who attached the asset.
  - Composite Index: `(course_id, created_at)`.
  - Foreign Key Index: `zoom_meeting_id`.

## 3. API Contract (Admin Domain)
- `GET /api/admin/video-assets` — Filters: `course_id`, `zoom_meeting_id`, `type`.
- `POST /api/admin/video-assets` — Creates video asset (auto-sanitizes YouTube URLs: watch, embed, live, shorts to 11-char IDs).
- `GET /api/admin/video-assets/{id}` — Shows detailed record with relations.
- `PUT /api/admin/video-assets/{id}` — Updates asset metadata.
- `DELETE /api/admin/video-assets/{id}` — Removes asset record.

## 4. YouTube OAuth 2.0 & Ingestion Pipeline
- **Security & Token Encryption:**
  - Refresh tokens stored in `platform_settings` are encrypted at rest with AES-256-GCM (`Crypt::encryptString`).
  - Google OAuth `state` parameter generated with 32-character random hex per session (`Cache::put("yt_oauth_state_{state}", $adminId, 15min)`) to protect against CSRF attacks. Single-use consumption on callback.
  - Upstream token revocation on Google servers (`$client->revokeToken($refreshToken)`) upon disconnect.
- **Automated Course Playlists:**
  - Each course possesses an optional `youtube_playlist_id`. Newly uploaded videos are automatically appended to this unlisted playlist.
- **Chunked Resumable Ingestion:**
  - `POST /api/admin/video-assets/upload-chunk` receives 256 KB multiple binary chunks, reassembles files without path traversal, and dispatches `UploadVideoToYouTubeJob`.
