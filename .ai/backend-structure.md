# Backend Folder Structure

The backend follows a customized but standard Laravel 11 structure.

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            # Controllers for the Vue SPA (Dashboard, Reports)
│   │   ├── Api/              # Controllers for the Flutter App
│   │   └── Auth/             # Controllers handling SSO and SMS Login
│   ├── Middleware/
│   │   └── HandleInertiaRequests.php # Injects global URLs into Vue
│   └── Requests/             # FormRequests for validation
├── Models/                   # Eloquent Models (Trip, TripPoint)
├── Repositories/             # Encapsulates all Database Queries
│   └── MunicipalityVehicleRepository.php # Only place where Oracle is queried
├── Services/                 # Business Logic layer
│   ├── TripSyncService.php   # Idempotency, snapshots, point batching
│   ├── VirtualFuelService.php# Estimates fuel usage based on GPS
│   └── AdminTripReviewService.php # Handles approve/reject/clarify logic
└── Providers/
    └── AppServiceProvider.php

config/
└── municipality.php          # Configuration mapping for Oracle table/column names

database/
└── migrations/               # SQLite/PGSQL schema for managed data

routes/
├── web.php                   # Inertia SPA routes & SSO
└── api.php                   # Sanctum protected routes for Flutter
