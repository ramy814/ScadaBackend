# Database Tables & Relationships

## Custom Tables (Managed by Laravel Migrations)

### WELLS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | Primary key |
| WELL_NUM | VARCHAR | Well number / ID |
| NAME | VARCHAR | Well name |
| QUARTER_OID | INTEGER NULL | FK to GAZADATA.QUARTERS |
| DAMAGE_STATUS | VARCHAR | Damage status (سليمة,متضررة,أعيد تأهيلها,مدمرة) |
| OPERATIONAL_STATUS | VARCHAR | Operational status (يعمل,متوقف,تحت الصيانة) |
| INSTALLATION_DATE | DATE | Legacy compatibility field; not exposed in the general create/edit form |
| LAST_MAINTENANCE_DATE | DATE | Legacy compatibility field; not exposed in the general create/edit form |
| WELL_DEPTH | DECIMAL | Depth in meters |
| PUMP_TYPE | VARCHAR | `submersible` or `vertical` |
| PUMP_POWER_HP | DECIMAL | Pump power in horsepower |
| CAPACITY | DECIMAL | Capacity in m³ |
| SALINITY | DECIMAL | Salinity level |
| POWER_SOURCE | VARCHAR | `electricity`, `solar`, or `generator` |
| LATITUDE | DECIMAL(10,8) | GPS latitude |
| LONGITUDE | DECIMAL(11,8) | GPS longitude |
| IS_ACTIVE | BOOLEAN | Default 1 |
| TANK_SHAPE | VARCHAR | اسطواني عمودي, اسطواني أفقي, مكعب, or `Daily Tank` |
| TANK_MATERIAL | VARCHAR | `HDPE`, معدن, or فيبرجلاس |
| TANK_DIAMETER | DECIMAL | Fuel tank diameter (m) |
| TANK_HEIGHT | DECIMAL | Fuel tank height (m); used as cylinder length for horizontal tanks |
| CHLORINATION_SYSTEM | VARCHAR | يوجد or لا يوجد |
| CHLORINATION_STATUS | VARCHAR | يعمل or لا يعمل |
| CHLORINE_TANK_CAPACITY| DECIMAL | Chlorine tank capacity (liters) |
| CHLORINE_TANK_TYPE | VARCHAR | اسطواني عمودي or مكعب |
| CHLORINE_TANK_MATERIAL| VARCHAR | HDPE أزرق or بلاستيك شفاف |
| CHLORINE_PUMP_TYPE | VARCHAR | Chlorine pump type |
| COOLING_TANK_CAPACITY | DECIMAL | 220 or 500 liters |
| COOLING_TANK_TYPE | VARCHAR | HDPE أزرق or HDPE أسود |
| COOLING_SYSTEM | VARCHAR | اوتوماتيك, يدوي, غير متوفر, غاطس, or اوتوماتيك يحتاج صيانة |
| COOLING_LINE_TYPE | VARCHAR | حديد سكب or HDPE |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

Fuel balance calculation (`h = DIESEL_HEIGHT_CM / 100`, dimensions in meters):

- Vertical cylinder: `liters = π × (TANK_DIAMETER / 2)² × min(h, TANK_HEIGHT) × 1000`.
- Horizontal cylinder: `TANK_HEIGHT` is the cylinder length. With `r = TANK_DIAMETER / 2` and `h` clamped to `0..TANK_DIAMETER`, segment area is `r² × acos((r-h)/r) - (r-h) × sqrt(2rh-h²)`; multiply by `TANK_HEIGHT × 1000`.
- Cube/square-base tank: `liters = TANK_DIAMETER² × min(h, TANK_HEIGHT) × 1000`.
- Dashboard inventory rows, totals, and reports use this geometric result as the actual balance. They do not fall back to `AVAILABLE_FUEL_LITERS`; incomplete tank dimensions/shape/height produce an unavailable balance instead of an invented value.
- The previous reading is selected per well using `INVENTORY_DATE`, then `OID` as the tie-breaker. Dashboard date filters must not remove the historical row used as the previous reading.
- Diesel report operating hours: `current GENERATOR_HOURS_READING - previous GENERATOR_HOURS_READING`. If the current counter is lower, the result is left blank and a reset/inconsistent-reading note is shown.
- Approved fuel supplied between inventories: sum `G_STORE.FUEL_LOG_DETAILS.APPROVED_GALLONS` where the linked `G_STORE.FUEL_LOG.FILL_UP_DATE` is after the previous inventory and through the current inventory.
- Actual fuel consumed: `previous calculated balance + approved supply - current calculated balance`; actual rate is this value divided by operating hours. Negative results are treated as inconsistent data and left blank with a note.
- Water production: `operating hours × WELLS.CAPACITY`.
- Calculated diesel consumption: `operating hours × G_STORE.GENERATOR.FUEL_CONSUMPTION`.

