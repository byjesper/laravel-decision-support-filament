<?php

declare(strict_types=1);

return [
    'field' => [
        'key' => 'Key',
        'key_help' => 'Stable identifier a host fact provider is registered against. Set at creation; cannot be changed afterwards.',
        'name' => 'Name',
        'description' => 'Description',
        'profile' => 'Profile',
        'profile_help' => 'The shape the whole guide is validated against when you publish (set at creation, locked afterwards). Phased enforces a forward flow through the stages questions → facts → decisions → outcomes — no edge may jump back to an earlier stage; choose it for structured, staged guides. Freeform imposes no ordering — any node may connect to any other; choose it for ad-hoc decision trees.',
        'permissions' => 'Required permissions',
        'permissions_help' => 'Permissions a user needs to see/run this guide. The guide-level copy is authoritative for gating; edits take effect immediately. Publishing a version overwrites it from that version.',
        'permissions_unavailable' => 'No permission catalog is configured (decision-support-filament.permissions.options), so permissions can’t be selected here and access can’t be gated by permission. Configure a catalog — an array, or a per-guide closure — to enable selection.',
        'permissions_no_catalog_help' => 'No permission catalog is configured, so you can remove these existing permissions but can’t add new ones until one is supplied.',
        'permissions_mode' => 'Permission match',
        'permissions_mode_any' => 'Any of the permissions (OR)',
        'permissions_mode_all' => 'All of the permissions (AND)',
        'permissions_mode_help' => 'How the required permissions are combined when checking access. The engine enforces nothing — your Guide policy reads this alongside the permissions.',
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
