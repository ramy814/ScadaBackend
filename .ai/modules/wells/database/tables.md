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
| INSTALLATION_DATE | DATE | |
| LAST_MAINTENANCE_DATE | DATE | |
| WELL_DEPTH | DECIMAL | Depth in meters |
| PUMP_TYPE | VARCHAR | 'centrifugal' or 'submersible' |
| CAPACITY | DECIMAL | Capacity in m³ |
| SALINITY | DECIMAL | Salinity level |
| POWER_SOURCE | VARCHAR | 'electricity' or 'solar' |
| LATITUDE | DECIMAL(10,8) | GPS latitude |
| LONGITUDE | DECIMAL(11,8) | GPS longitude |
| IS_ACTIVE | BOOLEAN | Default 1 |
| TANK_SHAPE | VARCHAR | Fuel tank shape |
| TANK_MATERIAL | VARCHAR | Fuel tank material |
| TANK_DIAMETER | DECIMAL | Fuel tank diameter (m) |
| TANK_HEIGHT | DECIMAL | Fuel tank height (m) |
| CHLORINATION_SYSTEM | VARCHAR | Chlorination system type |
| CHLORINE_TANK_CAPACITY| DECIMAL | Chlorine tank capacity (liters) |
| CHLORINE_TANK_TYPE | VARCHAR | Chlorine tank type |
| CHLORINE_TANK_MATERIAL| VARCHAR | Chlorine tank material |
| CHLORINE_PUMP_MODEL | VARCHAR | Chlorine pump model |
| CHLORINE_PUMP_TYPE | VARCHAR | Chlorine pump type |
| COOLING_TANK_CAPACITY | DECIMAL | Cooling tank capacity (liters) |
| COOLING_TANK_TYPE | VARCHAR | Cooling tank type |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

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
| CREATED_AT | TIMESTAMP | |

### WELL_READINGS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| FLOW_RATE | DECIMAL | Flow rate in m³ |
| CHLORINE | DECIMAL | Chlorine in liters |
| SALINITY | DECIMAL | Salinity reading |
| QUALITY | VARCHAR | excellent/good/acceptable/poor |
| READING_DATE | TIMESTAMP | When reading was taken |
| LATITUDE | DECIMAL NULL | Inspector GPS |
| LONGITUDE | DECIMAL NULL | Inspector GPS |
| SYNCED_AT | TIMESTAMP NULL | When synced from mobile |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### DIESEL_INVENTORIES
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| WELL_OID | INTEGER FK | References WELLS |
| INSPECTOR_OID | INTEGER FK | References INSPECTORS |
| GENERATOR_OID | INTEGER NULL | References G_STORE.GENERATOR (external) |
| INVENTORY_DATE | TIMESTAMP | When diesel inventory was taken |
| GENERATOR_HOURS_READING | DECIMAL NULL | Generator operating hours meter |
| DIESEL_HEIGHT_CM | DECIMAL NULL | Diesel height inside tank in centimeters |
| AVAILABLE_FUEL_LITERS | DECIMAL | Actual available fuel balance in liters |
| LATITUDE | DECIMAL NULL | Inspector GPS |
| LONGITUDE | DECIMAL NULL | Inspector GPS |
| NOTES | TEXT NULL | Field notes |
| SYNCED_AT | TIMESTAMP NULL | When synced from mobile |
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

### READING_GENERATOR_LOGS
| Column | Type | Notes |
|--------|------|-------|
| OID | INTEGER PK AUTO | |
| READING_OID | INTEGER FK | References WELL_READINGS |
| GENERATOR_OID | INTEGER | Generator reference |
| CURRENT_READING | DECIMAL | Current meter reading |
| GALLONS | DECIMAL | Solar fuel amount |
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
| CREATED_AT | TIMESTAMP | |
| UPDATED_AT | TIMESTAMP | |

## External Oracle Tables (Read-Only)

### G_STORE.GENERATOR
Active generators query:
```sql
SELECT OID, NAME, GENERATOR_NUM, CURRENT_READING, GALLONS
FROM (
    SELECT g.OID, g.NAME, g.GENERATOR_NUM, f.CURRENT_READING, f.GALLONS,
           ROW_NUMBER() OVER (PARTITION BY g.OID ORDER BY f.OID DESC) rn
    FROM G_STORE.GENERATOR g
    LEFT JOIN G_STORE.FUEL_LOG_DETAILS f ON g.OID = f.ASSET_OID
    WHERE g.IS_ACTIVE = 1
      AND g.TYPE_OID = 128
) WHERE rn = 1
ORDER BY GENERATOR_NUM;
```

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
- Inspector → Readings: One-to-Many
- Inspector → DieselInventories: One-to-Many
- Reading → GeneratorLogs: One-to-Many
- NotificationType → WellNotifications: One-to-Many
- Well → WellNotifications: One-to-Many
- Inspector → WellNotifications: One-to-Many
