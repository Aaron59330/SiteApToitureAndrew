<?php
// Bibliothèque commune : stockage, session admin, rendu des pages, e-mails.
define('APP', __DIR__);
define('ROOT', dirname(__DIR__));
define('DATA', APP . '/data');
define('UPLOADS', ROOT . '/uploads');
require APP . '/config.php';
if (!is_dir(DATA)) @mkdir(DATA, 0700, true);
if (!is_dir(UPLOADS)) @mkdir(UPLOADS, 0755, true);
date_default_timezone_set('Europe/Paris');

// ---------- stockage JSON (protégé : fichiers .php qui s'auto-bloquent)
function jfile($name) { return DATA . '/' . $name . '.php'; }
function read_json($name, $default = null) {
  $f = jfile($name);
  if (!is_file($f)) return $default;
  $raw = file_get_contents($f);
  $i = strpos($raw, "\n");
  $j = json_decode($i === false ? '' : substr($raw, $i + 1), true);
  return $j === null ? $default : $j;
}
function write_json($name, $data) {
  $f = jfile($name); $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  file_put_contents($tmp, "<?php http_response_code(404); exit; ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
  rename($tmp, $f);
}
function update_json($name, $default, $fn) {
  $lock = fopen(DATA . '/.lock', 'c'); flock($lock, LOCK_EX);
  $data = read_json($name, $default);
  $res = $fn($data);
  write_json($name, $data);
  flock($lock, LOCK_UN); fclose($lock);
  return $res;
}
function secret() {
  $f = DATA . '/secret.php';
  if (is_file($f)) return trim(explode("\n", file_get_contents($f), 2)[1] ?? '');
  $s = bin2hex(random_bytes(32));
  file_put_contents($f, "<?php http_response_code(404); exit; ?>\n" . $s);
  return $s;
}

// ---------- session administrateur
const COOKIE = 'ap_session';
const SESSION_S = 43200;
function sign($p) { return hash_hmac('sha256', $p, secret()); }
function is_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function set_session() {
  $p = 'admin.' . (time() + SESSION_S);
  setcookie(COOKIE, $p . '.' . sign($p), ['expires' => time() + SESSION_S, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
function clear_session() { setcookie(COOKIE, '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']); }
function is_admin() {
  $t = $_COOKIE[COOKIE] ?? '';
  $p = explode('.', $t);
  if (count($p) !== 3 || $p[0] !== 'admin' || (int)$p[1] < time()) return false;
  return hash_equals(sign($p[0] . '.' . $p[1]), $p[2]);
}
function admin_account() { return read_json('admin', null) ?: (defined('ADMIN_DEFAULT') ? ADMIN_DEFAULT : null); }
function verify_login($email, $pw) {
  $a = admin_account();
  $okMail = $a && hash_equals(strtolower(trim($a['email'])), strtolower(trim((string)$email)));
  $okPw = password_verify((string)$pw, $a['hash'] ?? password_hash('x', PASSWORD_DEFAULT));
  return $a && $okMail && $okPw;
}
function rate_limit($key, $max = 8, $win = 900) {
  $ok = true;
  update_json('attempts', [], function (&$d) use ($key, $max, $win, &$ok) {
    $now = time(); $d[$key] = array_values(array_filter($d[$key] ?? [], fn($t) => $now - $t < $win));
    if (count($d[$key]) >= $max) { $ok = false; return; }
    $d[$key][] = $now;
  });
  return $ok;
}
function reset_limit($key) { update_json('attempts', [], function (&$d) use ($key) { unset($d[$key]); }); }
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0'; }

// ---------- utilitaires
function jout($data, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function clip($s, $n) { return mb_substr((string)($s ?? ''), 0, $n); }
function new_id() { return bin2hex(random_bytes(6)); }
function body_json() { return json_decode(file_get_contents('php://input'), true) ?: []; }

// ---------- images (redimensionne et convertit)
function save_image($tmp) {
  $info = @getimagesize($tmp);
  if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) return null;
  $img = @imagecreatefromstring(file_get_contents($tmp));
  if (!$img) return null;
  if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $ex = @exif_read_data($tmp); $o = $ex['Orientation'] ?? 1;
    $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($rot) $img = imagerotate($img, $rot, 0);
  }
  $w = imagesx($img); $hh = imagesy($img); $max = 2000;
  if ($w > $max || $hh > $max) {
    $r = min($max / $w, $max / $hh); $nw = (int)round($w * $r); $nh = (int)round($hh * $r);
    $n = imagecreatetruecolor($nw, $nh); imagealphablending($n, false); imagesavealpha($n, true);
    imagecopyresampled($n, $img, 0, 0, 0, 0, $nw, $nh, $w, $hh); $img = $n;
  }
  $name = bin2hex(random_bytes(10));
  if (function_exists('imagewebp')) { $name .= '.webp'; imagewebp($img, UPLOADS . '/' . $name, 82); }
  else { $name .= '.jpg'; imagejpeg($img, UPLOADS . '/' . $name, 85); }
  return '/uploads/' . $name;
}

// ---------- e-mails
function mail_layout($title, $inner, $cta = null) {
  $btn = $cta ? '<p style="margin:26px 0 4px"><a href="' . h($cta[1]) . '" style="background:#d42a26;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px;display:inline-block">' . h($cta[0]) . '</a></p>' : '';
  return '<!DOCTYPE html><html><body style="margin:0;background:#f2f3f5;font-family:Segoe UI,Arial,sans-serif;color:#222">'
   . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3f5;padding:28px 12px"><tr><td align="center">'
   . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden">'
   . '<tr><td style="background:#d42a26;padding:22px 30px;color:#fff"><div style="font-size:13px;letter-spacing:.08em;opacity:.9">AP TOITURE</div><div style="font-size:22px;font-weight:700;margin-top:4px">' . h($title) . '</div></td></tr>'
   . '<tr><td style="padding:28px 30px;font-size:15px;line-height:1.6">' . $inner . $btn . '</td></tr>'
   . '<tr><td style="background:#1a1a1a;color:#a5a5a5;padding:18px 30px;font-size:12.5px;line-height:1.6">AP Toiture – Couverture · Zinguerie · Pignon · Velux<br>59330 Hautmont · 06 25 44 11 31 · apcouvreur@gmail.com</td></tr>'
   . '</table></td></tr></table></body></html>';
}
function send_mail($to, $subject, $html, $text, $replyTo = null, $fromName = 'AP Toiture') {
  $b = '=_' . bin2hex(random_bytes(8));
  $h = "From: $fromName <no-reply@" . MAIL_DOMAIN . ">\r\n";
  if ($replyTo) $h .= "Reply-To: $replyTo\r\n";
  $h .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"\r\n";
  $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--";
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  return function_exists('mail') ? @mail($to, $subj, $body, $h, '-f no-reply@' . MAIL_DOMAIN) : false;
}

// ---------- rendu des pages (texte / images modifiables)
const INLINE = ['br', 'b', 'strong', 'em', 'i'];
const SKIP = ['script', 'style', 'svg', 'textarea', 'select', 'option', 'input', 'noscript', 'iframe'];

function dom_load($html) {
  $d = new DOMDocument('1.0', 'UTF-8'); libxml_use_internal_errors(true);
  $d->loadHTML('<?xml encoding="UTF-8">' . $html);
  foreach (iterator_to_array($d->childNodes) as $n) if ($n->nodeType === XML_PI_NODE) $d->removeChild($n);
  libxml_clear_errors();
  $d->encoding = 'UTF-8';
  return $d;
}
function inner_html($el) { $o = ''; foreach ($el->childNodes as $c) $o .= $el->ownerDocument->saveHTML($c); return $o; }
function set_inner_html($el, $html) {
  while ($el->firstChild) $el->removeChild($el->firstChild);
  $tmp = dom_load('<html><body><div id="r">' . $html . '</div></body></html>');
  $r = $tmp->getElementById('r');
  foreach (iterator_to_array($r->childNodes) as $c) $el->appendChild($el->ownerDocument->importNode($c, true));
}
function sanitize_walk($nodes) {
  $out = '';
  foreach ($nodes as $n) {
    if ($n->nodeType === XML_TEXT_NODE) { $out .= htmlspecialchars(preg_replace('/\s+/u', ' ', $n->nodeValue), ENT_NOQUOTES | ENT_SUBSTITUTE); }
    elseif ($n->nodeType === XML_ELEMENT_NODE) {
      $t = strtolower($n->nodeName);
      if ($t === 'br') $out .= '<br>';
      elseif ($t === 'b' || $t === 'strong') $out .= '<b>' . sanitize_walk($n->childNodes) . '</b>';
      elseif ($t === 'em' || $t === 'i') $out .= '<em>' . sanitize_walk($n->childNodes) . '</em>';
      elseif ($t === 'div' || $t === 'p') $out .= ($out !== '' && substr($out, -4) !== '<br>' ? '<br>' : '') . sanitize_walk($n->childNodes);
      elseif (!in_array($t, SKIP, true)) $out .= sanitize_walk($n->childNodes);
    }
  }
  return $out;
}
function sanitize($html) {
  $d = dom_load('<html><body><div id="r">' . (string)$html . '</div></body></html>');
  $out = sanitize_walk($d->getElementById('r')->childNodes);
  return trim(preg_replace('/(<br>)+$/', '', $out));
}
function has_class($el, $c) { return $el->nodeType === XML_ELEMENT_NODE && in_array($c, preg_split('/\s+/', $el->getAttribute('class')), true); }
function closest($el, $fn) { for ($n = $el; $n && $n->nodeType === XML_ELEMENT_NODE; $n = $n->parentNode) if ($fn($n)) return $n; return null; }
function is_global($el) { return (bool)closest($el, fn($n) => in_array(strtolower($n->nodeName), ['header', 'footer'], true) || has_class($n, 'topbar')); }
function is_noedit($el) { return (bool)closest($el, fn($n) => $n->hasAttribute('data-noedit')); }
function in_svg($el) { return (bool)closest($el, fn($n) => strtolower($n->nodeName) === 'svg'); }

function process_page($raw, $page, $admin = false, $overrides = [], $gallery = null) {
  $d = dom_load($raw);
  $body = $d->getElementsByTagName('body')->item(0);
  if ($gallery !== null) fill_gallery($d, $gallery, $admin);

  // 1. enveloppe les textes « mixtes » dans des <span>
  $all = []; foreach ($body->getElementsByTagName('*') as $e) $all[] = $e;
  foreach ($all as $el) {
    $nm = strtolower($el->nodeName);
    if (in_array($nm, SKIP, true) || is_noedit($el) || in_svg($el)) continue;
    $mixed = false;
    foreach ($el->childNodes as $k) if ($k->nodeType === XML_ELEMENT_NODE && !in_array(strtolower($k->nodeName), INLINE, true)) { $mixed = true; break; }
    if (!$mixed) continue;
    foreach (iterator_to_array($el->childNodes) as $k) {
      if ($k->nodeType === XML_TEXT_NODE && trim($k->nodeValue) !== '') {
        $s = $d->createElement('span'); $s->appendChild($d->createTextNode($k->nodeValue)); $el->replaceChild($s, $k);
      }
    }
  }

  $texts = []; $imgs = []; $cnt = ['g' => 0, 'p' => 0, 'gi' => 0, 'pi' => 0];
  $ovG = $overrides['global'] ?? []; $ovP = ($overrides['pages'] ?? [])[$page] ?? [];
  $visit = function ($el) use (&$visit, &$texts, &$imgs, &$cnt, $ovG, $ovP, $admin, $d) {
    if ($el->nodeType !== XML_ELEMENT_NODE) return;
    $nm = strtolower($el->nodeName);
    if (in_array($nm, SKIP, true) || $el->hasAttribute('data-noedit')) return;
    if (has_class($el, 'srv-ico')) { // bloc « icône » remplaçable par une photo
      $key = 'pi' . $cnt['pi']++; $imgs[$key] = 'icon';
      $o = $ovP[$key] ?? null;
      if ($o && ($o['o'] ?? null) === 'icon' && is_string($o['v'] ?? null)) set_inner_html($el, '<img src="' . h($o['v']) . '" alt="">');
      if ($admin) { $el->setAttribute('data-edi', $key); $el->setAttribute('data-edi-orig', 'icon'); }
      return;
    }
    if ($nm === 'img') {
      $sc = is_global($el) ? 'g' : 'p'; $key = $sc . 'i' . $cnt[$sc . 'i']++;
      $orig = $el->getAttribute('src'); $imgs[$key] = $orig;
      $o = ($sc === 'g' ? $ovG : $ovP)[$key] ?? null;
      if ($o && ($o['o'] ?? null) === $orig && is_string($o['v'] ?? null)) $el->setAttribute('src', $o['v']);
      if ($admin) { $el->setAttribute('data-edi', $key); $el->setAttribute('data-edi-orig', $orig); }
      return;
    }
    $leaf = $el->childNodes->length > 0; $txt = '';
    foreach ($el->childNodes as $k) {
      if ($k->nodeType === XML_TEXT_NODE || $k->nodeType === XML_COMMENT_NODE) { if ($k->nodeType === XML_TEXT_NODE) $txt .= $k->nodeValue; continue; }
      if ($k->nodeType === XML_ELEMENT_NODE && in_array(strtolower($k->nodeName), INLINE, true)) { $txt .= $k->textContent; continue; }
      $leaf = false; break;
    }
    if ($leaf && trim($txt) !== '') {
      $sc = is_global($el) ? 'g' : 'p'; $key = $sc . $cnt[$sc]++;
      $orig = sanitize(inner_html($el)); $texts[$key] = $orig;
      $o = ($sc === 'g' ? $ovG : $ovP)[$key] ?? null;
      if ($o && ($o['o'] ?? null) === $orig && is_string($o['v'] ?? null)) { set_inner_html($el, $o['v']); sync_link($el); }
      if ($admin) { $el->setAttribute('data-ed', $key); $el->setAttribute('data-ed-orig', $orig); }
      return;
    }
    foreach (iterator_to_array($el->childNodes) as $k) $visit($k);
  };
  foreach (iterator_to_array($body->childNodes) as $c) $visit($c);

  if ($admin) {
    $head = $d->getElementsByTagName('head')->item(0);
    $l = $d->createElement('link'); $l->setAttribute('rel', 'stylesheet'); $l->setAttribute('href', '/admin/admin.css'); $head->appendChild($l);
    $body->setAttribute('data-admin', ''); $body->setAttribute('data-page', $page);
    $s = $d->createElement('script'); $s->setAttribute('src', '/admin/admin.js'); $s->setAttribute('defer', ''); $body->appendChild($s);
  }
  return [$d, $texts, $imgs];
}
function sync_link($el) {
  $a = strtolower($el->nodeName) === 'a' ? $el : closest($el, fn($n) => strtolower($n->nodeName) === 'a');
  if (!$a) return;
  $href = $a->getAttribute('href'); $txt = trim($a->textContent);
  if (strpos($href, 'tel:') === 0) { $dg = preg_replace('/[^\d+]/', '', $txt); if (strlen($dg) >= 6) $a->setAttribute('href', 'tel:' . ($dg[0] === '0' ? '+33' . substr($dg, 1) : $dg)); }
  elseif (strpos($href, 'mailto:') === 0 && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $txt)) $a->setAttribute('href', 'mailto:' . $txt);
}
function projects_norm($l) {
  foreach ($l as &$p) {
    if (isset($p['file']) && !isset($p['photos'])) { $p['photos'] = [$p['file']]; unset($p['file']); }
    $p['photos'] = array_values($p['photos'] ?? []); $p['category'] = $p['category'] ?? ''; $p['title'] = $p['title'] ?? ''; $p['description'] = $p['description'] ?? '';
  }
  return $l;
}
function read_projects() { return projects_norm(read_json('realisations', [])); }
function fill_gallery($d, $items, $admin) {
  $g = $d->getElementById('gallery'); if (!$g) return;
  $g->setAttribute('data-noedit', '');
  $items = array_values(array_filter(projects_norm($items), fn($p) => !empty($p['photos'])));
  if (!$items) { set_inner_html($g, '<p class="empty">' . ($admin ? 'Aucun chantier pour le moment. Utilisez « Nouveau chantier » ci-dessus.' : 'Les réalisations seront bientôt publiées.') . '</p>'); return; }
  $html = '';
  foreach ($items as $it) {
    $n = count($it['photos']);
    $data = h(json_encode($it['photos'], JSON_UNESCAPED_SLASHES));
    $html .= '<article class="proj" tabindex="0" role="button" data-id="' . h($it['id']) . '" data-title="' . h($it['title']) . '" data-cat="' . h($it['category']) . '" data-desc="' . h($it['description']) . '" data-photos="' . $data . '">'
      . '<img src="' . h($it['photos'][0]) . '" alt="' . h($it['title'] ?: 'Réalisation AP Toiture') . '" loading="lazy">'
      . '<span class="proj-count">' . $n . ' photo' . ($n > 1 ? 's' : '') . '</span>'
      . '<div class="proj-info"><h3>' . h($it['title'] ?: 'Sans titre') . '</h3>' . ($it['category'] !== '' ? '<p>' . h($it['category']) . '</p>' : '') . '<span class="proj-more">Voir le détail &rarr;</span></div></article>';
  }
  set_inner_html($g, $html);
}
function page_list() { return ['index', 'services', 'realisations', 'a-propos', 'contact']; }
function page_raw($p) { return in_array($p, page_list(), true) ? file_get_contents(APP . '/pages/' . $p . '.html') : null; }
