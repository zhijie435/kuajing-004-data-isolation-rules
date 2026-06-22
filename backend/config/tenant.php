<?php

return [
    'domain_map' => [
        // 'edu.example.com' => '1',
        // 'school1.example.com' => '2',
    ],

    'tenant_column' => 'tenant_id',

    'super_admin_roles' => ['super_admin', 'system_admin'],

    'context_cache_ttl' => 1800,

    'strict_mode' => true,
];