### WELL_MEDIA
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | Primary key |
| WELL_OID | INTEGER FK | References WELLS; `NO ACTION` blocks deletion while media exists |
| CATEGORY | VARCHAR(50) | `pump`, `fuel_tank`, `cooling`, `valves_parts`, or `well_room` |
| LABEL | VARCHAR NULL | User-provided image name/description |
| FILE_PATH | VARCHAR(500) | Private `local` disk path; never expose directly |
| ORIGINAL_NAME | VARCHAR | Original uploaded filename |
| MIME_TYPE | VARCHAR(100) NULL | Validated image MIME type |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### WELL_ACTIVITY_MEDIA
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | Primary key |
| ACTIVITY_TYPE | VARCHAR(30) | `reading` or `notification` |
| ACTIVITY_OID | INTEGER | Parent `WELL_READINGS.OID` or `WELL_NOTIFICATIONS.OID` according to type |
| CLIENT_LOCAL_ID | VARCHAR(64) UNIQUE | Mobile photo UUID used for idempotent retry |
| FILE_PATH | VARCHAR(500) | Private `local` disk path; never expose directly |
| ORIGINAL_NAME | VARCHAR | Original mobile filename |
| MIME_TYPE | VARCHAR(100) | Validated JPEG/PNG/WebP MIME type |
| FILE_SIZE | INTEGER | Uploaded compressed size in bytes |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

Activity media rules:

- Files are uploaded only after the parent row exists and the server returned its OID.
- There is no client-supplied inspector identity; the API verifies the authenticated inspector owns `ACTIVITY_OID`.
- `CLIENT_LOCAL_ID` prevents duplicate rows/files when the offline queue retries the same upload.
- Images stay private and are delivered to authorized dashboard users through the authenticated media controller.

### TEAMS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| NAME | VARCHAR | Team name |
| IS_ACTIVE | BOOLEAN | Default 1 |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### TEAM_WELLS (Pivot)
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| TEAM_OID | INTEGER FK | References TEAMS |
| WELL_OID | INTEGER FK | References WELLS |
| CREATED_AT | TIMESTAMP | |

### INSPECTORS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| NAME | VARCHAR | Inspector name |
| PHONE | VARCHAR UNIQUE | Phone = username |
| PASSWORD | VARCHAR | Hashed via bcrypt |
| IS_ACTIVE | BOOLEAN | Default 1 |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### INSPECTOR_TEAMS (Pivot)
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| TEAM_OID | INTEGER FK | References TEAMS |
| CREATED_AT | TIMESTAMP | |

### WELL_GENERATORS (Pivot)
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| GENERATOR_OID | INTEGER | References G_STORE.GENERATOR (external) |
| FUEL_CONSUMPTION_LPH | DECIMAL(10,2) NULL | Approved generator consumption for this well in liters/hour; falls back to G_STORE when null |
| CONSUMPTION_TOLERANCE_LPH | DECIMAL(10,2) NULL | Allowed difference above the approved rate in liters/hour; application default is zero |
| CREATED_AT | TIMESTAMP | |

### WELL_READINGS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| FLOW_RATE | DECIMAL | Flow rate in m³ |
| GENERATOR_HOURS_READING | DECIMAL | Generator operating-hours meter; required by the mobile form |
| PUMP_HOURS_READING | DECIMAL NULL | Pump operating-hours meter; optional in mobile entry and sync |
| CHLORINE | DECIMAL | Chlorine in liters |
| SALINITY | DECIMAL | Salinity reading |
| QUALITY | VARCHAR | excellent/good/acceptable/poor |
| READING_DATE | TIMESTAMP | Inspector-selected field date; source of truth for chronological reading data, dashboard counts, filters, latest/previous readings, and reports |
| LATITUDE | DECIMAL NULL | Inspector GPS |
| LONGITUDE | DECIMAL NULL | Inspector GPS |
| SYNCED_AT | TIMESTAMP NULL | When synced from mobile |
| CLIENT_LOCAL_ID | VARCHAR(64) NULL UNIQUE | Stable mobile reading UUID; prevents duplicate parent rows on retry |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

