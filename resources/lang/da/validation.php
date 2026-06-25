<?php

declare(strict_types=1);

/*
 * Danske gengivelser af motorens udgivelsesvalideringsfejl, nøglet på motorens
 * stabile `ValidationError::$code`. Pladsholdere udfyldes fra fejlens
 * strukturerede `params`. Koder uden en post her falder tilbage til motorens
 * engelske `message`.
 */
return [
    'question' => [
        'prompt_required' => 'Spørgsmålsnode kræver et spørgsmål.',
        'fact_required' => 'Spørgsmålsnode kræver et faktanavn at gemme svaret under.',
        'input_type_invalid' => 'Spørgsmålsnode har en ugyldig inputtype.',
        'options_required' => 'Et select-spørgsmål kræver mindst én valgmulighed.',
    ],

    'outcome' => [
        'verdict_required' => 'Udfaldsnode kræver en konklusion.',
    ],

    'fact' => [
        'fact_required' => 'Faktanode kræver et faktanavn at slå op.',
        'unknown_fact' => "Betingelsen på kanten ':edge' refererer til ukendt fakta ':fact'.",
        'empty_expression' => "Udtryksbetingelsen på kanten ':edge' er tom.",
        'invalid_expression' => "Udtrykket på kanten ':edge' er ugyldigt: :error",
    ],

    'graph' => [
        'no_entry' => 'Guiden har ingen brugbar startnode.',
        'unknown_node_type' => "Noden ':key' har en uregistreret type ':type'.",
        'dangling_edge' => "En kant refererer til den ukendte node ':node'.",
        'uncovered_port' => "Noden ':key' har ingen udgående kant for porten ':port'.",
        'non_outcome_leaf' => "Noden ':key' har ingen udgående kanter, men er ikke et udfald.",
        'orphan_node' => "Noden ':key' kan ikke nås fra startnoden.",
        'cycle' => "Guiden indeholder en cyklus gennem ':key'.",
    ],

    'profile' => [
        'phase_order' => "Kanten fra ':from' til ':to' bevæger sig baglæns mellem faser.",
    ],
];
