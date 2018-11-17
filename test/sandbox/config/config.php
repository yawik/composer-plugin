<?php

// chdir in config file so tests environment can chdir to this sandbox
chdir(dirname(__DIR__));
include_once __DIR__.'/../src/Module.php';
return [
    'modules' => [
        'Core',
    ],
];
