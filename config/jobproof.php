<?php

return [
    // Built-in milestone templates: name + percent of quote. Percents sum to 100.
    'milestone_templates' => [
        'house_build' => [
            'name' => 'House build',
            'milestones' => [
                ['name' => 'Deposit & site clearing', 'percent' => 10],
                ['name' => 'Foundation', 'percent' => 15],
                ['name' => 'Structure & columns', 'percent' => 20],
                ['name' => 'Roofing', 'percent' => 15],
                ['name' => 'Brickwork & plastering', 'percent' => 15],
                ['name' => 'M&E (plumbing, electrical)', 'percent' => 10],
                ['name' => 'Finishes & handover', 'percent' => 15],
            ],
        ],
        'renovation' => [
            'name' => 'Renovation',
            'milestones' => [
                ['name' => 'Deposit', 'percent' => 30],
                ['name' => 'Demolition & rough works', 'percent' => 30],
                ['name' => 'Fit-out', 'percent' => 30],
                ['name' => 'Handover', 'percent' => 10],
            ],
        ],
        'installation' => [
            'name' => 'Installation',
            'milestones' => [
                ['name' => 'Deposit', 'percent' => 50],
                ['name' => 'Installation complete', 'percent' => 50],
            ],
        ],
    ],

    // Interface languages: code => label shown in the language switcher. English is the source text.
    'locales' => [
        'en' => 'EN',
        'ms' => 'BM',
        'zh' => '中文',
    ],

    'client_session_days' => 30,

    // Emails allowed to open /horizon in production.
    'horizon_admins' => array_filter(array_map('trim', explode(',', (string) env('HORIZON_ADMINS', '')))),
    'otp_ttl_minutes' => 10,
    'proof_upload_url_minutes' => 30,
];
