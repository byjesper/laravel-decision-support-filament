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
        // Navigation group. A plain label or a translation key — passed through __().
        'group' => 'Decision Support',
        'sort' => null,
        'icon' => 'heroicon-o-rectangle-group',
        // Navigation label for the guide resource. null => Filament's default
        // (the plural model label, e.g. "Guides"). A string may be a plain label
        // or a translation key — it is passed through __().
        'label' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource labels
    |--------------------------------------------------------------------------
    |
    | Override the singular/plural model labels used in titles, breadcrumbs, and
    | buttons. null => Filament's defaults derived from the model name. Strings
    | may be plain labels or translation keys (passed through __()).
    |
    */
    'labels' => [
        'model' => null,
        'plural' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Forms
    |--------------------------------------------------------------------------
    |
    | How a guide is CREATED from the resource. 'page' (default) opens the
    | full-page create form; 'modal' or 'slideover' create from the list without
    | leaving it. Editing always stays a full page — that page hosts the guide's
    | versions (the relation manager, Edit-tree/Run/Publish), which Filament can
    | only render on a record page.
    |
    */
    'forms' => [
        'layout' => 'page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | The catalog of permissions an author can pick from for a guide (the chosen
    | ones are stored at extra_attributes.permissions). It may be:
    |   - null                       => no catalog: the permissions field is replaced
    |                                   by an info callout (permissions can't be gated
    |                                   until you supply one);
    |   - an array (list of strings, => a multi-select with that catalog for every
    |     or value => label pairs)      guide;
    |   - a closure                  => fn (?Guide $guide): array — resolved per
    |                                   guide, so different guides offer different
    |                                   catalogs (null while creating a guide).
    |
    | 'mode' is the default for how those permissions combine: 'any' (OR — hold
    | any one) or 'all' (AND — hold every one). Authors can override it per guide;
    | it is stored at extra_attributes.permissions_mode.
    |
    | The engine enforces nothing — read extra_attributes.permissions and
    | .permissions_mode from your own Guide policy.
    |
    */
    'permissions' => [
        'options' => null,
        'mode' => 'any',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-language content
    |--------------------------------------------------------------------------
    |
    | Locales the tree editor offers a translation input for, per translatable
    | content field (written into the node's `*_i18n` maps). Empty => single
    | language (current behaviour). The runner renders in the panel's active
    | locale, falling back to `fallback_locale` and then the base string.
    |
    |   'locales' => ['da', 'en'],
    |   'fallback_locale' => 'en',
    |
    */
    'locales' => [],
    'fallback_locale' => null,

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
