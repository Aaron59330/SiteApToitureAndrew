<?php
require __DIR__ . '/_app/lib.php';
$p = $_GET['p'] ?? 'index';
$raw = page_raw($p);
if ($raw === null) { http_response_code(404); echo 'Page introuvable'; exit; }
$admin = is_admin();
$file = $p . '.html';
[$d] = process_page($raw, $file, $admin, read_json('content', ['global' => [], 'pages' => []]), $p === 'realisations' ? read_projects() : null, in_array($p, ['index', 'realisations'], true) ? pub_reviews() : null);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: ' . ($admin ? 'no-store' : 'no-cache'));
header('X-Content-Type-Options: nosniff');
echo $d->saveHTML();
