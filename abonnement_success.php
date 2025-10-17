<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Stripe;
use \Stripe\Checkout\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

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
$paymentIntentId = $session->payment_intent; // parfois utile

// Récup infos utilisateur en bdd
$stmt = $pdo->prepare("SELECT u.email, r.prenom, r.nom, r.photo FROM users u JOIN recruteurs r ON u.id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable");
}

// Récup détails abonnement Stripe (optionnel, utile pour montant exact, dates)
$subscription = null;
try {
    $subscription = \Stripe\Subscription::retrieve($subscriptionId);
} catch (Exception $e) {
    // Ne pas bloquer, continuer sans détails
}

// Montant à facturer (en euros)
$amount = 0;

if ($subscription) {
    if (!empty($subscription->items->data) && isset($subscription->items->data[0]->current_period_end)) {
        // Récupérer données
        $subscriptionId = $subscription->id;
        $status = $subscription->status ?? '';
        $periodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $expireDate = $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null;
        $subscriptionName = $subscription->items->data[0]->price->nickname ?? '';

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
            // Récupérer la valeur dans admin_settings
            $res = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='cv_consultables_essentielle' LIMIT 1");
            $cvCredits = (int) $res->fetchColumn();

            // Insérer ou mettre à jour crédits
            $pdo->prepare("
                INSERT INTO user_cv_credits (user_id, cv_credits_remaining)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                cv_credits_remaining = VALUES(cv_credits_remaining),
                updated_at = NOW()
            ")->execute([$userId, $cvCredits]);
        }
    } else {
        echo "Problème : items ou current_period_end non définis";
    }
}

 else {
    echo "Problème : subscription vide ou nulle";
}









if ($subscription && isset($subscription->plan)) {
    // montant en centimes Stripe
    $amount = ($subscription->plan->amount ?? 0) / 100;
} elseif (!empty($session->amount_total)) {
    $amount = $session->amount_total / 100;
}

// Générer PDF facture
function generateInvoicePdf($toName, $reference, $paymentAmount, $userDetails, $baseUrl)
{
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; max-width:650px; margin:auto; padding:30px; color:#222;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
            <!-- Propriétaire (gauche) -->
            <div style="flex:1; min-width:220px;">
                <img src="<?= rtrim($baseUrl, '/') ?>/assets/img/logo.jpg" alt="Logo Médecins Ruraux"
                    style="height:70px; margin-bottom:15px;" />
                <div style="font-weight:900; font-size:20px; margin-bottom:4px;">Sur4plots</div>
                135 rue des Peupliers<br>
                01100 Martignat<br>
                France<br>
                <strong>Tél :</strong> +33 6 89 88 32 33
            </div>
            <!-- Client (droite) -->
            <div style="flex:1; min-width:220px; text-align:right;">
                <div style="font-weight:700; margin-bottom:2px;">Client :</div>
                <strong>Nom :</strong> <?= htmlspecialchars($toName) ?><br>
                <strong>Email :</strong> <?= htmlspecialchars($userDetails['email'] ?? '') ?><br>
                <?php if (!empty($userDetails['prenom'])): ?><strong>Prénom :</strong>
                    <?= htmlspecialchars($userDetails['prenom']) ?><br><?php endif; ?>
                <?php if (!empty($userDetails['adresse'])): ?><strong>Adresse :</strong>
                    <?= nl2br(htmlspecialchars($userDetails['adresse'])) ?><br><?php endif; ?>
                <?php if (!empty($userDetails['ville'])): ?><strong>Ville :</strong>
                    <?= htmlspecialchars($userDetails['ville']) ?><br><?php endif; ?>
                <?php if (!empty($userDetails['code_postal'])): ?><strong>Code postal :</strong>
                    <?= htmlspecialchars($userDetails['code_postal']) ?><br><?php endif; ?>
                <?php if (!empty($userDetails['pays'])): ?><strong>Pays :</strong>
                    <?= htmlspecialchars($userDetails['pays']) ?><br><?php endif; ?>
                <?php if (!empty($userDetails['telephone'])): ?><strong>Téléphone :</strong>
                    <?= htmlspecialchars($userDetails['telephone']) ?><br><?php endif; ?>
            </div>
        </div>

        <div style="text-align:center;">
            <h1 style="font-weight:900; font-size:30px; margin-bottom:10px; color:#222; margin-top:10px;">
                Facture d'abonnement
            </h1>
        </div>

        <div style="margin-top:20px;">
            <table style="width:100%;">
                <tr>
                    <td><strong>Date :</strong></td>
                    <td><?= date('d/m/Y') ?></td>
                </tr>
                <tr>
                    <td><strong>Référence abonnement :</strong></td>
                    <td><?= htmlspecialchars($reference) ?></td>
                </tr>
            </table>
        </div>

        <h2
            style="font-weight:700; margin-top:35px; margin-bottom:12px; border-bottom:1px solid #ddd; padding-bottom:6px; color:#111;">
            Détails de l'abonnement
        </h2>
        <table style="width:100%; border-collapse:collapse; margin-top:10px; font-weight:600;" border="1" cellpadding="10"
            cellspacing="0">
            <thead style="background-color:#f3f3f3; color:#222; font-weight:700;">
                <tr>
                    <th style="text-align: left;">Description</th>
                    <th style="text-align: right;">Montant (€)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Abonnement mensuel <?= htmlspecialchars($baseUrl) ?></td>
                    <td style="text-align: right; font-weight:700;"><?= number_format($paymentAmount, 2, ',', ' ') ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th style="text-align: right; font-weight:900;">Total</th>
                    <th style="text-align: right; font-weight:900; color: #e1651b;">
                        <?= number_format($paymentAmount, 2, ',', ' ') ?> €
                    </th>
                </tr>
            </tfoot>
        </table>

        <p style="margin-top: 30px; font-weight: 600;">Merci pour votre confiance et votre abonnement.</p>
    </div>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}


