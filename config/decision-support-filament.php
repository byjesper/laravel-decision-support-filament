<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Where the guide resource appears in the host panel's navigation. Hosts
    | override the plugin's navigation via the panel registration if they need
    | finer control.
    |
    */
    'navigation' => [
        'group' => 'Decision Support',
        'sort' => null,
        'icon' => 'heroicon-o-rectangle-group',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mermaid preview
    |--------------------------------------------------------------------------
    |
    | The bundled diagram renderer. `theme` is forwarded to mermaid's
    | initialisation; hosts may swap it for any built-in mermaid theme.
    |
    */
    'mermaid' => [
        'theme' => 'default',
    ],
];