Reading date rules:

- The mobile form defaults to today and permits an inclusive range from today through 7 calendar days earlier.
- `READING_DATE` is independent of `CREATED_AT` and `SYNCED_AT`; offline records may be created or synchronized later.
- Sort readings by `READING_DATE DESC, OID DESC` for latest-first displays. Use the reverse direction where a chronological production report requires it.
- Reading production exports select one current row per `WELL_OID`: the latest row inside the complete selected filter set by `READING_DATE DESC, OID DESC`.
- The preceding reading used for the report calculation is the immediately earlier historical row for the same well and may be outside the selected export period.
- The final production report is active-well-driven: all `WELLS.IS_ACTIVE = 1` rows appear even if no filtered reading exists. Missing readings keep their report values null and are never represented as zero production or zero meter readings.
- Report supervision statuses are application-level interpretations of availability and meter consistency only; no chemical/water-quality threshold is stored or inferred by this report.

### DIESEL_INVENTORIES
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| GENERATOR_OID | INTEGER NULL | References G_STORE.GENERATOR (external) |
| INVENTORY_DATE | TIMESTAMP | When diesel inventory was taken |
| GENERATOR_HOURS_READING | DECIMAL NULL | Generator operating-hours meter; legacy rows may be null, but required and non-negative for every new mobile/API inventory |
| DIESEL_HEIGHT_CM | DECIMAL NULL | Diesel height in centimeters; legacy rows may be null, but required and non-negative for every new mobile/API inventory |
| AVAILABLE_FUEL_LITERS | DECIMAL | Actual available fuel balance in liters |
| LATITUDE | DECIMAL NULL | Inspector GPS |
| LONGITUDE | DECIMAL NULL | Inspector GPS |
| NOTES | TEXT NULL | Field notes |
| SYNCED_AT | TIMESTAMP NULL | When synced from mobile |
| CLIENT_LOCAL_ID | VARCHAR(64) NULL UNIQUE | Stable mobile inventory UUID; prevents duplicate rows on retry |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

New diesel inventory rules:

- Flutter and `/api/wells/diesel-inventories/sync` require both `GENERATOR_HOURS_READING` and `DIESEL_HEIGHT_CM`.
- Empty, non-numeric, and negative values are invalid. Numeric zero is valid.
- Keep the Oracle columns nullable only for historical compatibility; application code must not create a new null value.

Diesel report classification and formulas:

- Internal tank: `UPPER(TRIM(WELLS.TANK_SHAPE)) = 'DAILY TANK'`.
- External tank: `TANK_SHAPE IS NOT NULL` and not `Daily Tank`. Oracle empty strings are null; do not add `TRIM(TANK_SHAPE) <> ''`, because comparison with an Oracle empty string evaluates to null and removes valid rows.
- Unclassified wells are excluded from both groups until `TANK_SHAPE` is completed.
- Internal actual rate = current approved fill quantity ÷ the positive meter difference from the immediately preceding approved fill.
- External current/previous balances are calculated from tank geometry and diesel height. Actual rate = `(previous balance + approved supply - current balance) ÷ positive operating hours`.
- Upper consumption limit = per-assignment approved rate + non-negative tolerance. Actual values above it are high; equality is normal.
- Missing inputs are unavailable. Reverse/zero/impossible meter intervals and negative calculated consumption are invalid.

### READING_GENERATOR_LOGS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| READING_OID | INTEGER FK | References WELL_READINGS |
| GENERATOR_OID | INTEGER | Generator reference |
| CURRENT_READING | DECIMAL | Current meter reading |
| GALLONS | DECIMAL | Legacy source column; displayed as the latest diesel fill quantity in liters |
| CREATED_AT | TIMESTAMP | |

### NOTIFICATION_TYPES
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| NAME | VARCHAR | Notification type name |
| ICON | VARCHAR | Stable UI icon code (`danger`, `warning`, `success`, `info`, `bell`, `maint`, `fuel`, `water`, `power`, `repair`) |
| EMOJI | VARCHAR | Legacy/compatibility field; store the same safe icon code, not raw emoji |
| COLOR | VARCHAR | Hex color such as `#ef4444` |
| PRIORITY | INTEGER | Lower number means higher priority |
| IS_ACTIVE | INTEGER/BOOLEAN | Store as `1` or `0`; Oracle may return it as string |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### WELL_NOTIFICATIONS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| NOTIFICATION_TYPE_OID | INTEGER FK | References NOTIFICATION_TYPES |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| MESSAGE | TEXT | Inspector notification body |
| IS_READ | INTEGER/BOOLEAN | Store as `1` or `0` |
| READ_AT | TIMESTAMP NULL | |
| CLIENT_LOCAL_ID | VARCHAR(64) NULL UNIQUE | Stable mobile signal UUID; prevents duplicate parent rows on retry |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

