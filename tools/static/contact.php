<?php
require __DIR__ . '/_app/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(['ok' => false], 405);
if (!empty($_POST['website'])) jout(['ok' => true]); // anti-robot
if (!rate_limit('contact:' . client_ip(), 6, 3600)) jout(['ok' => false, 'error' => 'limit'], 429);
function cl($s, $n) { return trim(str_replace(["\r", "\n"], ' ', strip_tags(mb_substr((string)$s, 0, $n)))); }
$nom = cl($_POST['nom'] ?? '', 100); $tel = cl($_POST['tel'] ?? '', 40); $email = cl($_POST['email'] ?? '', 150);
$type = cl($_POST['type'] ?? '', 60); $msg = trim(strip_tags(mb_substr((string)($_POST['message'] ?? ''), 0, 5000)));
if ($nom === '' || $tel === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) jout(['ok' => false, 'error' => 'invalid'], 422);

$m = ['id' => new_id(), 'date' => date('c'), 'nom' => $nom, 'tel' => $tel, 'email' => $email, 'type' => $type, 'message' => $msg, 'read' => false, 'replies' => []];
update_json('messages', [], function (&$l) use ($m) { array_unshift($l, $m); });

// notification par e-mail (la demande est déjà enregistrée dans l'espace admin même si l'envoi échoue)
$row = fn($k, $v) => '<tr><td style="padding:8px 14px 8px 0;color:#777;white-space:nowrap;vertical-align:top">' . $k . '</td><td style="padding:8px 0;font-weight:600">' . $v . '</td></tr>';
$inner = '<p style="margin-top:0">Une personne vient de demander un devis depuis votre site.</p>'
  . '<table cellpadding="0" cellspacing="0" style="width:100%;border-top:1px solid #eee;border-bottom:1px solid #eee;margin:14px 0">'
  . $row('Nom', h($nom)) . $row('Téléphone', '<a href="tel:' . h(preg_replace('/[^\d+]/', '', $tel)) . '" style="color:#d42a26;text-decoration:none">' . h($tel) . '</a>')
  . $row('E-mail', '<a href="mailto:' . h($email) . '" style="color:#d42a26;text-decoration:none">' . h($email) . '</a>') . $row('Travaux', h($type ?: '—')) . '</table>'
  . '<div style="padding:16px 18px;background:#f7f7f8;border-left:4px solid #d42a26;border-radius:6px;white-space:normal">' . ($msg !== '' ? nl2br(h($msg)) : '<i style="color:#888">Aucun message</i>') . '</div>'
  . '<p style="color:#777;font-size:13px;margin-top:18px">Vous pouvez répondre directement à ce mail (le client recevra votre réponse) ou depuis votre espace administrateur.</p>';
$html = mail_layout('Nouvelle demande de devis', $inner, ['Répondre depuis le site', SITE_URL . '/admin/messages.php?id=' . $m['id']]);
$text = "Nouvelle demande de devis\n\nNom : $nom\nTéléphone : $tel\nE-mail : $email\nTravaux : $type\n\n$msg\n\nRépondre : " . SITE_URL . "/admin/messages.php?id=" . $m['id'];
send_mail(MAIL_TO, "Demande de devis - $nom" . ($type ? " ($type)" : ''), $html, $text, "$nom <$email>", 'Site AP Toiture');
jout(['ok' => true]);
