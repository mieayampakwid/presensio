<?php

return [
    'inbox' => [
        'title' => 'Notifications',
        'description' => 'Center for activity alerts, announcements, and your account information.',
        'mark_all_read' => 'Mark all as read',
        'all' => 'All',
        'unread' => 'Unread',
        'empty' => 'No notifications found.',
        'empty_unread' => 'No unread notifications found.',
    ],
    'deliveries' => [
        'title' => 'Notification Delivery Logs',
        'description' => 'External notification delivery history (WhatsApp & Email), delivery status, and error tracking.',
        'breadcrumbs' => [
            'admin' => 'Admin',
            'delivery_logs' => 'Delivery Logs',
        ],
        'filters' => [
            'type' => 'Type',
            'all_types' => 'All Types',
            'channel' => 'Channel',
            'all_channels' => 'All Channels',
            'status' => 'Status',
            'all_statuses' => 'All Statuses',
            'date' => 'Date',
        ],
        'table' => [
            'time' => 'Time',
            'type' => 'Type',
            'channel' => 'Channel',
            'recipient' => 'Recipient',
            'status' => 'Status',
            'attempts' => 'Attempts',
            'details_error' => 'Details / Error',
            'empty' => 'No matching notification delivery history found.',
            'scheduled' => 'Scheduled:',
            'id' => 'ID:',
        ],
    ],
];
