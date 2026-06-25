<?php

declare(strict_types=1);

return [
    'field' => [
        'key' => 'Nøgle',
        'key_help' => 'Stabil identifikator, som en host-faktaudbyder er registreret mod. Sættes ved oprettelse; kan ikke ændres efterfølgende.',
        'name' => 'Navn',
        'description' => 'Beskrivelse',
        'profile' => 'Profil',
        'profile_help' => 'Formbegrænsning ved udgivelse, som håndhæves af motoren. Sættes ved oprettelse; kan ikke ændres efterfølgende.',
        'permissions' => 'Påkrævede tilladelser',
        'permissions_help' => 'Tilladelser, en bruger har brug for for at se/køre denne guide. Kopien på guideniveau er autoritativ for adgangsstyring; ændringer træder i kraft straks. Udgivelse af en version overskriver den fra den version.',
    ],

    'section' => [
        'metadata' => 'Metadata',
        'metadata_description' => 'Forbrugerdefineret metadata gemt på guiden. Læses af din Guide-policy — motoren håndhæver intet.',
    ],

    'column' => [
        'key' => 'Nøgle',
        'name' => 'Navn',
        'profile' => 'Profil',
        'versions' => 'Versioner',
        'active_version' => 'Aktiv version',
    ],

    'action' => [
        'start' => 'Start',
        'start_tooltip' => 'Udgiv en version for at køre denne guide.',
    ],
];
