<?php

declare(strict_types=1);

return [
    'field' => [
        'key' => 'Nøgle',
        'key_help' => 'Stabil identifikator, som en host-faktaudbyder er registreret mod. Sættes ved oprettelse; kan ikke ændres efterfølgende.',
        'name' => 'Navn',
        'description' => 'Beskrivelse',
        'profile' => 'Profil',
        'profile_help' => 'Formen hele guiden valideres imod ved udgivelse (sættes ved oprettelse og låses derefter). Faseinddelt håndhæver et fremadrettet flow gennem trinnene spørgsmål → fakta → beslutninger → udfald — ingen kant må springe tilbage til et tidligere trin; vælg den til strukturerede, trinvise guides. Fri form pålægger ingen rækkefølge — enhver node kan forbindes til enhver anden; vælg den til ad hoc-beslutningstræer.',
        'permissions' => 'Påkrævede tilladelser',
        'permissions_help' => 'Tilladelser, en bruger har brug for for at se/køre denne guide. Kopien på guideniveau er autoritativ for adgangsstyring; ændringer træder i kraft straks. Udgivelse af en version overskriver den fra den version.',
        'permissions_unavailable' => 'Der er ikke konfigureret noget tilladelseskatalog (decision-support-filament.permissions.options), så tilladelser kan ikke vælges her, og adgang kan ikke styres via tilladelser. Konfigurér et katalog — et array eller en closure pr. guide — for at aktivere valg.',
        'permissions_no_catalog_help' => 'Der er ikke konfigureret noget tilladelseskatalog, så du kan fjerne disse eksisterende tilladelser, men ikke tilføje nye, før et katalog er angivet.',
        'permissions_mode' => 'Tilladelseskrav',
        'permissions_mode_any' => 'En af tilladelserne (ELLER)',
        'permissions_mode_all' => 'Alle tilladelserne (OG)',
        'permissions_mode_help' => 'Hvordan de påkrævede tilladelser kombineres ved adgangskontrol. Motoren håndhæver intet — din Guide-policy læser dette sammen med tilladelserne.',
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
