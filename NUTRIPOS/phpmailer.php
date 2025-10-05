<?php

require '../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $_ENV['EMAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['EMAIL_USERNAME'];
    $mail->Password   = $_ENV['EMAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $_ENV['EMAIL_PORT'];

    // Recipients
    $mail->setFrom($_ENV['EMAIL_USERNAME'], 'NutriPOS');
    $mail->addAddress($_ENV['EMAIL_USERNAME'], 'NutriPOS');
    // $mail->addBCC($_ENV['EMAIL_USERNAME'], 'NutriPOS');

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = <<<HTML
                <h3>Hello!</h3>
                <p>This is a test email.</p>
                HTML;

    $mail->send();
    echo 'Message has been sent successfully!';
} catch (Exception $e) {
    echo "Message could not be sent.\n{$mail->ErrorInfo}";
}
