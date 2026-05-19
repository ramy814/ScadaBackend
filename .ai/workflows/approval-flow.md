# Administrative Approval Flow

## Goal
To audit all trips to ensure municipal resources (vehicles, fuel) are used appropriately according to the recorded mission.

## State Machine

1. **`pending` (Initial State)**
   - The trip has been synced from the mobile app and successfully `confirmed`.
   - Action: It appears in the admin dashboard under "Pending Reviews".
   - Admin views the map, the origin/destination, wait minutes, and odometer readings.

2. **`needs_clarification` (Interaction)**
   - The admin notices a discrepancy (e.g., the path deviates wildly from the destination).
   - Admin clicks "Clarify" and inputs a `review_note`.
   - The trip state becomes `needs_clarification`.
   - An audit log is written.
   - The mobile app will poll or be notified that a trip requires attention.
   - The driver inputs a reason in the mobile app, hitting the `/api/trips/{id}/clarification-response` endpoint.
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
