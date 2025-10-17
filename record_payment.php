<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$recruteurId = $input['recruteur_id'] ?? null;
$candidatId = $input['candidat_id'] ?? null;
$montant = $input['montant'] ?? null;
$methode = $input['methode'] ?? null;
$transactionId = $input['transaction_id'] ?? null;

if (!$recruteurId || !$candidatId || !$montant || !$methode || !$transactionId) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

// Génération PDF facture
function generateInvoicePdf($toName, $reference, $paymentAmount, $userDetails, $baseUrl)
{
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; max-width:650px; margin:auto; padding:30px; color:#222;">
        <div style="text-align:center; margin-bottom:30px;">
            <img src="<?= $baseUrl ?>/assets/img/logo.jpg" alt="Logo Médecins Ruraux" style="height:80px;" />
            <h1 style="margin-top: 15px; font-weight: 900; font-size: 32px; color: #222;">Facture de paiement</h1>
        </div>
        <p><strong>Date :</strong> <?= date('d/m/Y') ?></p>
        <p><strong>Référence paiement :</strong> <?= htmlspecialchars($reference) ?></p>

        <h2 style="font-weight: 700; margin-top: 40px; padding-bottom: 5px; color:#111;">
        </h2>
        <p>Mme VIGNY-VELON Valerie<br />
            135 rue des Peupliers<br />
            01100 Martignat
        </p>

        <h2
            style="font-weight: 700; margin-top: 30px; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; color:#111;">
            Facturé à :</h2>
        <p style="line-height: 1.5;">
            <strong>Nom :</strong> <?= htmlspecialchars($toName) ?><br />
            <strong>Email :</strong> <?= htmlspecialchars($userDetails['email'] ?? '') ?><br />
            <?php if (!empty($userDetails['prenom'])): ?><strong>Prénom :</strong>
                <?= htmlspecialchars($userDetails['prenom']) ?><br /><?php endif; ?>
            <?php if (!empty($userDetails['adresse'])): ?><strong>Adresse :</strong>
                <?= nl2br(htmlspecialchars($userDetails['adresse'])) ?><br /><?php endif; ?>
            <?php if (!empty($userDetails['ville'])): ?><strong>Ville :</strong>
                <?= htmlspecialchars($userDetails['ville']) ?><br /><?php endif; ?>
            <?php if (!empty($userDetails['code_postal'])): ?><strong>Code postal :</strong>
                <?= htmlspecialchars($userDetails['code_postal']) ?><br /><?php endif; ?>
            <?php if (!empty($userDetails['pays'])): ?><strong>Pays :</strong>
                <?= htmlspecialchars($userDetails['pays']) ?><br /><?php endif; ?>
            <?php if (!empty($userDetails['telephone'])): ?><strong>Téléphone :</strong>
                <?= htmlspecialchars($userDetails['telephone']) ?><br /><?php endif; ?>
        </p>

        <h2
            style="font-weight: 700; margin-top: 30px; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; color:#111;">
            Détails du paiement :</h2>
        <table style="width:100%; border-collapse: collapse; margin-top: 10px; font-weight: 600;" border="1"
            cellpadding="10" cellspacing="0">
            <thead style="background-color:#f3f3f3; color:#222; font-weight: 700;">
                <tr>
                    <th style="text-align: left;">Description</th>
                    <th style="text-align: right;">Montant (€)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Accès documents candidat #<?= htmlspecialchars($reference) ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= number_format($paymentAmount, 2, ',', ' ') ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th style="text-align: right; font-weight: 900;">Total</th>
                    <th style="text-align: right; font-weight: 900; color: #e1651b;">
                        <?= number_format($paymentAmount, 2, ',', ' ') ?> €
                    </th>
                </tr>
            </tfoot>
        </table>

        <p style="margin-top: 30px; font-weight: 600;">Merci pour votre confiance.</p>
    </div>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

// Fonction envoi mail


function sendAdminPaymentNotification($adminEmail, $toName, $montant, $reference, $transactionId, $userDetails)
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
        $mail->addAddress($adminEmail);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Nouveau paiement reçu";

        $mail->Body = '
        <div style="font-family: Arial, sans-serif; color: #222;">
          <p><strong>Nouveau paiement reçu</strong></p>
          <ul>
            <li><strong>Client :</strong> ' . htmlspecialchars($toName) . '</li>
            <li><strong>Email :</strong> ' . htmlspecialchars($userDetails['email']) . '</li>
            <li><strong>Montant :</strong> ' . number_format($montant, 2, '.', '') . ' €</li>
            <li><strong>Référence :</strong> ' . htmlspecialchars($reference) . '</li>
            <li><strong>Transaction :</strong> ' . htmlspecialchars($transactionId) . '</li>
          </ul>
          <p>Consulte ton dashboard pour le détail.</p>
        </div>
        ';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur email admin paiement: " . $mail->ErrorInfo);
        return false;
    }
}



