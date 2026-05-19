# Coding Rules & Conventions

## 1. PHP Version & Laravel
- **PHP**: 8.2 (Strictly, due to production server limitations).
- **Laravel**: 11.x
- Avoid using PHP 8.3+ features.

## 2. Controllers
- Keep controllers thin. Max 50-70 lines.
- Use dependency injection in the constructor for Services.
- All request validation must happen in a `FormRequest` class, not in the controller.
- Example:
```php
public function store(TripStoreRequest $request, TripSyncService $service)
{
    $trip = $service->storeTrip($request->user(), $request->validated());
    return new TripResource($trip);
}
```

## 3. Inertia & Vue 3
- We do **not** use the `ziggy-js` routing library.
- Instead, URLs are passed from Laravel to Vue via the `HandleInertiaRequests` middleware using `$page.props.urls`.
- In Vue 3, access URLs like this:
```javascript
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const loginUrl = page.props.urls.login;
```
- For forms handling, use Inertia's `useForm`.
- Implement visual loading states via `form.processing`.

## 4. Repositories
- Any query touching the Oracle Database must go into a Repository class (e.g., `MunicipalityVehicleRepository`).
- Repositories should return Collections or simple arrays, not raw Query Builders, to the Service layer.

## 5. Security
- Never trust input from the mobile app regarding its state. Always verify server-side (e.g., the points checksum, calculating wait times vs relying on app wait times).
- All admin actions must use POST/PUT/DELETE and be protected by the `auth` middleware.

## 6. Language
- The source code (variables, functions, classes, database tables) is exclusively in **English**.
- The User Interface (Views, Vue Components, Validation Messages, PDF generation) is exclusively in **Arabic**.
