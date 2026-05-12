# Fleet Management Backend Rules

This Laravel 13 backend serves Gaza Municipality fleet tracking.

Core rules:

- Keep controllers thin; place business logic in services.
- Keep Oracle schema details behind repositories until the final schema is provided.
- The driver app is offline-first. Never design an endpoint that requires live connectivity during a trip.
- Never confirm local deletion unless the server has stored the trip, all point batches, point count, and checksum.
- Every administrative approval/rejection/change must be auditable.
- Driver APIs are Arabic-product oriented, but code identifiers stay English.
- Use FormRequest for non-trivial inputs.
- Use API Resources for stable response contracts.
- Oracle Spatial is the target spatial engine; SQLite migrations are development scaffolding only.

