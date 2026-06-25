<?php

declare(strict_types=1);

return [
    'column' => [
        'version' => 'Version',
        'status' => 'Status',
        'published_at' => 'Published at',
    ],

    'action' => [
        'new_draft' => 'New draft',
        'duplicate' => 'New version from this',
        'edit_tree' => 'Edit tree',
        'start' => 'Start',
        'edit_metadata' => 'Edit metadata',
        'publish' => 'Publish',
    ],

    'notification' => [
        'draft_created' => 'Draft version :number created',
        'duplicated' => 'Version :number created from v:source',
        'metadata_updated' => 'Version :number metadata updated',
        'published' => 'Version :number published',
        'publish_failed' => 'Publishing failed',
    ],
];
