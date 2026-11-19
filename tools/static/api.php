<?php
require __DIR__ . '/_app/lib.php';
$a = $_GET['a'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(['error' => 'Méthode refusée'], 405);
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') jout(['error' => 'Requête refusée'], 403);
$in = body_json();

// ---- sans session
if ($a === 'login') {
  $ip = client_ip();
  if (!rate_limit('login:' . $ip)) jout(['error' => 'Trop de tentatives. Réessayez dans quelques minutes.'], 429);
  if (verify_login($in['email'] ?? '', $in['password'] ?? '')) { reset_limit('login:' . $ip); set_session(); jout(['ok' => true]); }
  jout(['error' => 'E-mail ou mot de passe incorrect.'], 401);
}
if ($a === 'setup') {
  if (admin_account()) jout(['error' => 'Compte déjà créé.'], 403);
  if (!rate_limit('setup:' . client_ip(), 10)) jout(['error' => 'Trop de tentatives.'], 429);
  if (!hash_equals(SETUP_KEY, trim((string)($in['key'] ?? '')))) jout(['error' => 'Code d\'installation incorrect.'], 403);
  $email = trim((string)($in['email'] ?? '')); $pw = (string)($in['password'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jout(['error' => 'E-mail invalide.'], 422);
  if (mb_strlen($pw) < 10) jout(['error' => 'Mot de passe : 10 caractères minimum.'], 422);
  write_json('admin', ['email' => $email, 'hash' => password_hash($pw, PASSWORD_DEFAULT)]);
  set_session(); jout(['ok' => true]);
}
if ($a === 'logout') { clear_session(); jout(['ok' => true]); }

// ---- session administrateur obligatoire
if (!is_admin()) jout(['error' => 'Non connecté'], 401);

switch ($a) {
  case 'upload':
    if (empty($_FILES['file']['tmp_name'])) jout(['error' => 'Aucune image valide reçue.'], 400);
    $u = save_image($_FILES['file']['tmp_name']); if (!$u) jout(['error' => 'Image illisible.'], 400);
    jout(['url' => $u]);

  case 'content':
    $page = $in['page'] ?? ''; $raw = page_raw(preg_replace('/\.html$/', '', (string)$page));
    if ($raw === null) jout(['error' => 'Page inconnue'], 400);
    [, $texts, $imgs] = process_page($raw, $page);
    update_json('content', ['global' => [], 'pages' => []], function (&$data) use ($in, $page, $texts, $imgs) {
      $data['global'] = $data['global'] ?? []; $data['pages'][$page] = $data['pages'][$page] ?? [];
      foreach (($in['text'] ?? []) as $key => $html) {
        if (!array_key_exists($key, $texts) || !is_string($html) || strlen($html) > 5000) continue;
        $clean = sanitize($html);
        if ($key[0] === 'g') $ref = &$data['global']; else $ref = &$data['pages'][$page];
        if ($clean === $texts[$key]) unset($ref[$key]); else $ref[$key] = ['o' => $texts[$key], 'v' => $clean];
        unset($ref);
      }
      foreach (($in['img'] ?? []) as $key => $url) {
        if (!array_key_exists($key, $imgs) || !is_string($url)) continue;
        if ($key[0] === 'g') $ref = &$data['global']; else $ref = &$data['pages'][$page];
        if ($url === $imgs[$key]) unset($ref[$key]);
        elseif (preg_match('#^/(uploads|images)/[\w.\-]+$#', $url)) $ref[$key] = ['o' => $imgs[$key], 'v' => $url];
        unset($ref);
      }
    });
    jout(['ok' => true]);

  case 'proj_save':
    $photos = array_values(array_filter((array)($in['photos'] ?? []), fn($u) => is_string($u) && preg_match('#^/uploads/[\w.\-]+$#', $u)));
    $title = trim(clip($in['title'] ?? '', 140));
    if ($title === '' || !$photos) jout(['error' => 'Un titre et au moins une photo sont nécessaires.'], 422);
    $id = (string)($in['id'] ?? ''); $removed = [];
    update_json('realisations', [], function (&$l) use (&$id, $in, $title, $photos, &$removed) {
      $l = projects_norm($l); $found = false;
      foreach ($l as &$p) if ($id !== '' && $p['id'] === $id) {
        $found = true; $removed = array_diff($p['photos'], $photos);
        $p['title'] = $title; $p['category'] = trim(clip($in['category'] ?? '', 60)); $p['description'] = trim(clip($in['description'] ?? '', 5000)); $p['photos'] = $photos;
      }
      unset($p);
      if (!$found) { $id = new_id(); array_unshift($l, ['id' => $id, 'title' => $title, 'category' => trim(clip($in['category'] ?? '', 60)), 'description' => trim(clip($in['description'] ?? '', 5000)), 'photos' => $photos, 'created' => date('c')]); }
    });
    foreach ($removed as $u) @unlink(UPLOADS . '/' . basename($u));
    jout(['ok' => true, 'id' => $id]);

  case 'proj_del':
    $files = [];
    update_json('realisations', [], function (&$l) use (&$files) {
      $l = projects_norm($l);
      $l = array_values(array_filter($l, function ($p) use (&$files) { if ($p['id'] === ($_GET['id'] ?? '')) { $files = $p['photos']; return false; } return true; }));
    });
    foreach ($files as $u) @unlink(UPLOADS . '/' . basename($u));
    jout(['ok' => true]);

  case 'real_move':
    update_json('realisations', [], function (&$l) use ($in) {
      $i = null; foreach ($l as $k => $it) if ($it['id'] === ($in['id'] ?? '')) $i = $k;
      if ($i === null) return; $j = $i + (($in['dir'] ?? '') === 'up' ? -1 : 1);
      if ($j < 0 || $j >= count($l)) return; [$l[$i], $l[$j]] = [$l[$j], $l[$i]];
    });
    jout(['ok' => true]);

  // ---- messagerie
  case 'msg_list':
    $l = read_json('messages', []); usort($l, fn($x, $y) => strcmp($y['date'], $x['date']));
    jout(['messages' => $l]);

  case 'msg_read':
    update_json('messages', [], function (&$l) use ($in) { foreach ($l as &$m) if ($m['id'] === ($in['id'] ?? '')) $m['read'] = true; });
    jout(['ok' => true]);

  case 'msg_del':
    update_json('messages', [], function (&$l) use ($in) { $l = array_values(array_filter($l, fn($m) => $m['id'] !== ($in['id'] ?? ''))); });
    jout(['ok' => true]);

  case 'msg_reply':
    $text = trim((string)($in['text'] ?? '')); if ($text === '' || mb_strlen($text) > 8000) jout(['error' => 'Message vide ou trop long.'], 422);
    $msg = null; foreach (read_json('messages', []) as $m) if ($m['id'] === ($in['id'] ?? '')) $msg = $m;
    if (!$msg) jout(['error' => 'Message introuvable'], 404);
    $quoted = '<div style="margin-top:22px;padding:14px 16px;background:#f2f3f5;border-left:4px solid #d42a26;border-radius:6px;color:#555;font-size:13.5px"><b>Votre demande :</b><br>' . nl2br(h($msg['message'])) . '</div>';
    $html = mail_layout('Réponse à votre demande', '<p>Bonjour ' . h($msg['nom']) . ',</p><p>' . nl2br(h($text)) . '</p><p>Cordialement,<br><b>AP Toiture</b></p>' . $quoted);
    $ok = send_mail($msg['email'], 'Réponse à votre demande - AP Toiture', $html, "Bonjour " . $msg['nom'] . ",\n\n$text\n\nCordialement,\nAP Toiture\n06 25 44 11 31", MAIL_TO, 'AP Toiture');
    if (!$ok) jout(['error' => 'L\'e-mail n\'a pas pu être envoyé. Répondez directement à ' . $msg['email']], 500);
    update_json('messages', [], function (&$l) use ($in, $text) { foreach ($l as &$m) if ($m['id'] === $in['id']) { $m['read'] = true; $m['replies'][] = ['date' => date('c'), 'text' => $text]; } });
    jout(['ok' => true]);

  case 'pw':
    $acc = admin_account();
    if (!password_verify((string)($in['current'] ?? ''), $acc['hash'])) jout(['error' => 'Mot de passe actuel incorrect.'], 403);
    $new = (string)($in['new'] ?? ''); if (mb_strlen($new) < 10) jout(['error' => 'Nouveau mot de passe : 10 caractères minimum.'], 422);
    $email = trim((string)($in['email'] ?? $acc['email'])); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jout(['error' => 'E-mail invalide.'], 422);
    write_json('admin', ['email' => $email, 'hash' => password_hash($new, PASSWORD_DEFAULT)]);
    jout(['ok' => true]);
}
jout(['error' => 'Action inconnue'], 404);
