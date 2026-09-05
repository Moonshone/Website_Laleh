<?php

declare(strict_types=1);

const CONTACT_ADDRESS = 'laleh.barzegar.art@gmail.com';

function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Method not allowed.');
}

// Bots commonly fill this visually hidden field; return success without sending.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, true, 'Thank you. Your message has been sent.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $message === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respond(422, false, 'Please provide your name, a valid email address, and a message.');
}

if (preg_match('/[\r\n]/', $email) || strlen($name) > 120 || strlen($email) > 254 || strlen($message) > 10000) {
    respond(422, false, 'One or more fields are invalid or too long.');
}

$safeName = preg_replace('/[\r\n]+/', ' ', $name);
$subject = 'Website contact from ' . $safeName;
$body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}\n";
$headers = [
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

// Prefer the sender identity configured for this web server. The recipient's
// Gmail address and the visitor's address must not be used as the technical From.
$fromAddress = trim((string) (getenv('CONTACT_FROM_ADDRESS') ?: ini_get('sendmail_from')));
if ($fromAddress !== '') {
    if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $fromAddress)) {
        error_log('Contact form: CONTACT_FROM_ADDRESS/sendmail_from is not a valid email address.');
        respond(500, false, 'Your message could not be sent. Please try again later.');
    }
    $headers[] = 'From: ' . $fromAddress;
}

if (!mail(CONTACT_ADDRESS, $subject, $body, implode("\r\n", $headers))) {
    error_log('Contact form: mail() rejected the message for delivery.');
    respond(500, false, 'Your message could not be sent. Please try again later.');
}

respond(200, true, 'Thank you. Your message has been sent.');
