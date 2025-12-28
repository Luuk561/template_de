<?php

// Test: Can we use the WEB route with an API token?
require __DIR__.'/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiToken = $_ENV['FORGE_API_TOKEN'];
$org = $_ENV['FORGE_ORGANIZATION'];
$serverId = $_ENV['FORGE_SERVER_ID'];
$siteId = '2955959';
$domainRecordId = '1078361'; // From your network tab

echo "Testing WEB route with API token...\n";
echo "POST /{$org}/affiliate-sites-hetzner/{$siteId}/domains/{$domainRecordId}/certificate/letsencrypt\n\n";

$url = "https://forge.laravel.com/{$org}/affiliate-sites-hetzner/{$siteId}/domains/{$domainRecordId}/certificate/letsencrypt";

$payload = json_encode([
    'domain_record_id' => $domainRecordId,
    'verification_method' => 'http-01',
    'key_type' => 'ecdsa',
    'prefer_isrg_x1' => false,
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiToken}",
    "Accept: application/json",
    "Content-Type: application/json",
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";
