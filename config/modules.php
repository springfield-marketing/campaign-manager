<?php

return [
    [
        'key' => 'ivr',
        'name' => 'IVR',
        'description' => 'Voice campaign workflows, call orchestration, and reporting entry points.',
        'route' => 'modules.ivr.index',
        'enabled' => true,
    ],
    [
        'key' => 'whatsapp',
        'name' => 'WhatsApp',
        'description' => 'Messaging templates, delivery operations, and conversation tooling.',
        'route' => 'modules.whatsapp.index',
        'enabled' => true,
    ],
    [
        'key' => 'emails',
        'name' => 'Emails',
        'description' => 'Email campaign management, audience targeting, and outbound delivery.',
        'route' => 'modules.emails.index',
        'enabled' => true,
    ],
];
