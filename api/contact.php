<?php
// Pont formulaire -> CRM operator. Le jeton reste côté serveur : il ne doit
// JAMAIS apparaître dans le JavaScript de la page.
// Renseigner CRM_TOKEN dans api/config.php (fichier non versionné).
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo json_encode(['error' => 'method']); exit;
}
$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) { http_response_code(500); echo json_encode(['error' => 'config']); exit; }
require $cfg; // définit CRM_TOKEN et CRM_URL

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { http_response_code(400); echo json_encode(['error' => 'json']); exit; }

// piège à robots : si rempli, on fait semblant d'accepter
if (!empty($in['site_web'])) { echo json_encode(['ok' => true]); exit; }

$name  = trim($in['name']  ?? '');
$email = trim($in['email'] ?? '');
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400); echo json_encode(['error' => 'champs']); exit;
}

$notes = "Demande depuis " . trim($in["origine"] ?? "sabinecaizergues.fr") . "\n"
       . "Besoin : "   . trim($in['besoin']  ?? 'non précisé') . "\n"
       . "Message : "  . trim($in['message'] ?? '');

$payload = json_encode([
  'name'        => $name,
  'company'     => trim($in['company'] ?? ''),
  'email'       => $email,
  'phone'       => trim($in['phone'] ?? ''),
  'source'      => 'organic',
  'notes'       => $notes,
  'projectSlug' => in_array(($in['projectSlug'] ?? ''), ['sites','site-vitrine'], true)
                     ? $in['projectSlug'] : 'sites',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(CRM_URL);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 15,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . CRM_TOKEN],
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) { echo json_encode(['ok' => true]); }
else { http_response_code(502); echo json_encode(['error' => 'crm', 'code' => $code]); }
