# Valves Database Architecture

## External Source Table

### `EME.WATER_VALVES`
Authoritative source for municipality water network records used by the valves module.
- `W4_GENERAL_ID`: Required identifier used by `App\Models\Valves\Valve` as the primary key.
- `W4_STREET_NO`: Street number used for display/location text.
- `W4_VALVE_TYPE`: Valve type display component.
- `W4_VALVE_DIAMETER`: Diameter display component.
- `W4_MANUFACTURER`: Manufacturer.
- `W4_OPERATION`: Source operation value.
- `W4_STATUS`: Source status from EME.
- `W4_PROJECT_NO`: Project number.
- `W4_SOURCE`: Source/area descriptor.
- `W4_QUALITY`: Source quality value.
- `W4_VALVE_NO`: Valve number.
- `W4_VALVE_OPEN`: Open/closed source value where populated.
- `GEOMETRY`: Oracle Spatial geometry. Do not select this with normal `select *`/Eloquent queries because PDO may throw `ORA-00932`.
- Web-map coordinates are attached separately by `ValveRepository::forMap()` using `SDO_CS.TRANSFORM(..., 4326)` and `SDO_UTIL.GETVERTICES`. `ValveService::getMapValves()` accepts only coordinates inside the Gaza bounding box (`31.15..31.65` latitude, `34.15..34.65` longitude); corrupt spatial outliers remain untouched in Oracle and are omitted only from the web payload.

There must be no local managed `VALVES` table. Migration `2026_06_30_000009_drop_wrong_valves_table.php` removes the incorrectly-created table.

## Managed Tables (Oracle CONTROL_UNIT schema)

Latest migration status: valve teams, inspectors, logs, maintenance reports, and the wrong-table cleanup migration have been run successfully.

### `VALVE_LOGS`
- `OID`: Primary key.
- `VALVE_OID`: Stores `EME.WATER_VALVES.W4_GENERAL_ID`; no local foreign key to `VALVES`.
- `USER_OID`: ID mapping to the SSO user (`MogSsoUser`).
- `ACTION`: The recorded action.
- `NOTE`: Description.
- `SMS_RAW`: Raw text (if triggered via SMS).
- `ACTION_AT`: Timestamp.

### `VALVE_MAINTENANCE_REPORTS`
- `OID`: Primary key.
- `VALVE_OID`: Stores `EME.WATER_VALVES.W4_GENERAL_ID`; no local foreign key to `VALVES`.
- `REPORTED_BY`: Mapped to `MogSsoUser` ID.
- `DESCRIPTION`: Issue details.
- `PRIORITY`: Level of urgency.
- `STATUS`: Status (`pending`, `in_progress`, `resolved`).
- `RESOLVED_BY`: Mapped to `MogSsoUser` ID.
- `RESOLVED_AT`: Resolution timestamp.

### `VALVE_TEAMS`
- `OID`: Primary key.
- `NAME`: Field team name.
- `IS_ACTIVE`: Whether the team is active.
- `ASSIGN_ALL_VALVES`: When true, the team grants inspectors access to all current and future valves without requiring rows in `VALVE_TEAM_VALVES`.
- `CREATED_AT`, `UPDATED_AT`: Timestamps.

### `VALVE_TEAM_VALVES`
- `OID`: Primary key.
- `TEAM_OID`: Foreign key to `VALVE_TEAMS`.
- `VALVE_OID`: Stores `EME.WATER_VALVES.W4_GENERAL_ID`; no local foreign key to `VALVES`.
- `CREATED_AT`: Assignment timestamp.
- Unique pair: `TEAM_OID`, `VALVE_OID`.

### `VALVE_INSPECTORS`
- `OID`: Primary key.
- `NAME`: Inspector name.
- `PHONE`: Unique mobile number and app username.
- `PASSWORD`: Hashed password.
- `IS_ACTIVE`: Whether the inspector can log in.
- `CREATED_AT`, `UPDATED_AT`: Timestamps.

### `VALVE_INSPECTOR_TEAMS`
- `OID`: Primary key.
- `INSPECTOR_OID`: Foreign key to `VALVE_INSPECTORS`.
- `TEAM_OID`: Foreign key to `VALVE_TEAMS`.
- `CREATED_AT`: Assignment timestamp.
- Unique pair: `INSPECTOR_OID`, `TEAM_OID`.

## Relationships
- ValveTeam ↔ Valve: Many-to-many through `VALVE_TEAM_VALVES`.
- If `VALVE_TEAMS.ASSIGN_ALL_VALVES = true`, treat the team as assigned to all valves and ignore the pivot rows for access filtering.
- ValveInspector ↔ ValveTeam: Many-to-many through `VALVE_INSPECTOR_TEAMS`.
- Valve inspectors and teams are separate from wells inspectors and teams.
