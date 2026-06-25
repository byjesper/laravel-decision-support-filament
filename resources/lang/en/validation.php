<?php

declare(strict_types=1);

/*
 * Localized renderings of the engine's publish-validation errors, keyed by the
 * engine's stable `ValidationError::$code`. Placeholders are filled from the
 * error's structured `params`. Any code without an entry here falls back to the
 * engine's English `message`.
 */
return [
    'question' => [
        'prompt_required' => 'Question node requires a prompt.',
        'fact_required' => 'Question node requires a fact name to store the answer.',
        'input_type_invalid' => 'Question node has an invalid input type.',
        'options_required' => 'A select question requires at least one option.',
    ],

    'outcome' => [
        'verdict_required' => 'Outcome node requires a verdict.',
    ],

    'fact' => [
        'fact_required' => 'Fact node requires a fact name to resolve.',
        'unknown_fact' => "Condition on edge ':edge' references unknown fact ':fact'.",
        'empty_expression' => "Expression condition on edge ':edge' is empty.",
        'invalid_expression' => "Expression on edge ':edge' is invalid: :error",
    ],

    'graph' => [
        'no_entry' => 'The guide has no resolvable entry node.',
        'unknown_node_type' => "Node ':key' has an unregistered type ':type'.",
        'dangling_edge' => "An edge references the unknown node ':node'.",
        'uncovered_port' => "Node ':key' has no outgoing edge for port ':port'.",
        'non_outcome_leaf' => "Node ':key' has no outgoing edges but is not an outcome.",
        'orphan_node' => "Node ':key' is unreachable from the entry node.",
        'cycle' => "The guide contains a cycle through ':key'.",
    ],

    'profile' => [
        'phase_order' => "Edge from ':from' to ':to' moves backwards across phases.",
    ],
];
