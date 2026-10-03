-- Copy active NAMUA print routes to the existing NJAJANFEST connections.
-- This script only inserts into pos_print_route. Run against the Finance DB
-- used by the APK, after confirming the NJF-* connections are registered.
-- Route codes are prefixed with NJF- so rerunning the script is idempotent.

START TRANSACTION;

INSERT INTO pos_print_route (
    route_code, route_name, event_code, document_type,
    outlet_id, terminal_id, operational_division_id, product_division_id,
    content_scope, connection_id, layout_id, copy_count, priority,
    notes, print_mode, is_active
)
SELECT
    CONCAT('NJF-', source_route.route_code),
    CONCAT('NJF - ', source_route.route_name),
    source_route.event_code,
    source_route.document_type,
    target_outlet.id,
    NULL,
    source_route.operational_division_id,
    source_route.product_division_id,
    source_route.content_scope,
    target_connection.id,
    source_route.layout_id,
    source_route.copy_count,
    source_route.priority,
    source_route.notes,
    source_route.print_mode,
    source_route.is_active
FROM pos_print_route AS source_route
JOIN pos_outlet AS source_outlet
    ON source_outlet.id = source_route.outlet_id
    AND source_outlet.outlet_code = 'NAMUA01'
JOIN pos_outlet AS target_outlet
    ON target_outlet.outlet_code = 'OUT-NJAJANFEST'
JOIN pos_print_connection AS source_connection
    ON source_connection.id = source_route.connection_id
    AND source_connection.outlet_id = source_outlet.id
JOIN pos_print_connection AS target_connection
    ON target_connection.outlet_id = target_outlet.id
    AND target_connection.connection_code = CONCAT('NJF-', source_connection.connection_code)
    AND target_connection.connection_name = source_connection.connection_name
    AND target_connection.location_label <=> source_connection.location_label
    AND target_connection.is_active = 1
JOIN pos_print_layout AS layout_row
    ON layout_row.id = source_route.layout_id
    AND layout_row.is_active = 1
WHERE source_route.is_active = 1
    AND source_route.terminal_id IS NULL
    AND NOT EXISTS (
        SELECT 1
        FROM pos_print_route AS existing
        WHERE existing.route_code = CONCAT('NJF-', source_route.route_code)
    );

SELECT ROW_COUNT() AS routes_added;

SELECT route.id, route.route_code, route.event_code, route.print_mode,
       connection.connection_name, connection.connection_code,
       layout_row.layout_name
FROM pos_print_route AS route
JOIN pos_outlet AS outlet ON outlet.id = route.outlet_id
JOIN pos_print_connection AS connection ON connection.id = route.connection_id
JOIN pos_print_layout AS layout_row ON layout_row.id = route.layout_id
WHERE outlet.outlet_code = 'OUT-NJAJANFEST'
ORDER BY route.event_code, route.priority, route.id;

COMMIT;
