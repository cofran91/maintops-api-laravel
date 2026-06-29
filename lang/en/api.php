<?php

return [
    'messages' => [
        'ok' => 'OK',
        'auth' => [
            'login_successful' => 'Login successful.',
            'password_reset_link_sent' => 'If the email exists, a password reset link will be sent.',
            'password_updated' => 'Password updated successfully.',
            'authenticated_user' => 'Authenticated user.',
            'language_updated' => 'Language updated successfully.',
            'signed_out' => 'Signed out successfully.',
        ],
        'service_tokens' => [
            'issued' => 'Service token issued.',
        ],
        'audits' => [
            'retrieved' => 'Audits retrieved.',
        ],
        'maintenance_orders' => [
            'retrieved' => 'Maintenance orders retrieved.',
            'created' => 'Maintenance order created successfully.',
            'retrieved_one' => 'Maintenance order retrieved.',
            'updated' => 'Maintenance order updated successfully.',
        ],
        'maintenance_order_items' => [
            'retrieved' => 'Maintenance order items retrieved.',
            'retrieved_one' => 'Maintenance order item retrieved.',
            'updated' => 'Maintenance order item updated successfully.',
        ],
        'maintenance_tasks' => [
            'retrieved' => 'Maintenance tasks retrieved.',
            'created' => 'Maintenance task created successfully.',
            'retrieved_one' => 'Maintenance task retrieved.',
            'updated' => 'Maintenance task updated successfully.',
            'deleted' => 'Maintenance task deleted successfully.',
        ],
        'maintenance_plans' => [
            'retrieved' => 'Maintenance plans retrieved.',
            'created' => 'Maintenance plan created successfully.',
            'retrieved_one' => 'Maintenance plan retrieved.',
            'updated' => 'Maintenance plan updated successfully.',
            'deleted' => 'Maintenance plan deleted successfully.',
        ],
        'workshops' => [
            'retrieved' => 'Workshops retrieved.',
            'created' => 'Workshop created successfully.',
            'retrieved_one' => 'Workshop retrieved.',
            'updated' => 'Workshop updated successfully.',
            'deleted' => 'Workshop deleted successfully.',
        ],
        'vehicles' => [
            'retrieved' => 'Vehicles retrieved.',
            'created' => 'Vehicle created successfully.',
            'retrieved_one' => 'Vehicle retrieved.',
            'updated' => 'Vehicle updated successfully.',
            'deleted' => 'Vehicle deleted successfully.',
        ],
        'dashboard' => [
            'retrieved' => 'Dashboard retrieved.',
        ],
        'vehicle_systems' => [
            'retrieved' => 'Vehicle systems retrieved.',
        ],
        'users' => [
            'retrieved' => 'Users retrieved.',
            'created' => 'User created successfully.',
            'retrieved_one' => 'User retrieved.',
            'updated' => 'User updated successfully.',
            'deleted' => 'User deleted successfully.',
        ],
        'owners' => [
            'retrieved' => 'Owners retrieved.',
            'created' => 'Owner created successfully.',
            'retrieved_one' => 'Owner retrieved.',
            'updated' => 'Owner updated successfully.',
            'deleted' => 'Owner deleted successfully.',
        ],
        'analytics_initial_sync' => [
            'snapshot_retrieved' => 'Analytics initial sync snapshot retrieved.',
        ],
    ],

    'exceptions' => [
        'unauthenticated' => 'Unauthenticated.',
        'forbidden' => 'Forbidden.',
        'validation' => 'The given data was invalid.',
        'not_found' => 'Resource not found.',
        'server_error' => 'Unable to process the request.',
        'unauthorized_service_request' => 'Unauthorized service request.',
        'unsupported_analytics_initial_sync_resource' => 'Unsupported analytics initial sync resource.',
        'service_tokens' => [
            'unauthorized' => 'Not authorized to issue service tokens.',
            'analytics_unauthorized' => 'Not authorized to issue Analytics tokens.',
        ],
    ],

    'validation' => [
        'auth' => [
            'credentials' => 'The provided credentials are incorrect.',
        ],
        'maintenance_orders' => [
            'transition_invalid' => 'The requested order status transition is not valid.',
            'approve_without_items' => 'An order without items cannot be approved.',
            'complete_before_started' => 'An order cannot be completed before it has started.',
            'complete_with_open_items' => 'An order with pending or active items cannot be completed.',
            'cancel_allowed_status' => 'Only scheduled or in-progress orders can be cancelled.',
            'cancel_with_in_progress_items' => 'An order with in-progress items cannot be cancelled.',
            'reject_scheduled' => 'A scheduled order cannot be rejected.',
            'deliver_before_completed' => 'An order cannot be delivered before it has been completed.',
            'deliver_with_open_items' => 'An order with pending or active items cannot be delivered.',
            'submit_without_items' => 'An order without items cannot be submitted for owner approval.',
        ],
        'maintenance_order_items' => [
            'transition_invalid' => 'The requested item status transition is not valid.',
            'reject_scheduled' => 'A scheduled item cannot be rejected.',
            'start_requires_order_scheduled_or_in_progress' => 'An item cannot be started unless its order is scheduled or in progress.',
            'cancel_current_status' => 'The item cannot be cancelled from its current status.',
            'complete_before_started' => 'An item cannot be completed before it has started.',
        ],
        'rules' => [
            'vehicle_without_open_order' => 'The selected vehicle already has an open maintenance order.',
            'order_status_role' => 'The authenticated role cannot apply this order status change.',
            'order_status_transition' => 'The requested order status transition is not allowed.',
            'active_advisor' => 'The selected advisor must be an active advisor user.',
            'item_status_role' => 'The authenticated role cannot apply this item status change.',
            'item_cancel_allowed_status' => 'Only scheduled or in-progress items can be cancelled from this endpoint.',
            'item_status_transition' => 'The requested item status transition is not allowed.',
            'selected_workshop_invalid' => 'The selected workshop is invalid.',
            'user_workshop_requires_technician' => 'Only users with the technician role can be assigned to a workshop.',
            'user_workshop_admin_only' => 'Only system administrators can assign technicians to a workshop.',
            'super_admin_not_assignable' => 'Super admin users cannot be created or promoted through this endpoint.',
            'role_not_allowed' => 'You are not allowed to assign the selected role.',
            'technician_active_role' => 'Each technician must be an active user with the technician role.',
            'technician_assigned_elsewhere' => 'The technician is already assigned to another workshop.',
            'schedule_day_invalid' => 'The day must be monday, tuesday, wednesday, thursday, friday, saturday, or sunday.',
            'schedule_allowed_keys' => 'Each day only accepts opens_at and closes_at.',
            'schedule_closing_after_opening' => 'The closing time must be after the opening time.',
            'manager_active_role' => 'The workshop manager must be an active user with the workshop_manager role.',
            'manager_assigned_elsewhere' => 'The workshop manager is already assigned to another workshop.',
        ],
    ],
];
