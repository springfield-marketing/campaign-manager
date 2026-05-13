<?php

return [
    /*
     * Number of consecutive FAILED messages required before a phone number
     * is automatically suppressed on the WhatsApp channel.
     */
    'failure_threshold' => (int) env('WHATSAPP_FAILURE_THRESHOLD', 3),

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
];
