<?php

declare(strict_types=1);

return [
    'title' => 'Edit tree — :guide v:version',
    'breadcrumb' => 'Edit tree (v:version)',

    'action' => [
        'save' => 'Save draft',
        'save_published' => 'Save changes',
        'test' => 'Test guide',
        'publish' => 'Publish version',
        'add_node' => 'Add node',
        'add_edge' => 'Add edge',
        'add_option' => 'Add option',
    ],

    'item' => [
        'new_node' => 'New node',
        'new_edge' => 'New edge',
    ],

    'section' => [
        'nodes' => 'Nodes',
        'nodes_description' => 'A node is a step in the guide: a question, a fact lookup, a decision, or a terminal outcome.',
        'edges' => 'Edges',
        'edges_description' => "An edge routes from a node's output port to another node, optionally guarded by a condition.",
        'metadata' => 'Metadata',
        'metadata_description' => "This version's editable working copy of the guide's consumer metadata. It seeds the guide on publish.",
        'validation' => 'Validation',
        'validation_description' => 'Checked against your current edits — resolve these before publishing.',
        'preview' => 'Live preview',
        'preview_description' => 'Updates as you edit (unsaved). Save the draft to persist.',
    ],

    'validation' => [
        'ok' => 'No issues — ready to publish.',
    ],

    'readonly_notice_title' => 'This version is published',
    'readonly_notice' => 'The structure is locked. You can still edit labels, prompts, verdicts and translations here, but adding, removing or rewiring nodes needs a new version.',

    'field' => [
        'type' => 'Type',
        'type_help' => 'The kind of step. Changing it swaps the configuration fields below.',
        'key' => 'Key',
        'key_help' => 'Unique identifier for this node within the guide; edges reference it.',
        'label' => 'Label',
        'label_help' => 'Optional human-friendly name shown in the editor and diagram.',
        'prompt' => 'Prompt',
        'prompt_help' => 'The question shown to the person running the guide.',
        'input_type' => 'Input type',
        'input_type_help' => 'How the answer is collected. boolean routes true/false; select routes by chosen value.',
        'fact' => 'Fact',
        'fact_help' => 'The fact name the answer is stored under, and that edge conditions reference.',
        'options' => 'Options',
        'options_help' => 'Choices for a select question.',
        'option_value' => 'Value',
        'option_label' => 'Label',
        'verdict' => 'Verdict',
        'verdict_help' => 'The short verdict shown when this outcome is reached.',
        'text' => 'Text',
        'text_help' => 'Optional longer explanation shown beneath the verdict. Supports Markdown.',
        'warnings' => 'Warnings',
        'warnings_help' => 'Optional caveats shown with the verdict.',
        'permissions_help' => 'Permissions required to see/run the guide. Seeds the guide-level (authoritative) copy when this version is published.',
        'from' => 'From',
        'port' => 'Port',
        'port_help' => 'e.g. true/false for a boolean question, or out.',
        'to' => 'To',
        'condition' => 'Condition',
        'operator' => 'Operator',
        'value' => 'Value',
        'expression' => 'Expression',
        'expression_help' => 'A symfony/expression-language expression evaluated against the facts.',
        'translation_label' => ':label (:locale)',
    ],

    'condition' => [
        'always' => 'always (default)',
        'structured' => 'structured (fact / operator / value)',
        'expression' => 'expression',
        'unknown' => 'fact unknown',
    ],

    'notification' => [
        'saved' => 'Draft saved',
        'saved_published' => 'Changes saved',
        'published' => 'Guide published',
        'publish_failed' => 'Publishing failed',
        'publish_failed_body' => 'The guide has :count validation issue(s) to resolve.',
    ],
];
