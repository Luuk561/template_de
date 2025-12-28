<?php

// Test: gebruik dezelfde route als de UI
require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiToken = $_ENV['FORGE_API_TOKEN'];
$org = $_ENV['FORGE_ORGANIZATION'];
$serverId = $_ENV['FORGE_SERVER_ID'];
$siteId = '2955959';
$domain = 'bestedraadlozestofzuiger.nl';

// Step 1: Get domain records for this site
echo "Fetching domain records for site {$siteId}...\n";

// Use v1 endpoint to get site details
$ch = curl_init("https://forge.laravel.com/api/v1/servers/{$serverId}/sites/{$siteId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiToken}",
    "Accept: application/json",
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";

$data = json_decode($response, true);
print_r($data);
