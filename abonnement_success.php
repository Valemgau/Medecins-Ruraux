<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Stripe;
use \Stripe\Checkout\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($userRole !== 'recruteur') {
    die("Rôle utilisateur inconnu");
}

$sessionId = $_GET['session_id'] ?? null;

if (!$sessionId) {
    die("Session Stripe manquante");
}

// Récupère session Stripe Checkout
try {
    $session = Session::retrieve($sessionId);
} catch (Exception $e) {
    die("Erreur récupération session Stripe : " . $e->getMessage());
}

// Récupérer info abonnement et client dans Stripe
$subscriptionId = $session->subscription;
$customerId = $session->customer;
$paymentIntentId = $session->payment_intent;

// Récup infos utilisateur en bdd avec téléphone
$stmt = $pdo->prepare("SELECT u.email, r.prenom, r.nom, r.photo, r.telephone, r.telephone_indicatif, r.ville, r.pays, r.adresse, r.code_postal FROM users u JOIN recruteurs r ON u.id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable");
}

// Récup détails abonnement Stripe
$subscription = null;
try {
    $subscription = \Stripe\Subscription::retrieve($subscriptionId);
} catch (Exception $e) {
    // Ne pas bloquer, continuer sans détails
}

// Montant à facturer (en euros)
$amount = 0;
$subscriptionName = '';

