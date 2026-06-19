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
        'quality_hold'   => (int) env('WHATSAPP_COOLDOWN_QUALITY_HOLD', 3),
        'experiment'     => (int) env('WHATSAPP_COOLDOWN_EXPERIMENT', 7),
        'regional'       => (int) env('WHATSAPP_COOLDOWN_REGIONAL', 30),
        'no_engagement'  => (int) env('WHATSAPP_COOLDOWN_NO_ENGAGEMENT', 90),
    ],

    /*
     * A number is placed into a long-term no-engagement cooldown when it has
     * been sent messages in this many distinct campaigns with zero clicks.
     */
    'no_engagement_threshold' => (int) env('WHATSAPP_NO_ENGAGEMENT_THRESHOLD', 5),

    // The WhatsApp raw-contacts importer was retired (Phase 3 / docs/data-rules/imports.md):
    // raw contacts are imported through the single Contacts → Imports path. Its 'raw_import'
    // column-mapping config was removed along with the processor.
];
