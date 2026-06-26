<?php

declare(strict_types=1);

return [
    'title' => 'Rediger træ — :guide v:version',
    'breadcrumb' => 'Rediger træ (v:version)',

    'action' => [
        'save' => 'Gem kladde',
        'save_published' => 'Gem ændringer',
        'test' => 'Test guide',
        'publish' => 'Udgiv version',
        'add_node' => 'Tilføj node',
        'add_edge' => 'Tilføj kant',
        'add_option' => 'Tilføj valgmulighed',
    ],

    'item' => [
        'new_node' => 'Ny node',
        'new_edge' => 'Ny kant',
    ],

    'section' => [
        'nodes' => 'Noder',
        'nodes_description' => 'En node er et trin i guiden: et spørgsmål, et faktaopslag, en beslutning eller et endeligt udfald.',
        'edges' => 'Kanter',
        'edges_description' => 'En kant fører fra en nodes udgangsport til en anden node, eventuelt styret af en betingelse.',
        'metadata' => 'Metadata',
        'metadata_description' => 'Denne versions redigerbare arbejdskopi af guidens forbrugermetadata. Den initialiserer guiden ved udgivelse.',
        'validation' => 'Validering',
        'validation_description' => 'Kontrolleret mod dine nuværende ændringer — løs disse før udgivelse.',
        'preview' => 'Live forhåndsvisning',
        'preview_description' => 'Opdateres mens du redigerer (ugemt). Gem kladden for at bevare ændringerne.',
    ],

    'validation' => [
        'ok' => 'Ingen problemer — klar til udgivelse.',
    ],

    'readonly_notice_title' => 'Denne version er udgivet',
    'readonly_notice' => 'Strukturen er låst. Du kan stadig redigere labels, spørgsmål, udfald og oversættelser her, men at tilføje, fjerne eller omkoble noder kræver en ny version.',

    'field' => [
        'type' => 'Type',
        'type_help' => 'Trinets art. Ændring af den udskifter konfigurationsfelterne nedenfor.',
        'key' => 'Nøgle',
        'key_help' => 'Entydig identifikator for denne node i guiden; kanter refererer til den.',
        'label' => 'Label',
        'label_help' => 'Valgfrit menneskevenligt navn vist i editoren og diagrammet.',
        'prompt' => 'Spørgsmål',
        'prompt_help' => 'Spørgsmålet, der vises til personen, som kører guiden.',
        'input_type' => 'Inputtype',
        'input_type_help' => 'Hvordan svaret indsamles. boolean ruter sand/falsk; select ruter efter valgt værdi.',
        'fact' => 'Fakta',
        'fact_help' => 'Faktanavnet, som svaret gemmes under, og som kantbetingelser refererer til.',
        'required' => 'Påkrævet',
        'required_help' => 'Kræv et ikke-tomt svar, før kørslen kan fortsætte. Gælder kun fri-tekst-spørgsmål (tekst/dato/tal).',
        'options' => 'Valgmuligheder',
        'options_help' => 'Valg for et select-spørgsmål.',
        'option_value' => 'Værdi',
        'option_label' => 'Label',
        'verdict' => 'Konklusion',
        'verdict_help' => 'Den korte konklusion, der vises, når dette udfald nås.',
        'text' => 'Tekst',
        'text_help' => 'Valgfri længere forklaring vist under konklusionen. Understøtter Markdown.',
        'warnings' => 'Advarsler',
        'warnings_help' => 'Valgfrie forbehold vist sammen med konklusionen.',
        'permissions_help' => 'Tilladelser, der kræves for at se/køre guiden. Initialiserer den autoritative kopi på guideniveau, når denne version udgives.',
        'from' => 'Fra',
        'port' => 'Port',
        'port_help' => 'F.eks. true/false for et boolesk spørgsmål, eller out.',
        'to' => 'Til',
        'condition' => 'Betingelse',
        'operator' => 'Operator',
        'value' => 'Værdi',
        'expression' => 'Udtryk',
        'expression_help' => 'Et symfony/expression-language-udtryk, der evalueres mod fakta.',
        'edge_label' => 'Kantlabel',
        'edge_label_help' => 'Valgfri label vist på denne gren i diagrammet i stedet for den udledte betingelsestekst. Understøtter oversættelser pr. sprog.',
        'translation_label' => ':label (:locale)',
    ],

    'node_type' => [
        'question' => 'Spørgsmål',
        'fact' => 'Faktaopslag',
        'decision' => 'Beslutning',
        'outcome' => 'Udfald',
    ],

    'input_type' => [
        'boolean' => 'Ja / Nej',
        'select' => 'Flere valg',
        'date' => 'Dato',
        'text' => 'Fritekst',
        'number' => 'Tal',
    ],

    'condition' => [
        'always' => 'altid (standard)',
        'structured' => 'struktureret (fakta / operator / værdi)',
        'expression' => 'udtryk',
        'unknown' => 'fakta ukendt',
    ],

    'notification' => [
        'saved' => 'Kladde gemt',
        'saved_published' => 'Ændringer gemt',
        'published' => 'Guide udgivet',
        'publish_failed' => 'Udgivelse mislykkedes',
        'publish_failed_body' => 'Guiden har :count valideringsproblem(er), der skal løses.',
    ],
];
