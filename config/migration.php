<?php

return [
    'database' => [
        'host' => 'localhost',
        'port' => 8123,
        'username' => 'default',
        'password' => '',
        'database' => 'default',
    ],
    'migrations_directory' => __DIR__ . '/../src/Migrations',
    'migration_template' => __DIR__ . '/../src/resources/templates/migration_template.php.tpl',
];
