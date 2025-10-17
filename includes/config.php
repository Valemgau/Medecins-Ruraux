<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

setlocale(LC_TIME, 'fr_FR.UTF-8');

session_start();



// set_error_handler(function ($severity, $message, $file, $line) {
//     throw new ErrorException($message, 0, $severity, $file, $line);
// });

// // Gestionnaire global d’exception non attrapée
// set_exception_handler(function ($exception) {
//     header('HTTP/1.1 404 Not Found');
//     include __DIR__ . '/../404.php';  // chemin à adapter
//     exit();
// });

// // Exemple pour gérer les erreurs fatales en fin de script
// register_shutdown_function(function () {
//     $error = error_get_last();
//     if ($error !== null) {
//         header('HTTP/1.1 404 Not Found');
//         include __DIR__ . '/../404.php';
//         exit();
//     }
// });



// Charger phpdotenv
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
use \Stripe\Stripe;


$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    exit('Erreur connexion BDD: ' . $e->getMessage());
}

date_default_timezone_set('Europe/Paris');

// Options Stripe & PayPal globales
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'];
$stripePublicKey = $_ENV['STRIPE_PUBLIC_KEY'];
$stripePublicKey = $_ENV['STRIPE_PUBLIC_KEY'];
$productID = $_ENV['PRODUCT_ID'];
$paypalClientId = $_ENV['PAYPAL_CLIENT_ID'];
$baseUrl = $_ENV['BASE_URL'];
$smtpHost = $_ENV['SMTP_HOST'] ?? '';
$smtpPort = intval($_ENV['SMTP_PORT'] ?? 465);
$smtpUser = $_ENV['SMTP_USER'] ?? '';
$smtpPass = $_ENV['SMTP_PASS'] ?? '';
$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@tonsite.com';
$limitPerPage = intval($_ENV['LIMIT_PER_PAGE'] ?? 10);


Stripe::setApiKey($stripeSecretKey);