## External Oracle Tables (Read-Only)

### G_STORE.GENERATOR
Active generators query:
```sql
SELECT OID, NAME, GENERATOR_NUM, CURRENT_READING, GALLONS, FUEL_CONSUMPTION
FROM (
    SELECT g.OID, g.NAME, g.GENERATOR_NUM, f.CURRENT_READING, f.GALLONS,
           g.FUEL_CONSUMPTION,
           ROW_NUMBER() OVER (PARTITION BY g.OID ORDER BY f.OID DESC) rn
    FROM G_STORE.GENERATOR g
    LEFT JOIN G_STORE.FUEL_LOG_DETAILS f
      ON g.OID = f.ASSET_OID
     AND f.ASSET_TYPE = 401
    WHERE g.IS_ACTIVE = 1
      AND g.TYPE_OID = 128
) WHERE rn = 1
ORDER BY GENERATOR_NUM;
```

The Wells generator card uses the latest joined values with these Arabic labels:
- `CURRENT_READING`: **آخر قراءة لعداد المولد (ساعة)**.
- `GALLONS`: **آخر كمية تعبئة سولار (لتر)**. The legacy column name does not define the display unit.

Diesel reports additionally read:
- `ENGINE_CAPACITY`: generator capacity in KVA.
- `FUEL_CONSUMPTION`: nominal diesel consumption in liters/hour.
- `FUEL_LOG_DETAILS.APPROVED_GALLONS`: approved supplied fuel quantity; despite the legacy column name, the operational report treats this source value as liters.
- `FUEL_LOG_DETAILS.CURRENT_READING`: generator meter reading stored as text; validate it numerically in the service.
- `FUEL_LOG_DETAILS.ASSET_TYPE = 401`: mandatory discriminator for generator rows. `ASSET_OID` overlaps other asset types and is not a safe join key by itself.
- `FUEL_LOG.FILL_UP_DATE`: delivery date used to place approved supply between consecutive inventory readings.
- `FUEL_LOG.CANCEL_DATE`: canceled fills are excluded.

For `Daily Tank`, calculate `LAG` across every approved, non-canceled generator fill before applying the requested date filter. Do not filter out a missing/malformed `CURRENT_READING` before `LAG`; the immediately preceding fill must remain the preceding fill and make the interval unavailable instead of being skipped.

### GAZADATA.QUARTERS
```sql
SELECT q.OId, q.NAME FROM GAZADATA.QUARTERS q
```

## Relationships
- Well ↔ Team: Many-to-Many (via TEAM_WELLS)
- Well ↔ Generator: Many-to-Many (via WELL_GENERATORS)
- Inspector ↔ Team: Many-to-Many (via INSPECTOR_TEAMS)
- Well → Readings: One-to-Many
- Well → DieselInventories: One-to-Many
- Well → WellMedia: One-to-Many
- Inspector → Readings: One-to-Many
- Inspector → DieselInventories: One-to-Many
- Reading → GeneratorLogs: One-to-Many
- Reading → WellActivityMedia: One-to-Many filtered by `ACTIVITY_TYPE = reading`
- NotificationType → WellNotifications: One-to-Many
- Well → WellNotifications: One-to-Many
- Inspector → WellNotifications: One-to-Many
- WellNotification → WellActivityMedia: One-to-Many filtered by `ACTIVITY_TYPE = notification`

## Deletion Rules

- All foreign keys from `INSPECTORS`, `TEAMS`, and `WELLS` are enabled and validated with Oracle `DELETE_RULE = NO ACTION`.
- Never cascade inspector deletion into `WELL_READINGS`, `DIESEL_INVENTORIES`, `WELL_NOTIFICATIONS`, or `INSPECTOR_TEAMS`.
- Never cascade team deletion into `TEAM_WELLS` or `INSPECTOR_TEAMS`.
- Never cascade well deletion into readings, inventories, notifications, teams, generators, or media.
- Application services perform a detailed preflight for a friendly message, while the foreign key remains the non-bypassable final guard.
- Physical deletion is only for completely unlinked setup mistakes. Use `IS_ACTIVE = 0` for operational records with history.