if ($subscription) {
    if (!empty($subscription->items->data) && isset($subscription->items->data[0]->current_period_end)) {
        // Récupérer données
        $subscriptionId = $subscription->id;
        $status = $subscription->status ?? '';
        $periodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $expireDate = $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null;
        $subscriptionName = $subscription->items->data[0]->price->nickname ?? 'Abonnement mensuel';

        // Mettre à jour la base
        $stmt = $pdo->prepare("UPDATE users SET 
            stripe_subscription_id = ?, 
            subscription_status = ?, 
            subscription_current_period_end = ?, 
            subscription_name = ?
            WHERE id = ?"
        );
        $stmt->execute([
            $subscriptionId,
            $status,
            $expireDate,
            $subscriptionName,
            $userId
        ]);

        // Si formules "Essentielle", ajouter crédits CV
        if ($subscriptionName === 'Essentielle') {
            $res = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='cv_consultables_essentielle' LIMIT 1");
            $cvCredits = (int) $res->fetchColumn();

            $pdo->prepare("
                INSERT INTO user_cv_credits (user_id, cv_credits_remaining)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                cv_credits_remaining = VALUES(cv_credits_remaining),
                updated_at = NOW()
            ")->execute([$userId, $cvCredits]);
        }
    }
}

if ($subscription && isset($subscription->plan)) {
    $amount = ($subscription->plan->amount ?? 0) / 100;
} elseif (!empty($session->amount_total)) {
    $amount = $session->amount_total / 100;
}

// Générer PDF facture avec design professionnel
function generateInvoicePdf($toName, $paymentAmount, $userDetails, $subscriptionName, $subscriptionId)
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #2d3748; margin: 0; padding: 0; }
            .container { max-width: 800px; margin: 0 auto; padding: 40px; }
            .header { display: table; width: 100%; margin-bottom: 40px; }
            .header-left { display: table-cell; width: 50%; vertical-align: top; }
            .header-right { display: table-cell; width: 50%; text-align: right; vertical-align: top; }
            .logo { max-height: 80px; margin-bottom: 15px; }
            .company-name { font-size: 24px; font-weight: 900; color: #10b981; margin-bottom: 5px; }
            .company-details { font-size: 12px; line-height: 1.6; color: #4a5568; }
            .invoice-title { text-align: center; font-size: 32px; font-weight: 900; color: #1a202c; margin: 30px 0; }
            .invoice-info { background: #f7fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
            .info-row { display: table; width: 100%; margin-bottom: 8px; }
            .info-label { display: table-cell; font-weight: 600; color: #4a5568; width: 40%; }
            .info-value { display: table-cell; color: #1a202c; }
            .section-title { font-size: 18px; font-weight: 700; color: #1a202c; margin-top: 30px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #10b981; }
            .client-box { background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981; }
            .client-title { font-weight: 700; font-size: 14px; color: #2d3748; margin-bottom: 10px; }
            .client-info { font-size: 12px; line-height: 1.8; color: #4a5568; }
            .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            .table th { background: #10b981; color: white; padding: 12px; text-align: left; font-weight: 600; }
            .table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .table tbody tr:last-child td { border-bottom: none; }
            .table .total-row { background: #f7fafc; font-weight: 700; font-size: 16px; }
            .amount-highlight { color: #10b981; font-size: 18px; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; font-size: 11px; color: #718096; }
            .thank-you { margin-top: 30px; padding: 15px; background: #ecfdf5; border-radius: 8px; color: #065f46; font-weight: 600; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- En-tête -->
            <div class="header">
                <div class="header-left">
                    <img src="https://medecinsruraux.com/assets/img/logo.jpg" alt="Médecins Ruraux" class="logo" />
                    <div class="company-name">Médecins Ruraux</div>
                    <div class="company-details">
                        135 rue des Peupliers<br>
                        01100 Martignat<br>
                        France<br>
                        <strong>Tél :</strong> +33 6 89 88 32 33<br>
                        <strong>Email :</strong> contact@medecinsruraux.com
                    </div>
                </div>
                <div class="header-right">
                    <div style="font-size: 11px; color: #718096; margin-bottom: 5px;">FACTURE N°</div>
                    <div style="font-size: 20px; font-weight: 700; color: #1a202c;"><?= date('Ymd') ?>-<?= substr($subscriptionId, -8) ?></div>
                    <div style="font-size: 11px; color: #718096; margin-top: 10px;">Date : <?= date('d/m/Y') ?></div>
                </div>
            </div>

            <!-- Titre -->
            <div class="invoice-title">FACTURE D'ABONNEMENT</div>

            <!-- Informations client -->
            <div class="section-title">Client</div>
            <div class="client-box">
                <div class="client-info">
                    <strong style="font-size: 14px; color: #1a202c;"><?= htmlspecialchars($toName) ?></strong><br>
                    <?php if (!empty($userDetails['email'])): ?>
                        <strong>Email :</strong> <?= htmlspecialchars($userDetails['email']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($userDetails['telephone_complet'])): ?>
                        <strong>Téléphone :</strong> <?= htmlspecialchars($userDetails['telephone_complet']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($userDetails['adresse'])): ?>
                        <?= nl2br(htmlspecialchars($userDetails['adresse'])) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($userDetails['code_postal']) || !empty($userDetails['ville'])): ?>
                        <?= htmlspecialchars($userDetails['code_postal'] . ' ' . $userDetails['ville']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($userDetails['pays'])): ?>
                        <?= htmlspecialchars($userDetails['pays']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Détails de l'abonnement -->
            <div class="section-title">Détails de la prestation</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Description</th>
                        <th style="width: 30%; text-align: right;">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($subscriptionName) ?></strong><br>
                            <span style="font-size: 11px; color: #718096;">Abonnement mensuel - Médecins Ruraux</span>
                        </td>
                        <td style="text-align: right; font-weight: 600;"><?= number_format($paymentAmount, 2, ',', ' ') ?> €</td>
                    </tr>
                    <tr class="total-row">
                        <td style="text-align: right;">Total TTC</td>
                        <td style="text-align: right;" class="amount-highlight"><?= number_format($paymentAmount, 2, ',', ' ') ?> €</td>
                    </tr>
                </tbody>
            </table>

            <!-- Message de remerciement -->
            <div class="thank-you">
                ✓ Merci pour votre confiance et votre abonnement
            </div>

            <!-- Pied de page -->
            <div class="footer">
                Cette facture est générée automatiquement et ne nécessite pas de signature.<br>
                Médecins Ruraux - 135 rue des Peupliers, 01100 Martignat, France<br>
                Pour toute question : contact@medecinsruraux.com | +33 6 89 88 32 33
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

// Envoi mail professionnel avec facture PDF
function sendInvoiceEmail($toEmail, $toName, $subscriptionName, $amount, $pdfOutput, $adminEmail)
{
    global $smtpHost, $smtpPort, $smtpUser, $smtpPass;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = ($smtpPort == 465) ? 'ssl' : 'tls';

        $mail->setFrom($smtpUser, 'Médecins Ruraux');
        $mail->addAddress($toEmail, $toName);
        $mail->addBCC($adminEmail);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Confirmation d'abonnement - Médecins Ruraux";

        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #2d3748; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; }
                .logo { max-height: 60px; margin-bottom: 15px; }
                .header-title { color: white; font-size: 28px; font-weight: 700; margin: 0; }
                .content { padding: 40px 30px; }
                .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
                .message { font-size: 14px; color: #4a5568; margin-bottom: 15px; line-height: 1.8; }
                .info-box { background: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; margin: 25px 0; border-radius: 4px; }
                .info-title { font-weight: 700; color: #065f46; margin-bottom: 10px; }
                .info-detail { color: #047857; font-size: 14px; margin: 8px 0; }
                .cta-button { display: inline-block; background: #10b981; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                .cta-button:hover { background: #059669; }
                .footer { background: #f7fafc; padding: 30px; text-align: center; font-size: 12px; color: #718096; }
                .divider { height: 1px; background: #e2e8f0; margin: 25px 0; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <!-- En-tête -->
                <div class="header">
                    <img src="https://medecinsruraux.com/assets/img/logo.jpg" alt="Médecins Ruraux" class="logo" style="max-height: 60px;" />
                    <h1 class="header-title">Abonnement activé !</h1>
                </div>

                <!-- Contenu principal -->
                <div class="content">
                    <div class="greeting">Bonjour ' . htmlspecialchars($toName) . ',</div>
                    
                    <p class="message">
                        Nous sommes ravis de vous compter parmi nos membres. Votre abonnement <strong>' . htmlspecialchars($subscriptionName) . '</strong> a été activé avec succès.
                    </p>

                    <!-- Encadré informations -->
                    <div class="info-box">
                        <div class="info-title">✓ Détails de votre abonnement</div>
                        <div class="info-detail"><strong>Formule :</strong> ' . htmlspecialchars($subscriptionName) . '</div>
                        <div class="info-detail"><strong>Montant :</strong> ' . number_format($amount, 2, ',', ' ') . ' € / mois</div>
                        <div class="info-detail"><strong>Date d\'activation :</strong> ' . date('d/m/Y') . '</div>
                    </div>

                    <!-- Informations client -->
                    <div style="background: #f7fafc; padding: 15px; border-radius: 6px; margin: 20px 0; font-size: 13px; color: #4a5568;">
                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 8px;">📋 Vos informations</div>
                        <div style="line-height: 1.8;">
                            <strong>Email :</strong> ' . htmlspecialchars($toEmail) . '<br>
                            ' . (!empty($userDetails['telephone_complet']) ? '<strong>Téléphone :</strong> ' . htmlspecialchars($userDetails['telephone_complet']) . '<br>' : '') . '
                            ' . (!empty($userDetails['adresse']) ? '<strong>Adresse :</strong> ' . nl2br(htmlspecialchars($userDetails['adresse'])) . '<br>' : '') . '
                            ' . (!empty($userDetails['code_postal']) || !empty($userDetails['ville']) ? htmlspecialchars($userDetails['code_postal'] . ' ' . $userDetails['ville']) . '<br>' : '') . '
                            ' . (!empty($userDetails['pays']) ? htmlspecialchars($userDetails['pays']) : '') . '
                        </div>
                    </div>

                    <p class="message">
                        Vous trouverez votre facture en pièce jointe à cet email. Vous pouvez désormais profiter pleinement de tous les avantages de votre abonnement.
                    </p>

                    <div style="text-align: center;">
                        <a href="https://medecinsruraux.com/dashboard-recruteur.php" class="cta-button" style="color: white;">
                            Accéder à mon tableau de bord
                        </a>
                    </div>

                    <div class="divider"></div>

                    <p class="message">
                        Si vous avez des questions ou besoin d\'assistance, notre équipe est à votre disposition.
                    </p>

                    <p class="message" style="margin-top: 30px; font-weight: 600; color: #1a202c;">
                        Cordialement,<br>
                        L\'équipe Médecins Ruraux
                    </p>
                </div>

                <!-- Pied de page -->
                <div class="footer">
                    <div style="margin-bottom: 10px;">
                        <strong>Médecins Ruraux</strong><br>
                        135 rue des Peupliers, 01100 Martignat, France
                    </div>
                    <div>
                        📧 <a href="mailto:' . htmlspecialchars($adminEmail) . '" style="color: #10b981; text-decoration: none;">' . htmlspecialchars($adminEmail) . '</a><br>
                        📞 +33 6 89 88 32 33
                    </div>
                    <div style="margin-top: 15px; font-size: 11px; color: #a0aec0;">
                        Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->AltBody = "Bonjour " . $toName . ",\n\nVotre abonnement " . $subscriptionName . " a été activé avec succès.\nMontant : " . number_format($amount, 2, ',', ' ') . " €\n\nVotre facture est jointe à cet email.\n\nCordialement,\nL'équipe Médecins Ruraux";

        $mail->addStringAttachment($pdfOutput, 'facture_' . date('Ymd_His') . '.pdf', 'base64', 'application/pdf');
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail facture abonnement: " . $mail->ErrorInfo);
        return false;
    }
}

// Préparer infos utilisateur pour la facture
$telephoneComplet = '';
if (!empty($user['telephone_indicatif']) && !empty($user['telephone'])) {
    $telephoneComplet = $user['telephone_indicatif'] . ' ' . $user['telephone'];
} elseif (!empty($user['telephone'])) {
    $telephoneComplet = $user['telephone'];
}

$userDetails = [
    'prenom' => $user['prenom'] ?? '',
    'nom' => $user['nom'] ?? '',
    'email' => $user['email'] ?? '',
    'telephone_complet' => $telephoneComplet,
    'adresse' => $user['adresse'] ?? '',
    'ville' => $user['ville'] ?? '',
    'code_postal' => $user['code_postal'] ?? '',
    'pays' => $user['pays'] ?? '',
];

// Générer PDF
$pdfOutput = generateInvoicePdf(
    trim($userDetails['prenom'] . ' ' . $userDetails['nom']),
    $amount,
    $userDetails,
    $subscriptionName,
    $subscriptionId
);

// Envoyer mail
$sent = sendInvoiceEmail(
    $user['email'], 
    trim($userDetails['prenom'] . ' ' . $userDetails['nom']), 
    $subscriptionName,
    $amount,
    $pdfOutput, 
    $adminEmail
);

if (!$sent) {
    error_log("Erreur envoi mail facture à {$user['email']}");
}

$title = "Abonnement confirmé";
ob_start();
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-50 via-white to-green-50 py-12 px-4">
    <div class="max-w-2xl w-full">
        <!-- Carte principale -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- En-tête avec dégradé -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-8 py-12 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-6 shadow-lg">
                    <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white mb-3">Abonnement activé !</h1>
                <p class="text-green-50 text-lg">Bienvenue dans la communauté Médecins Ruraux</p>
            </div>

            <!-- Contenu -->
            <div class="px-8 py-10">
                <!-- Message de confirmation -->
                <div class="text-center mb-8">
                    <p class="text-gray-700 text-lg leading-relaxed">
                        Merci pour votre confiance ! Votre abonnement <strong class="text-green-600"><?= htmlspecialchars($subscriptionName) ?></strong> est désormais actif.
                    </p>
                </div>

                <!-- Détails de l'abonnement -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500 rounded-lg p-6 mb-8">
                    <h2 class="font-bold text-green-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Détails de votre abonnement
                    </h2>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Formule</span>
                            <span class="font-semibold text-gray-900"><?= htmlspecialchars($subscriptionName) ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Montant</span>
                            <span class="font-semibold text-green-600 text-lg"><?= number_format($amount, 2, ',', ' ') ?> € / mois</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Date d'activation</span>
                            <span class="font-semibold text-gray-900"><?= date('d/m/Y') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Informations importantes -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-blue-500 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-blue-900 mb-1">Facture envoyée</h3>
                            <p class="text-blue-700 text-sm">
                                Votre facture a été envoyée à l'adresse <strong><?= htmlspecialchars($user['email']) ?></strong>. 
                                Pensez à vérifier vos spams si vous ne la recevez pas.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="/dashboard-recruteur.php"
                        class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold px-6 py-4 rounded-lg shadow-md hover:shadow-lg transition-all text-center flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Accéder au tableau de bord
                    </a>
                    
                    <a href="/index.php"
                        class="flex-1 bg-white hover:bg-gray-50 text-gray-700 font-semibold px-6 py-4 rounded-lg border-2 border-gray-300 hover:border-gray-400 transition-all text-center flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Consulter les candidats
                    </a>
                </div>

                <!-- Support -->
                <div class="mt-8 text-center text-gray-600 text-sm">
                    <p>Une question ? Contactez-nous :</p>
                    <div class="mt-2 flex items-center justify-center space-x-6">
                        <a href="mailto:<?= htmlspecialchars($adminEmail) ?>" class="text-green-600 hover:text-green-700 font-medium flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <?= htmlspecialchars($adminEmail) ?>
                        </a>
                        <span class="text-gray-400">|</span>
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            +33 6 89 88 32 33
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message de confiance -->
        <div class="mt-6 text-center">
            <p class="text-gray-500 text-sm">
                🔒 Paiement sécurisé | ✓ Abonnement sans engagement | 📧 Facture automatique
            </p>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>