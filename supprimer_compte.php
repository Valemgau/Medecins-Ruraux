<?php
require_once './includes/config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? null;

$email = null;
$prenom = '';
$nom = '';

// Récupérer l'email depuis users
$stmtEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtEmail->execute([$userId]);
$userData = $stmtEmail->fetch();

if (!$userData) {
    die("Utilisateur non trouvé");
}

$email = $userData['email'];

// Selon user_role, récupérer le prénom et nom dans la table associée
if ($userRole === 'recruteur') {
    $stmtName = $pdo->prepare("SELECT prenom, nom FROM recruteurs WHERE id = ?");
    $stmtName->execute([$userId]);
    $nameData = $stmtName->fetch();
} elseif ($userRole === 'candidat') {
    $stmtName = $pdo->prepare("SELECT prenom, nom FROM candidats WHERE id = ?");
    $stmtName->execute([$userId]);
    $nameData = $stmtName->fetch();
} else {
    die("Rôle utilisateur inconnu");
}

if ($nameData) {
    $prenom = $nameData['prenom'] ?? '';
    $nom = $nameData['nom'] ?? '';
}

$alert = null;

function sendDeletionEmail($toEmail, $toName, $adminEmail, $baseUrl)
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

        $mail->setFrom($smtpUser, parse_url($baseUrl, PHP_URL_HOST));
        $mail->addAddress($toEmail, $toName);
        $mail->addBCC($adminEmail);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Confirmation de suppression de votre compte";

        $mail->Body = '
        <div style="font-family: Arial, sans-serif; color: #222;">
            <p>Bonjour ' . htmlspecialchars($toName) . ',</p>
            <p>Votre compte a bien été supprimé de notre plateforme. Nous sommes désolés de vous voir partir.</p>
            <p>Pour toute question, n\'hésitez pas à nous contacter à <a href="mailto:' . htmlspecialchars($adminEmail) . '" style="color:#f97316; text-decoration:none;">' . htmlspecialchars($adminEmail) . '</a>.</p>
            <p>Cordialement,<br/><strong>L\'équipe ' . htmlspecialchars(parse_url($baseUrl, PHP_URL_HOST)) . '</strong></p>
        </div>
        ';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail suppression: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    try {
        $pdo->beginTransaction();

        if ($userRole === 'recruteur') {
            $stmtDeleteRecruteur = $pdo->prepare("DELETE FROM recruteurs WHERE id = ?");
            $stmtDeleteRecruteur->execute([$userId]);
        } elseif ($userRole === 'candidat') {
            $stmtDeleteCandidat = $pdo->prepare("DELETE FROM candidats WHERE id = ?");
            $stmtDeleteCandidat->execute([$userId]);
        }

        $stmtDeleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDeleteUser->execute([$userId]);

        $pdo->commit();

        sendDeletionEmail($email, trim($prenom.' '.$nom), $adminEmail, $baseUrl);

        session_unset();
        session_destroy();

        header('Location: goodbye.php?account_deleted=1');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $alert = ['type' => 'error', 'text' => "Erreur lors de la suppression du compte : " . $e->getMessage()];
    }
}

$title = "Suppression de compte";
ob_start();
?>

<div class="md:p-6 max-w-7xl mx-auto">
    <?php if ($alert): ?>
        <div class="<?= $alert['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> p-4 rounded mb-6 text-center">
            <?= htmlspecialchars($alert['text']) ?>
        </div>
    <?php endif; ?>

    <h1 class="text-3xl font-bold mb-6">Suppression de mon compte</h1>
    <p class="mb-4">Attention, cette action est irréversible. En supprimant votre compte, toutes vos données seront perdues.</p>

    <form method="post" onsubmit="return confirm('Confirmez-vous la suppression définitive de votre compte ?');">
        <button type="submit" name="delete_account" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded">
            Supprimer mon compte
        </button>
    </form>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>
