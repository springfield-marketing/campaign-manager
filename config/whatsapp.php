<?php

return [
    /*
     * Number of consecutive hard-fail messages (genuinely undeliverable, not
     * system errors or temporary holds) before a number is marked dead.
     */
    'hard_fail_threshold' => (int) env('WHATSAPP_HARD_FAIL_THRESHOLD', 3),

    /*
     * If a number has been messaged this many times or more and every non-system
     * message is a hard fail, it is marked dead regardless of consecutiveness.
     */
    'bulk_dead_threshold' => (int) env('WHATSAPP_BULK_DEAD_THRESHOLD', 10),

    /*
     * Cooldown periods (days) applied per failure-reason category.
     */
    'cooldown_days' => [
        'quality_hold' => (int) env('WHATSAPP_COOLDOWN_QUALITY_HOLD', 3),
        'experiment'   => (int) env('WHATSAPP_COOLDOWN_EXPERIMENT', 7),
        'regional'     => (int) env('WHATSAPP_COOLDOWN_REGIONAL', 30),
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
];
