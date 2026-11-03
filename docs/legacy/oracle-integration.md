# Oracle Integration Notes

The production target is Oracle with Oracle Spatial.

Current status:

- Laravel 13 scaffold is ready.
- `config/database.php` contains an `oracle` connection placeholder.
- `yajra/laravel-oci8` is installed for Laravel 13.
- `.env` contains the Oracle username/password provided for local setup.
- `php artisan oracle:check` is available to test the configured Oracle connection.
- Local development still uses SQLite until the Oracle schema and client libraries are available.
- The local machine currently emits an `oci8.so` warning because Oracle Instant Client is missing at the configured path.

Before production integration:

1. Install Oracle Instant Client on the deployment host.
2. Enable `oci8` / `pdo_oci` as required by the selected Laravel Oracle driver.
3. Provide either `DB_TNS` or `DB_HOST` plus `DB_SERVICE_NAME`.
4. Run `php artisan oracle:check`.
5. Map municipality tables/views for drivers, vehicles, employees, and places through repository classes.
6. Use Oracle Spatial for route geometry and distance analysis where available.
