<?php
declare(strict_types=1);
require __DIR__.'/../private/release.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo json_encode(['ok'=>true,'release'=>release_info(),'server_utc'=>gmdate(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
