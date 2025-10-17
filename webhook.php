<?php
// Stripe dépendance
require_once './includes/config.php';


// Récupère le corps brut de la requête
$payload = @file_get_contents('php://input');
// Récupère la signature du header
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$endpoint_secret = 'whsec_ByV35CUsm4TImr1I417vVsUs9WAgCuFc'; // Remplace par le secret du webhook

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400); // Invalid payload
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); // Invalid signature
    exit();
}

// Gestion du type d'événement
if ($event->type == 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    // LOGIQUE METIER : mettre à jour ta BDD, envoyer email, etc.
    // Exemple simple :
    $amount = $paymentIntent->amount_received / 100;
    $email = $paymentIntent->charges->data[0]->billing_details->email ?? null;
    // TODO: Traitement métier selon le paiement
}

// Réponse Stripe : 200 OK
http_response_code(200);
echo 'Webhook reçu.';
