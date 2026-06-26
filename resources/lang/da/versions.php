<?php

declare(strict_types=1);

return [
    'column' => [
        'version' => 'Version',
        'status' => 'Status',
        'published_at' => 'Udgivet',
    ],

    'action' => [
        'new_draft' => 'Ny tom kladde',
        'duplicate' => 'Ny version ud fra denne',
        'edit_tree' => 'Rediger træ',
        'start' => 'Start',
        'edit_metadata' => 'Rediger metadata',
        'publish' => 'Udgiv',
    ],

    'notification' => [
        'draft_created' => 'Kladdeversion :number oprettet',
        'duplicated' => 'Version :number oprettet ud fra v:source',
        'metadata_updated' => 'Metadata for version :number opdateret',
        'published' => 'Version :number udgivet',
        'publish_failed' => 'Udgivelse mislykkedes',
    ],
];
