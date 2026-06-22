# Fleet Management System — Project Overview
> بلدية غزة | نظام إدارة الأسطول

## Core Purpose
The Fleet Management System is an enterprise application designed for Gaza Municipality to track, manage, and audit municipality vehicle trips. It consists of an administrative dashboard for monitoring and a driver-facing mobile application for trip execution.

## System Components
1. **Backend API (Laravel 11)**: The core server providing data storage, synchronization logic, and integration points.
2. **Admin Dashboard (Vue 3 + Inertia.js)**: A single-page application (SPA) embedded within the Laravel backend for administrators to review trips, manage locations, and generate reports.
3. **Driver Application (Flutter)**: A mobile application used by drivers to record trips and GPS paths, built with an Offline-First philosophy.
4. **Oracle Database (Production)**: The external source of truth for municipality assets (Vehicles, Employees, SSO).
5. **SQLite (Local/Dev)**: The local database used by the Laravel backend to store its own managed data (trips, paths, logs).

## Architectural Principles
- **Offline-First Resilience**: Drivers often operate in areas without network coverage. All driver-facing endpoints are designed to accept batches of data out-of-order and resolve them without requiring constant connectivity.
- **Idempotency**: All trip data and GPS batches use unique identifiers (`local_id` for trips, `sequence_number` for points) to prevent duplicates during intermittent network retries.
- **Auditable Operations**: All administrative actions (approving, rejecting, clarifying trips) are logged in the `audit_logs` table.
- **Thin Controllers**: Controllers only handle HTTP request routing and basic validation. Complex business logic resides in Services (e.g., `TripSyncService`).
- **Repository Pattern**: All queries to the external Oracle database are strictly encapsulated within Repositories.
- **Authoritative Trip Maps**: Administrative trip maps show the recorded GPS points without road matching. Visual direction aids may be derived in the browser, but stored coordinates and calculated trip data remain unchanged.

## Tech Stack
- **Framework**: Laravel 11.x
- **PHP**: 8.2
- **Database**: SQLite (Managed Data) + Oracle (External Data, via `yajra/laravel-oci8`)
- **Frontend Framework**: Vue 3 with Composition API
- **Frontend Integration**: Inertia.js (No Ziggy, URLs passed via Shared Props)
- **CSS Framework**: Custom CSS (No Tailwind)
- **PDF Generation**: mPDF
- **Authentication (Admin)**: MOG SSO (External SAML/OAuth via package)
- **Authentication (Driver)**: Custom SMS OTP issuing Sanctum Tokens

## Typical User Flow
1. A driver logs into the mobile app via SMS OTP.
2. The driver creates a new trip offline, starting the GPS tracker.
3. As the driver moves, GPS points are batched and uploaded to the server whenever the internet is available.
4. The driver ends the trip. The mobile app sends a confirmation payload with a checksum.
5. The backend verifies the checksum and marks the trip as `confirmed`.
6. An administrator logs into the dashboard via SSO.
7. The administrator reviews the trip path on a map, checks the points, and approves/rejects the trip.
