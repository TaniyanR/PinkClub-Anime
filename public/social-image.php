<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/social_image_endpoint.php';
require_once __DIR__ . '/../lib/repository.php';

pcf_social_image_run([
    'item_sql' => 'SELECT * FROM items WHERE id = :id AND ' . items_front_release_where() . ' LIMIT 1',
    'service_key' => 'pinkclub-anime',
    'allowed_hosts' => ['dmm.co.jp', 'dmm.com', 'fanza.co.jp'],
    'candidate_fields' => ['image_large', 'full_package_url', 'main_image_url', 'image_url', 'image_small', 'image_list'],
    'raw_candidate_fields' => ['imageURL', 'packageImage', 'image', 'images', 'sampleImageURL'],
    'referer' => 'https://www.dmm.co.jp/',
    'user_agent' => 'Mozilla/5.0 (compatible; PinkClub-Anime-SocialCard/2.0; +' . BASE_URL . '/)',
]);
