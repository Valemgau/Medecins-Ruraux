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
$userRole = $_SESSION['role'] ?? null;

$email = null;
$prenom = '';
$nom = '';

$stmtEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtEmail->execute([$userId]);
$userData = $stmtEmail->fetch();

if (!$userData) {
    die("Utilisateur non trouvé");
}

$email = $userData['email'];

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

        $mail->setFrom($smtpUser, 'Médecins Ruraux');
        $mail->addAddress($toEmail, $toName);
        $mail->addBCC($adminEmail);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Confirmation de suppression de votre compte";

        $mail->Body = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: "Segoe UI", Arial, sans-serif; }
        .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 16px; padding: 40px; box-shadow: 0 4px 18px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; font-size: 28px; margin-bottom: 20px; }
        p { color: #6b7280; line-height: 1.6; margin-bottom: 15px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid #f3f4f6; font-size: 12px; color: #9ca3af; }
        a { color: #22c55e; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Compte supprimé</h1>
        <p>Bonjour ' . htmlspecialchars($toName) . ',</p>
        <p>Votre compte a été supprimé définitivement de notre plateforme. Nous sommes désolés de vous voir partir.</p>
        <p>Si vous changez d\'avis, vous pouvez créer un nouveau compte à tout moment.</p>
        <p>Pour toute question, contactez-nous à <a href="mailto:' . htmlspecialchars($adminEmail) . '">' . htmlspecialchars($adminEmail) . '</a>.</p>
        <div class="footer">
            <p>Cordialement,<br><strong>L\'équipe Médecins Ruraux</strong></p>
        </div>
    </div>
</body>
</html>';

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

        sendDeletionEmail($email, trim($prenom . ' ' . $nom), $adminEmail, $baseUrl);

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

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        
        <?php if ($alert): ?>
            <div class="mb-6">
                <div class="<?= $alert['type'] === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-800' : 'bg-red-50 border-l-4 border-red-500 text-red-800' ?> p-4 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <i class="fas <?= $alert['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <span><?= htmlspecialchars($alert['text']) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Carte principale -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            
            <!-- Header avec icône de danger -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 p-8 text-white">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-exclamation-triangle text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">Suppression de compte</h1>
                        <p class="text-red-100 mt-1">Action irréversible</p>
                    </div>
                </div>
            </div>

            <!-- Contenu -->
            <div class="p-8">
                
                <!-- Informations utilisateur -->
                <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                        <i class="fas fa-user text-green-600"></i>
                        Informations du compte
                    </h2>
                    <div class="space-y-2 text-gray-700">
                        <p><strong>Nom complet :</strong> <?= htmlspecialchars($prenom . ' ' . $nom) ?></p>
                        <p><strong>Email :</strong> <?= htmlspecialchars($email) ?></p>
                        <p><strong>Rôle :</strong> <?= htmlspecialchars(ucfirst($userRole)) ?></p>
                    </div>
                </div>

                <!-- Message d'avertissement -->
                <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-lg mb-8">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-red-900 mb-3">⚠️ Attention : Action irréversible</h3>
                            <p class="text-red-800 mb-3">En supprimant votre compte, vous perdrez <strong>définitivement</strong> :</p>
                            <ul class="list-disc list-inside space-y-2 text-red-700 mb-4">
                                <li>Toutes vos informations personnelles et professionnelles</li>
                                <li>Votre historique et vos activités sur la plateforme</li>
                                <?php if ($userRole === 'recruteur'): ?>
                                    <li>Votre abonnement en cours (sans remboursement)</li>
                                    <li>L'accès aux candidats consultés</li>
                                <?php else: ?>
                                    <li>Votre CV et vos candidatures en cours</li>
                                    <li>Votre visibilité auprès des recruteurs</li>
                                <?php endif; ?>
                            </ul>
                            <p class="text-red-800 font-semibold">Cette action ne peut pas être annulée.</p>
                        </div>
                    </div>
                </div>

                <!-- Alternatives -->
                <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg mb-8">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <i class="fas fa-lightbulb text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-green-900 mb-3">💡 Vous hésitez ?</h3>
                            <p class="text-green-800 mb-3">Avant de supprimer votre compte, considérez ces alternatives :</p>
                            <ul class="list-disc list-inside space-y-2 text-green-700">
                                <li>Mettez à jour vos informations de profil</li>
                                <li>Modifiez vos préférences de notification</li>
                                <?php if ($userRole === 'recruteur'): ?>
                                    <li>Résiliez votre abonnement tout en conservant votre compte</li>
                                <?php else: ?>
                                    <li>Mettez votre profil en pause temporairement</li>
                                <?php endif; ?>
                                <li>Contactez notre support pour toute assistance</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="flex flex-col sm:flex-row gap-4 justify-between items-center pt-6 border-t border-gray-200">
                    <a href="<?= $userRole === 'recruteur' ? 'dashboard-recruteur.php' : 'dashboard-candidat.php' ?>" 
                       class="flex-1 w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-8 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-arrow-left"></i>
                        <span>Retour au tableau de bord</span>
                    </a>

                    <form method="post" class="flex-1 w-full sm:w-auto" onsubmit="return confirm('⚠️ CONFIRMATION FINALE\n\nÊtes-vous absolument certain(e) de vouloir supprimer définitivement votre compte ?\n\nToutes vos données seront perdues sans possibilité de récupération.\n\nCliquez sur OK pour confirmer la suppression.');">
                        <button type="submit" name="delete_account" 
                                class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white py-3 px-8 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                            <i class="fas fa-trash-alt"></i>
                            <span>Supprimer définitivement mon compte</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>

        <!-- Note de bas de page -->
        <div class="mt-6 text-center text-gray-600 text-sm">
            <p>Besoin d'aide ? Contactez-nous à <a href="mailto:<?= htmlspecialchars($adminEmail) ?>" class="text-green-600 hover:text-green-700 font-semibold"><?= htmlspecialchars($adminEmail) ?></a></p>
        </div>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>
