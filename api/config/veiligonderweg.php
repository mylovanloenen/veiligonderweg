<?php

/*
 * Domeinconfiguratie voor VeiligOnderweg.
 * Bronnen: zie CLAUDE.md in de root van de repo.
 */
return [

    // Politie OData (catalogus "Politie" op dataderden.cbs.nl).
    'cbs' => [
        'base_url' => env('CBS_ODATA_BASE', 'https://dataderden.cbs.nl/ODataApi/odata'),
        'yearly_table' => '47018NED', // Geregistreerde misdrijven; soort misdrijf, wijk, buurt, jaarcijfers
        'default_year' => (int) env('CRIME_DEFAULT_YEAR', 2025),
        'page_size' => 10000,
    ],

    // PDOK CBS Wijk- en Buurtkaart (WFS 2.0).
    'pdok' => [
        'wfs_url' => env('PDOK_WFS_URL', 'https://service.pdok.nl/cbs/wijkenbuurten/2025/wfs/v1_0'),
        'year' => (int) env('PDOK_YEAR', 2025),
        'page_size' => 1000,
    ],

    'default_municipality' => env('DEFAULT_MUNICIPALITY', 'GM0363'), // Amsterdam

    // Buurten met minder inwoners krijgen geen score (te instabiel per inwoner).
    'min_population_for_score' => 50,

    // Mapping van scorecategorie -> politie SoortMisdrijf-codes (tabel 47018NED).
    'crime_categories' => [
        'total' => [
            'label' => 'Totaal misdrijven',
            'codes' => ['0.0.0'],
        ],
        'violence' => [
            'label' => 'Geweld',
            'codes' => ['1.4.1', '1.4.2', '1.4.3', '1.4.4', '1.4.5'],
        ],
        'robbery' => [
            'label' => 'Straatroof en overval',
            'codes' => ['1.4.6', '1.4.7', '1.2.4'],
        ],
        'burglary' => [
            'label' => 'Inbraak',
            'codes' => ['1.1.1', '1.1.2'],
        ],
        'theft' => [
            'label' => 'Diefstal voertuigen en fietsen',
            'codes' => ['1.2.1', '1.2.2', '1.2.3', '1.2.5'],
        ],
        'nuisance' => [
            'label' => 'Overlast en vernieling',
            'codes' => ['2.1.1', '2.2.1'],
        ],
    ],

    // Meldcategorieen (vaste lijst) met standaard-vervaltijd in minuten.
    'incident_categories' => [
        ['slug' => 'beroving', 'name' => 'Beroving', 'ttl_minutes' => 120],
        ['slug' => 'geweld', 'name' => 'Geweld', 'ttl_minutes' => 120],
        ['slug' => 'intimidatie', 'name' => 'Intimidatie', 'ttl_minutes' => 120],
        ['slug' => 'slechte_verlichting', 'name' => 'Slechte verlichting', 'ttl_minutes' => 720],
        ['slug' => 'overig', 'name' => 'Overig', 'ttl_minutes' => 120],
    ],

    'incidents' => [
        'max_description_length' => 200,
        'confirm_extends_minutes' => 30,   // elke bevestiging verlengt de vervaltijd
        'max_ttl_multiplier' => 2,         // tot maximaal 2x de standaard-TTL
        'hide_after_disputes' => 3,        // verbergen bij >= 3 weerleggingen die de bevestigingen overtreffen
        'anonymise_after_days' => 7,       // na verlopen: account-koppeling en tekst wissen
        'delete_after_days' => 30,         // na verlopen: verwijderen
        'rate_limit_per_hour' => 5,
        'default_radius_m' => 1000,
        'max_radius_m' => 5000,
    ],

    // Inhoudsfilter: meldingen gaan over situaties, nooit over personen.
    'content_filter' => [
        // Termen over afkomst, huidskleur, religie of uiterlijk (signalement) worden geweigerd.
        'person_terms' => [
            'signalement', 'huidskleur', 'getint', 'getinte', 'donkere man', 'donkere vrouw', 'donker persoon',
            'zwarte man', 'zwarte vrouw', 'witte man', 'witte vrouw', 'blanke', 'blank persoon',
            'marokkaan', 'marokkaanse', 'marokkanen', 'turk', 'turkse', 'turken', 'surinamer', 'surinaamse',
            'antilliaan', 'antilliaanse', 'pool', 'poolse', 'polen', 'buitenlander', 'buitenlanders', 'allochtoon',
            'allochtone', 'aziaat', 'aziatisch', 'aziatische', 'arabier', 'arabisch', 'arabische', 'moslim',
            'moslims', 'jood', 'joodse', 'joden', 'zigeuner', 'neger', 'negers', 'negerin', 'nikker',
            'asielzoeker', 'asielzoekers', 'vluchteling', 'vluchtelingen', 'zwerver', 'zwervers', 'junk', 'junks',
        ],
        'profanity' => [
            'kanker', 'tering', 'tyfus', 'klere', 'kut', 'hoer', 'hoeren', 'flikker', 'mongool', 'mongolen',
            'lul', 'klootzak', 'klootzakken', 'eikel', 'debiel', 'idioot', 'sukkel', 'kkr', 'godverdomme', 'gvd',
            'fuck', 'fucking', 'shit', 'bitch', 'cunt', 'nigga', 'nigger', 'faggot',
        ],
    ],
];
