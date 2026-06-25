<?php

declare(strict_types=1);

return [
    'field' => [
        'key' => 'Key',
        'key_help' => 'Stable identifier a host fact provider is registered against. Set at creation; cannot be changed afterwards.',
        'name' => 'Name',
        'description' => 'Description',
        'profile' => 'Profile',
        'profile_help' => 'Publish-time shape constraint enforced by the engine. Set at creation; cannot be changed afterwards.',
        'permissions' => 'Required permissions',
        'permissions_help' => 'Permissions a user needs to see/run this guide. The guide-level copy is authoritative for gating; edits take effect immediately. Publishing a version overwrites it from that version.',
    ],

    'section' => [
        'metadata' => 'Metadata',
        'metadata_description' => 'Consumer-defined metadata stored on the guide. Read by your Guide policy — the engine enforces nothing.',
    ],

    'column' => [
        'key' => 'Key',
        'name' => 'Name',
        'profile' => 'Profile',
        'versions' => 'Versions',
        'active_version' => 'Active version',
    ],

    'action' => [
        'start' => 'Start',
        'start_tooltip' => 'Publish a version to run this guide.',
    ],
];
