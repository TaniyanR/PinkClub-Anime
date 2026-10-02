<?php
declare(strict_types=1);

// Pure regression checks: no database, external HTTP request or fixture writes.
putenv('BASE_URL=https://anime.example.com');
$_SERVER['SCRIPT_NAME'] = '/absolute/checkout/scripts/auto_import.php';
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/public_page_cache.php';
require_once __DIR__ . '/../lib/app_features.php';
require_once __DIR__ . '/../lib/dmm_sync_service.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

check(BASE_URL === 'https://anime.example.com', 'CLI must not append a filesystem path to BASE_URL');
check(normalize_configured_base_url('https://example.com/app/public/index.php') === 'https://example.com/app', 'Subdirectory base URL');
check(normalize_configured_base_url('https://user:secret@example.com') === '', 'Reject credentials in base URL');
foreach (['http://127.0.0.1/feed', 'http://[::1]/feed', 'http://169.254.169.254/', 'file:///etc/passwd', 'https://user:secret@example.com/feed', "https://example.com/\r\nx"] as $url) {
    check(rss_http_url($url) === '', 'Reject unsafe RSS URL: ' . $url);
}
check(!rss_public_ip('10.0.0.1') && !rss_public_ip('::1') && rss_public_ip('8.8.8.8'), 'Public IP validation');
check(rss_normalize_url('https://example.com/?p=1') !== rss_normalize_url('https://example.com/?p=2'), 'Query-specific RSS articles must stay distinct');

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
$_SERVER['REQUEST_URI'] = '/item.php?id=3';
$issuedAt = time() - 3;
$token = analytics_beacon_token('/item.php?id=3', $issuedAt);
check(analytics_beacon_token_is_valid($token, '/item.php?id=3'), 'Valid signed beacon');
check(!analytics_beacon_token_is_valid($token, '/item.php?id=4'), 'Beacon must bind to item path');
check(!analytics_beacon_token_is_valid(analytics_beacon_token('/item.php?id=3', time()), '/item.php?id=3'), 'Reject immediate beacon');
$sharedHtml = '<script>var token = "__PCF_ANALYTICS_TOKEN__";</script>';
$a = pcf_public_page_cache_personalize($sharedHtml);
$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
$b = pcf_public_page_cache_personalize($sharedHtml);
check($a !== $b && !str_contains($b, '__PCF_ANALYTICS_TOKEN__'), 'Cache delivery personalizes visitor token');
check(!analytics_beacon_token_is_valid($token, '/item.php?id=3'), 'Another visitor cannot reuse token');

class FixtureFloorClient extends DmmApiClient
{
    public array $floors = [];
    public function fetchFloorList(): array { return $this->floors; }
}
$client = new FixtureFloorClient('fixture', 'fixture', 'https://example.com/');
$client->floors = ['result' => ['site' => [['code' => 'FANZA', 'service' => [['code' => 'digital', 'floor' => [['code' => 'videoa', 'id' => '43'], ['code' => 'anime', 'id' => '999']]]]]]]];
$service = (new ReflectionClass(DmmSyncService::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(DmmSyncService::class, 'client'))->setValue($service, $client);
check($service->resolveAnimeFloorId() === '999', 'Resolve Anime numeric ID from FloorList, never assume video ID');
$client->floors = ['result' => ['site' => []]];
$failed = false;
try { $service->resolveAnimeFloorId(); } catch (RuntimeException) { $failed = true; }
check($failed, 'Missing Anime floor must fail without syncing another catalog');
echo "PASS: {$checks} regression checks\n";
