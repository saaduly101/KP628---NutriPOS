<?php

require '../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$mail = new PHPMailer(true); // Enable exceptions

// SMTP Configuration
$mail->SMTPDebug = 2; 
$mail->isSMTP();
$mail->Host = $_ENV['EMAIL_HOST'];
$mail->SMTPAuth = true;
$mail->Username = $_ENV['EMAIL_USERNAME'];
$mail->Password = $_ENV['EMAIL_PASSWORD'];
// Use PHPMailer constants for clarity; adjust to ENCRYPTION_SMTPS with port 465 if your provider requires SSL
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = (int)$_ENV['EMAIL_PORT'];
// Prevent long hangs on bad connectivity
$mail->Timeout = 10; // seconds
$mail->SMTPKeepAlive = false;
$mail->SMTPAutoTLS = true;

// Sender and recipient settings
$mail->setFrom($_ENV['EMAIL_USERNAME'], 'From Name');
$mail->addAddress($_ENV['EMAIL_USERNAME'], 'Recipient Name');
$mail->isHTML(false); // Set email format to plain text
$mail->Subject = 'Your Receipt - NutriPOS';
$mail->Body    = 'This is just an example email!';

if(!$mail->send()){
    echo 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}