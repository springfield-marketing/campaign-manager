<?php

return [
    'cooldowns' => [
        'missed_days' => 1,
        'answered_days' => 14,
    ],

    'eligibility' => [
        'inactive_after_uses' => 3,
        'dead_after_uses' => 5,
    ],

    'raw_import' => [
        'required' => ['name', 'phone'],
        'aliases' => [
            'name' => ['name'],
            'phone' => ['phone', 'mobile', 'phone number', 'contact number'],
            'email' => ['email', 'email address'],
            'country' => ['country'],
            'nationality' => ['nationality'],
            'community' => ['community'],
            'resident' => ['resident', 'residency', 'resident status'],
            'city' => ['city'],
            'gender' => ['gender', 'sex'],
            'interest' => ['interest', 'interested project', 'project interest', 'project', 'enquiry', 'inquiry'],
            'source_file' => ['source file', 'source', 'source name'],
        ],
    ],

    'dtmf' => [
        'interested' => ['1'],
        'more_info' => ['2', 'A', 'B', 'C'],
        'unsubscribe' => ['3', 'D', 'E', 'F'],
    ],
];
