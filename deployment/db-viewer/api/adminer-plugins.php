<?php

// Only the hosted demo database, always over verified TLS.
return [
    new AdminerLoginServers([
        'Online ERP (Aiven MySQL)' => ['server' => (string) getenv('DBVIEW_SERVER'), 'driver' => 'server'],
    ]),
    new AdminerLoginSsl(['ca' => __DIR__.'/aiven-ca.pem', 'verify' => true]),
];
