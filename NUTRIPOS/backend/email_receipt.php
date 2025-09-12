<?php
declare(strict_types=1);

header_remove();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ob_start(); // capture stray output

require_once __DIR__.'/db.php';

$cfgAll = config();
$cfg = $cfgAll['mail'] ?? ['enabled'=>false];

// Inputs
$id = (int)($_POST['order_id'] ?? 0);
$to = trim((string)($_POST['to'] ?? ''));
if (!$id || $to === ''){
  $noise = ob_get_clean();
  echo json_encode(['error'=>'missing order_id/to','details'=>$noise], JSON_UNESCAPED_SLASHES);
  exit;
}

// Load order + items
try{
  $pdo = pdo();
  $o = $pdo->prepare("SELECT * FROM orders WHERE id=:id");
  $o->execute([':id'=>$id]);
  $order = $o->fetch();
  if (!$order){
    $noise = ob_get_clean();
    echo json_encode(['error'=>'order not found','details'=>$noise], JSON_UNESCAPED_SLASHES);
    exit;
  }
  $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=:id");
  $itemsStmt->execute([':id'=>$id]);
  $items = $itemsStmt->fetchAll();
}
catch(Throwable $e){
  if (ob_get_length() !== false) ob_end_clean();
  echo json_encode(['error'=>'db failure','details'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
  exit;
}

// Build QR payload
$payload = json_encode([
  'id'        => (int)$order['id'],
  'energy_kj' => (float)$order['energy_kj'],
  'kcal'      => (float)$order['calories_kcal'],
  'p'         => (float)$order['protein_g'],
  'f'         => (float)$order['fat_g'],
  'c'         => (float)$order['carb_g'],
  's'         => (float)$order['sugars_g'],
  'na'        => (float)$order['sodium_mg'],
], JSON_UNESCAPED_SLASHES);

// If mail disabled, just “pretend-sent” with a link, so UI doesn’t break
if (empty($cfg['enabled'])){
  $noise = ob_get_clean();
  echo json_encode(['ok'=>true, 'note'=>'mail disabled', 'receipt_url'=>receiptUrl($order)], JSON_UNESCAPED_SLASHES);
  exit;
}

// Ensure Composer deps exist
$autoload = __DIR__.'/vendor/autoload.php';
if (!is_file($autoload)){
  $noise = ob_get_clean();
  echo json_encode(['error'=>'dependencies missing: run composer install'], JSON_UNESCAPED_SLASHES);
  exit;
}
require_once $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Generate QR PNG
$options = new QROptions([
  'outputType' => QRCode::OUTPUT_IMAGE_PNG,
  'scale'      => 6,
  'margin'     => 1,
  'eccLevel'   => QRCode::ECC_M,
]);
$qr = (new QRCode($options))->render($payload); // binary png

// Build items HTML
$rows = '';
foreach ($items as $it){
  $n = htmlspecialchars((string)($it['name'] ?? ''), ENT_QUOTES, 'UTF-8');
  $c = htmlspecialchars((string)($it['afcd_code'] ?? ''), ENT_QUOTES, 'UTF-8');
  $g = number_format((float)$it['grams'], 2);
  $q = (int)$it['qty'];
  $rows .= "<tr><td>{$n}</td><td>{$c}</td><td style='text-align:right'>{$g}</td><td style='text-align:right'>{$q}</td></tr>";
}

// Compose mail
$mail = new PHPMailer(true);
try{
  $mail->isSMTP();
  $mail->Host       = (string)$cfg['smtp_host'];
  $mail->SMTPAuth   = true;
  $mail->Username   = (string)$cfg['smtp_user'];
  $mail->Password   = (string)$cfg['smtp_pass'];
  $mail->SMTPSecure = (string)$cfg['smtp_secure'];
  $mail->Port       = (int)$cfg['smtp_port'];

  $mail->setFrom((string)$cfg['from_email'], (string)$cfg['from_name']);
  $mail->addAddress($to);

  $cid = 'qrinline@nutripos';
  $mail->addStringEmbeddedImage($qr, $cid, 'qr.png', 'base64', 'image/png');
  $mail->addStringAttachment($qr, 'nutri-qr.png', 'base64', 'image/png');

  $mail->isHTML(true);
  $mail->Subject = "Your NutriPOS receipt #{$order['id']}";

  $nutri = sprintf(
    'Energy: %.1f kJ (%.0f kcal)<br/>Protein: %.2f g &nbsp; Fat: %.2f g<br/>Carbs: %.2f g &nbsp; Sugars: %.2f g<br/>Sodium: %.0f mg',
    (float)$order['energy_kj'], (float)$order['calories_kcal'],
    (float)$order['protein_g'], (float)$order['fat_g'],
    (float)$order['carb_g'], (float)$order['sugars_g'], (float)$order['sodium_mg']
  );

  $mail->Body = "
    <p>Thanks for your order. Here is your nutrition summary:</p>
    <p>{$nutri}</p>
    <p><strong>Scan QR for payload:</strong><br/>
    <img src='cid:{$cid}' alt='QR code' width='256' height='256' style='border:1px solid #eee;'/></p>
    <p><a href='".receiptUrl($order)."'>Open receipt in browser</a></p>
    <hr/>
    <p><strong>Items</strong></p>
    <table cellpadding='6' cellspacing='0' border='0' style='border-collapse:collapse'>
      <thead><tr><th align='left'>Name</th><th align='left'>Code</th><th align='right'>Grams</th><th align='right'>Qty</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  ";

  $mail->AltBody =
    "Your NutriPOS receipt #{$order['id']}\n\n".
    "Energy: {$order['energy_kj']} kJ ({$order['calories_kcal']} kcal)\n".
    "Protein: {$order['protein_g']} g, Fat: {$order['fat_g']} g\n".
    "Carbs: {$order['carb_g']} g, Sugars: {$order['sugars_g']} g\n".
    "Sodium: {$order['sodium_mg']} mg\n\n".
    "Open receipt: ".receiptUrl($order)."\n";

  $mail->send();

  $noise = ob_get_clean();
  if ($noise !== '') {
    echo json_encode(['ok'=>true, 'warning'=>'server output detected', 'details'=>$noise], JSON_UNESCAPED_SLASHES);
    exit;
  }
  echo json_encode(['ok'=>true], JSON_UNESCAPED_SLASHES);
  exit;

} catch (Exception $e){
  if (ob_get_length() !== false) ob_end_clean();
  echo json_encode(['error'=>'mail send failed', 'details'=>$mail->ErrorInfo], JSON_UNESCAPED_SLASHES);
  exit;
}

function receiptUrl(array $order): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme.'://'.$host.'/NutriPOS/public/receipt.html?id='.$order['id'];
}
