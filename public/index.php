<?php

namespace public;

use src\model\Api\Api;
use src\module\Page\Page;

require __DIR__ . '/../vendor/autoload.php';

$page = new Page();
$response = $page->route();

$headers = $response->headers->headers;

foreach ($headers as $header) {
    header($header);
}
http_response_code($response->status);

$jsonString = json_encode($response->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

print $jsonString;
