# Administrative Approval Flow

Manual assistant entries join this flow as `confirmed` / `pending` without GPS
points. They can be approved or rejected normally. `needs_clarification` is not
available for them because no driver-app session can receive or answer the
request; the backend enforces this in addition to hiding the action in Vue. The
supervisor reviews them in the dashboard's separate **الإدخال اليدوي** tab;
the default **رحلات التطبيق** tab never mixes them with driver-app trips. The
creating assistant may edit the entry only while it remains `pending`; approval
or rejection immediately makes it immutable in both the register UI and the
repository query. Before create or update, the backend reauthorizes the vehicle
through the assistant's station path with `VEHICLE_STAGES.ACCESS_TYPE IN (1, 3)` and requires the matched
`VEHICLE_STAGES.REMOVED_USER` to remain null; a removed station assignment can
neither appear in the form nor authorize a submitted vehicle ID.

Every supervisor review is also station scoped. The authenticated SSO
user must hold base `fleet` system access and have an active
`G_STORE.VEHICLE_STAGES` station link with `ACCESS_TYPE IN (2, 3)`. The dashboard
filters before pagination, and detail/approve/reject/clarify routes repeat the
live Oracle check. The assistant's station permission authorizes data entry only;
it never grants the later supervisor review scope. Entry/review are determined
automatically from Oracle, including navigation and runtime action checks; the
permission editor grants system entry only and displays the derived scope read-only.

## Assistant Approval Queue

The assistant register exposes **اعتماد الطلبات** when runtime
`fleet.manual-trips.approve` permits it. This requires the independent assistant
screen grant plus Oracle review scope `(2, 3)`, without dashboard permission.
The separate queue lists only completed manual requests for eligible vehicles,
including entries created by other assistants. Each pending row opens a native
confirmation dialog showing request details and an optional approval note.
Approval rechecks live vehicle scope, locks the row, and commits the state change
and audit together. Repeated approval or audit failure leaves no partial write.

## Goal
To audit all trips to ensure municipal resources (vehicles, fuel) are used appropriately according to the recorded mission.

## State Machine

1. **`pending` (Initial State)**
   - The trip has been synced from the mobile app and successfully `confirmed`.
   - Action: It appears in the admin dashboard under "Pending Reviews".
   - Admin views the recorded map, origin/destination, automatic stop points and durations, combined wait minutes, odometer readings, and technical tracking events.
   - The dashboard may display active uploaded trips for live supervision before confirmation, but approval/rejection still requires the finished confirmed trip workflow.
   - GPS disabled, airplane, shutdown, permission loss, tracking errors, and stale points are evidence requiring human review. None is sufficient by itself to label an action intentional.

2. **`needs_clarification` (Interaction)**
   - The admin notices a discrepancy (e.g., the path deviates wildly from the destination).
   - Admin clicks "Clarify" and inputs a `review_note`.
   - The trip state becomes `needs_clarification`.
   - An audit log is written.
   - The mobile app will poll or be notified that a trip requires attention.
   - The driver inputs a reason in the mobile app, hitting the `/api/driver/trips/{trip}/clarification-response` endpoint.
   - The `clarification_response` field is populated. The admin can now review the response.

3. **`approved` (Terminal State)**
   - The admin accepts the trip details.
   - The trip state becomes `approved`.
   - An audit log is written.
   - The trip is locked. The UI hides the action buttons.

4. **`rejected` (Terminal State)**
   - The admin rejects the trip (e.g., unauthorized use).
   - The trip state becomes `rejected`.
   - An audit log is written.
   - The trip is locked. The UI hides the action buttons.

## Important Rule
You **must** use the `audit_logs` table for tracking the history of these actions. The `trips` table only stores the *current* state and the *latest* note. 
When an admin clicks "Approve", the `AdminAuthController@approve` method creates an AuditLog row and updates the Trip row inside a DB transaction.

Live polling and tracking telemetry do not change administrative immutability. They add review context only; they do not automatically approve, reject, or create a clarification request.
