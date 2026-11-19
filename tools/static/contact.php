<?php
// Envoi du formulaire de devis par e-mail (hébergement IONOS mutualisé).
header('Content-Type: application/json; charset=utf-8');
$to = 'apcouvreur@gmail.com';
$domain = 'aptoiture.eu';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
// Anti-spam : champ piège rempli par les robots
if (!empty($_POST['website'])) { echo '{"ok":true}'; exit; }

function clean($s) { return trim(str_replace(["\r", "\n"], ' ', strip_tags((string)$s))); }
$nom   = clean($_POST['nom'] ?? '');
$tel   = clean($_POST['tel'] ?? '');
$email = clean($_POST['email'] ?? '');
$type  = clean($_POST['type'] ?? '');
$msg   = trim(strip_tags((string)($_POST['message'] ?? '')));

if ($nom === '' || $tel === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422); echo '{"ok":false,"error":"invalid"}'; exit;
}

$subject = '=?UTF-8?B?' . base64_encode("Demande de devis - $nom ($type)") . '?=';
$body = "Nouvelle demande de devis depuis $domain\n\n"
      . "Nom : $nom\nTéléphone : $tel\nE-mail : $email\nTravaux : $type\n\nMessage :\n$msg\n";
$headers = "From: Site AP Toiture <no-reply@$domain>\r\n"
         . "Reply-To: $nom <$email>\r\n"
         . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

$ok = mail($to, $subject, $body, $headers, "-f no-reply@$domain");
if (!$ok) http_response_code(500);
echo json_encode(['ok' => $ok]);