function sendInvoiceEmail($toEmail, $toName, $subject, $pdfOutput, $adminEmail, $baseUrl)
{
    global $smtpHost, $smtpPort, $smtpUser, $smtpPass, $adminEmail;

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

        // Message simple et pro dans le mail
        $mail->Body = '
  <div style="font-family: Arial, sans-serif; color: #222;">
    <p>Bonjour ' . htmlspecialchars($toName) . ',</p>
    <p>Nous vous remercions pour votre paiement.</p>
    <p>Vous trouverez votre facture en pièce jointe à cet email.</p>
    <p>Pour toute question, n\'hésitez pas à nous contacter à <a href="mailto:' . htmlspecialchars($adminEmail) . '" style="color:#f97316; text-decoration:none;">' . htmlspecialchars($smtpUser) . '</a>.</p>
    <p>Cordialement,<br/><strong>L\'équipe Médecins Ruraux</strong></p>
  </div>
';


        $mail->addStringAttachment($pdfOutput, 'facture_' . date('Ymd_His') . '.pdf', 'base64', 'application/pdf');

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail facture: " . $mail->ErrorInfo);
        return false;
    }
}


// Enregistrement paiement en base
$stmt = $pdo->prepare("INSERT INTO payments 
    (recruteur_id, candidat_id, montant, methode, statut, date_paiement, reference_transaction) 
    VALUES (?, ?, ?, ?, 'completed', NOW(), ?) 
    ON DUPLICATE KEY UPDATE statut='completed', date_paiement=NOW(), reference_transaction=VALUES(reference_transaction)");

$success = $stmt->execute([$recruteurId, $candidatId, $montant, $methode, $transactionId]);

if (!$success) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    exit;
}

// Récup infos utilisateur pour mail
$userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$userStmt->execute([$recruteurId]);
$user = $userStmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Recruteur introuvable']);
    exit;
}

$infoStmt = $pdo->prepare("SELECT prenom, nom, telephone, adresse, ville, code_postal, pays FROM recruteurs WHERE id = ?");
$infoStmt->execute([$recruteurId]);
$userInfo = $infoStmt->fetch();

if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'Infos du recruteur manquantes']);
    exit;
}

$toEmail = $user['email'];
$toName = trim(($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? ''));

$userDetails = [
    'prenom' => $userInfo['prenom'] ?? '',
    'nom' => $userInfo['nom'] ?? '',
    'telephone' => $userInfo['telephone'] ?? '',
    'adresse' => $userInfo['adresse'] ?? '',
    'ville' => $userInfo['ville'] ?? '',
    'code_postal' => $userInfo['code_postal'] ?? '',
    'pays' => $userInfo['pays'] ?? '',
    'email' => $toEmail,
];

// Récup référence candidat
$refStmt = $pdo->prepare("SELECT numero_reference FROM candidats WHERE id = ?");
$refStmt->execute([$candidatId]);
$candidat = $refStmt->fetch();
$reference = $candidat ? $candidat['numero_reference'] : '';

// Générer le PDF facture
$pdfOutput = generateInvoicePdf($toName, $reference, $montant, $userDetails, $baseUrl);

$subject = "Facture paiement - " . $reference;

// Envoi mail avec PDF attaché
global $adminEmail;
sendAdminPaymentNotification($adminEmail, $toName, $montant, $reference, $transactionId, $userDetails);
$sendMailSuccess = sendInvoiceEmail($toEmail, $toName, $subject, $pdfOutput, $adminEmail, $baseUrl);

if (!$sendMailSuccess) {
    error_log('Erreur envoi mail facture à ' . $toEmail);
}

echo json_encode(['success' => true]);
exit;
