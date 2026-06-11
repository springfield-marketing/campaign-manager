<?php

return [
    'cooldowns' => [
        'missed_days' => 1,
        'answered_days' => 14,
    ],

    'eligibility' => [
        'inactive_after_consecutive_no_answers' => 5,
    ],

    'raw_import' => [
        'required' => ['name'],
        'aliases' => [
            'name'                 => ['name', 'full name', 'full_name', 'contact name'],
            'phone'                => ['phone', 'mobile', 'phone number', 'contact number'],
            'email'                => ['email', 'email address'],
            'country_iso'          => ['country iso', 'country_iso', 'iso code', 'iso_code', 'country'],
            'emirate'              => ['emirate', 'state', 'city'],
            'nationality'          => ['nationality'],
            'gender'               => ['gender', 'sex'],
            'interest'             => ['interest', 'interested project', 'project interest', 'enquiry', 'inquiry'],
            'official_area_name'   => ['official area', 'official_area', 'official area name', 'official_area_name', 'dld area', 'area'],
            'marketing_area_name'  => ['marketing area', 'marketing_area', 'marketing area name', 'marketing_area_name', 'community', 'location'],
            'project_name'         => ['project name', 'project_name', 'project', 'development', 'building'],
            'building_name'        => ['building name', 'building_name', 'tower', 'tower name'],
            'unit_reference'       => ['unit', 'unit reference', 'unit_reference', 'unit number', 'apt', 'apartment'],
            'relationship_type'    => ['relationship type', 'relationship_type', 'relationship'],
            'confidence_level'     => ['confidence level', 'confidence_level', 'confidence'],
            'source'               => ['source file', 'source', 'source name'],
            'tier'                 => ['tier', 'client tier', 'wealth tier', 'segment'],
        ],
    ],

    'dtmf' => [
        'interested' => ['1'],
        'more_info' => ['2', 'A', 'B', 'C'],
        'unsubscribe' => ['3', 'D', 'E', 'F'],
    ],
];
