<?php
$dotenv = parse_ini_file(__DIR__ . '/../.env');
return [
    'host' => $dotenv['SMTP_HOST'],
    'username' => $dotenv['SMTP_USERNAME'],
    'password' => $dotenv['SMTP_PASSWORD'],
    'port' => $dotenv['SMTP_PORT'],
    'encryption' => $dotenv['SMTP_ENCRYPTION'],
    'from_email' => $dotenv['SMTP_USERNAME'],
    'from_name' => 'UNILIA Events System'
];
?>
