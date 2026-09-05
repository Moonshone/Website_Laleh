<?php

declare(strict_types=1);

const CONTACT_ADDRESS = 'lalehbarzegar@gmail.com';

function respond(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, 'Method not allowed.');
}

// Bots commonly fill this visually hidden field; return success without sending.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(200, 'Thank you. Your message has been sent.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $message === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respond(422, 'Please provide your name, a valid email address, and a message.');
}

if (preg_match('/[\r\n]/', $email) || strlen($name) > 120 || strlen($email) > 254 || strlen($message) > 10000) {
    respond(422, 'One or more fields are invalid or too long.');
}

$safeName = preg_replace('/[\r\n]+/', ' ', $name);
$subject = 'Website contact from ' . $safeName;
$body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}\n";
$headers = [
    'From: ' . CONTACT_ADDRESS,
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

if (!mail(CONTACT_ADDRESS, $subject, $body, implode("\r\n", $headers))) {
    error_log('Contact form: mail() rejected the message for delivery.');
    respond(500, 'Your message could not be sent. Please try again later.');
}

respond(200, 'Thank you. Your message has been sent.');