// Envoi mail avec facture PDF en pièce jointe
function sendInvoiceEmail($toEmail, $toName, $subject, $pdfOutput, $adminEmail, $baseUrl)
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
        $mail->Subject = $subject;

        $mail->Body = '
        <div style="font-family: Arial, sans-serif; color: #222;">
            <p>Bonjour ' . htmlspecialchars($toName) . ',</p>
            <p>Merci pour votre abonnement. Vous trouverez votre facture en pièce jointe à cet email.</p>
            <p>Pour toute question, n\'hésitez pas à nous contacter à <a href="mailto:' . htmlspecialchars($adminEmail) . '" style="color:#f97316; text-decoration:none;">' . htmlspecialchars($adminEmail) . '</a>.</p>
            <p>Cordialement,<br/><strong>L\'équipe Médecins Ruraux</strong></p>
        </div>
        ';

        $mail->addStringAttachment($pdfOutput, 'facture_abonnement_' . date('Ymd_His') . '.pdf', 'base64', 'application/pdf');
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail facture abonnement: " . $mail->ErrorInfo);
        return false;
    }
}

// Préparer infos utilisateur pour la facture
$userDetails = [
    'prenom' => $user['prenom'] ?? '',
    'nom' => $user['nom'] ?? '',
    'email' => $user['email'] ?? '',
    'telephone' => '', // à compléter si tu as ce champ en base
    'adresse' => '',
    'ville' => '',
    'code_postal' => '',
    'pays' => '',
];

// Générer PDF
$pdfOutput = generateInvoicePdf(
    trim($userDetails['prenom'] . ' ' . $userDetails['nom']),
    $subscriptionId,
    $amount,
    $userDetails,
    $baseUrl
);

// Envoyer mail
$subject = "Votre facture d'abonnement Médecins Ruraux";

$sent = sendInvoiceEmail($user['email'], trim($userDetails['prenom'] . ' ' . $userDetails['nom']), $subject, $pdfOutput, $adminEmail, $baseUrl);

if (!$sent) {
    error_log("Erreur envoi mail facture à {$user['email']}");
}

$title = "Abonnement effectuée";
ob_start();
?>
<div class="bg-green-100 text-green-700 p-6 rounded my-10 text-center">
    <h1 class="text-3xl font-bold mb-4">Abonnement activé avec succès !</h1>
    <p>Merci pour votre confiance, votre abonnement est désormais actif.</p>
    <p>Une facture vous a été envoyée par e-mail.</p>
    <a href="/dashboard-recruteur.php"
        class="mt-6 inline-block bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded">Retour au tableau de
        bord</a>
</div>
<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>