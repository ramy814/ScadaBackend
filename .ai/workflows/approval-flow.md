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
through the assistant's `ACCESS_TYPE >= 3` station path and requires the matched
`VEHICLE_STAGES.REMOVED_USER` to remain null; a removed station assignment can
neither appear in the form nor authorize a submitted vehicle ID.

Every supervisor review is also vehicle-category scoped. The authenticated SSO
user must hold base `fleet` access and the action key matching the current
`G_STORE.VEHICLE.FOLLOW_TO_OID` (`125`, `126`, `132`, or `133`). The dashboard
filters before pagination, and detail/approve/reject/clarify routes repeat the
live Oracle check. The assistant's station permission authorizes data entry only;
it never grants the later supervisor review scope.

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
