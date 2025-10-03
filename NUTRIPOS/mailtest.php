<?php
// Simple PHP mail() test using environment variables
// Note: On Windows, mail() uses SMTP settings from php.ini (SMTP, smtp_port, sendmail_from).
// We will load EMAIL_HOST, EMAIL_PORT, EMAIL_USERNAME from .env and set these ini values at runtime.
// On Linux/macOS, mail() relies on sendmail/postfix and these ini settings may not be used.

// Usage:
//   - Visit: mailtest.php?to=you@example.com
//   - Optionally override subject/message via query string: &subject=Test&message=Hello

header('Content-Type: text/plain');

// Load env vars if available
require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
// Suppress errors if .env missing; adjust as needed
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load();
}

// Apply Windows mail() SMTP settings from env (no effect on Linux sendmail)
$envHost = $_ENV['EMAIL_HOST'] ?? '';
$envPort = isset($_ENV['EMAIL_PORT']) ? (int)$_ENV['EMAIL_PORT'] : 0;
$envUser = $_ENV['EMAIL_USERNAME'] ?? '';

if ($envHost !== '') {
    @ini_set('SMTP', $envHost);
}
if ($envPort > 0) {
    @ini_set('smtp_port', (string)$envPort);
}
if ($envUser !== '') {
    @ini_set('sendmail_from', $envUser);
}

// Shorten socket timeout to avoid long hangs
@ini_set('default_socket_timeout', '10');

$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : 'PHP mail() test';
$message = isset($_GET['message']) ? trim($_GET['message']) : 'This is a simple test email sent via PHP mail() at ' . date('c');

if ($to === '') {
    echo "No recipient provided. Append ?to=you@example.com to the URL.\n";
    echo "Example: mailtest.php?to=youraddress@domain.com\n\n";
    echo "php.ini hints (Windows):\n - SMTP=smtp.example.com\n - smtp_port=25 (or 587)\n - sendmail_from=noreply@example.com\n";
    exit;
}

// Basic headers. Ensure sendmail_from is set in php.ini on Windows if From isn't honored.
$from = $envUser !== '' ? $envUser : 'noreply@example.com';
$headers = [];
$headers[] = 'From: ' . $from;
$headers[] = 'Reply-To: ' . $from;
$headers[] = 'X-Mailer: PHP/' . phpversion();
$headersStr = implode("\r\n", $headers);

$start = microtime(true);
$result = @mail($to, $subject, $message, $headersStr);
$elapsed = round(microtime(true) - $start, 3);

echo "mail() returned: " . ($result ? 'true' : 'false') . "\n";
echo "Elapsed: {$elapsed}s\n";

echo "To: {$to}\nSubject: {$subject}\n\n";

// Show effective settings for troubleshooting (primarily Windows)
echo "SMTP host: " . ini_get('SMTP') . "\n";
echo "SMTP port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n";

if (!$result) {
    echo "If false or hanging, check:\n";
    echo " - php.ini SMTP/smtp_port/sendmail_from settings (Windows).\n";
    echo " - Local firewall/ISP blocking outbound SMTP.\n";
    echo " - Use a dedicated SMTP library (PHPMailer) if mail() is unavailable.\n";
}
