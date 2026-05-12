<?php

return [
    /*
     * Number of consecutive FAILED messages required before a phone number
     * is automatically suppressed on the WhatsApp channel.
     */
    'failure_threshold' => (int) env('WHATSAPP_FAILURE_THRESHOLD', 3),
];
