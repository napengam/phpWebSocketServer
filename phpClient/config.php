<?php
// Set to true for Local Development (Laragon / localhost)
// Set to false for Production
$developsystem = true; 

return [
    'ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => !$developsystem, // false in dev, true in production
        'allow_self_signed' => $developsystem,  // true in dev, false in production
        'cafile'            => '',
    ]
];