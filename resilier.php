<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Stripe;
use \Stripe\Subscription;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($userRole !== 'recruteur') {
    die("Rôle utilisateur inconnu");
}

$stmt = $pdo->prepare("SELECT u.email, u.created_at, u.stripe_subscription_id, u.subscription_current_period_end, r.prenom, r.nom, r.photo FROM users u JOIN recruteurs r ON u.id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur non trouvé");
}

$alert = null;
$subscription = null;
$subscriptionStatus = null;

try {
    if (!empty($user['stripe_subscription_id'])) {
        $subscription = Subscription::retrieve($user['stripe_subscription_id']);
        $subscriptionStatus = $subscription->status;
        $periodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $expireDate = $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null;
    }
} catch (Exception $e) {
    $alert = ['type' => 'error', 'text' => "Erreur lors de la récupération de l'abonnement Stripe."];
}

function generateCancellationPdf($toName, $subscriptionId, $periodEndTimestamp, $userDetails, $baseUrl)
{
    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; max-width:650px; margin:auto; padding:30px; color:#222;">
        <div style="text-align:center; margin-bottom:30px;">
            <h1 style="font-weight:900; font-size:30px; color:#222;">Confirmation de résiliation</h1>
            <p>Bonjour <?= htmlspecialchars($toName) ?>,</p>
            <p>Nous confirmons la résiliation de votre abonnement.</p>
        </div>
        <p>Votre abonnement <strong><?= htmlspecialchars($subscriptionId) ?></strong> restera actif jusqu'au
            <strong><?= $periodEndTimestamp ?></strong>.
        </p>
        <h2 style="margin-top:30px; border-bottom:1px solid #ddd; padding-bottom:6px;">Détails utilisateur</h2>
        <p><strong>Nom :</strong> <?= htmlspecialchars($userDetails['prenom'] . ' ' . $userDetails['nom']) ?></p>
        <p><strong>Email :</strong> <?= htmlspecialchars($userDetails['email']) ?></p>
        <p style="margin-top:30px; font-weight:600;">Merci pour la confiance que vous nous avez accordée.</p>
    </div>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function sendCancellationEmail($toEmail, $toName, $subject, $pdfOutput, $adminEmail, $baseUrl)
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
        $mail->Subject = $subject;

        $mail->Body = '
        <div style="font-family: Arial, sans-serif; color: #222;">
            <p>Bonjour ' . htmlspecialchars($toName) . ',</p>
            <p>Votre demande de résiliation a bien été prise en compte.</p>
        </div>
        ';

        $mail->addStringAttachment($pdfOutput, 'confirmation_resiliation_' . date('Ymd_His') . '.pdf', 'base64', 'application/pdf');
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail résiliation: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_subscription'])) {
    if ($subscription && in_array($subscription->status, ['active', 'trialing', 'past_due'])) {
        try {
            $cancelSub = Subscription::update($subscription->id, [
                'cancel_at_period_end' => true,
            ]);
            $periodEnd = $cancelSub->items->data[0]->current_period_end ?? null;
            $expireDate = $periodEnd ? date('Y-m-d H:i:s', $periodEnd) : null;
            $alert = ['type' => 'success', 'text' => 'Résiliation prise en compte, votre abonnement reste actif jusqu\'au ' . date('d/m/Y', strtotime($expireDate))];

            $userDetails = [
                'prenom' => $user['prenom'] ?? '',
                'nom' => $user['nom'] ?? '',
                'email' => $user['email'] ?? '',
            ];

            $pdfOutput = generateCancellationPdf(
                trim($userDetails['prenom'] . ' ' . $userDetails['nom']),
                $subscription->id,
                $expireDate,
                $userDetails,
                $baseUrl
            );

            $subject = "Confirmation de résiliation d'abonnement";

            sendCancellationEmail(
                $user['email'],
                trim($userDetails['prenom'] . ' ' . $userDetails['nom']),
                $subject,
                $pdfOutput,
                $adminEmail,
                $baseUrl
            );

        } catch (Exception $e) {
            $alert = ['type' => 'error', 'text' => "Impossible de résilier l'abonnement : " . $e->getMessage()];
        }
    } else {
        $alert = ['type' => 'error', 'text' => "Aucun abonnement actif à résilier."];
    }
}

$title = "Résiliation abonnement";
ob_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        
        
        * {
            
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>

<body class="bg-gray-50">
    
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($alert): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: '<?= $alert['type'] === 'success' ? 'success' : 'error' ?>',
                        title: '<?= $alert['type'] === 'success' ? 'Succès' : 'Erreur' ?>',
                        text: '<?= addslashes($alert['text']) ?>',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#22c55e',
                        customClass: {
                            popup: 'rounded-3xl',
                            confirmButton: 'rounded-full px-8'
                        }
                    });
                });
            </script>
        <?php endif; ?>


        <!-- Informations abonnement -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
            
            <!-- Header -->
            <div class="px-8 py-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-credit-card text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Mon abonnement</h2>
                        <p class="text-sm text-gray-600">Gérez votre souscription</p>
                    </div>
                </div>
            </div>

            <!-- Contenu -->
            <div class="px-8 py-8">
                <?php if ($subscription): ?>
                    <?php
                        $priceNickname = $subscription->items->data[0]->price->nickname ?? 'Abonnement';
                        $periodEnd = $subscription->current_period_end ?? $subscription->items->data[0]->current_period_end ?? null;
                        $expireDateFormatted = $periodEnd ? date('d/m/Y', $periodEnd) : null;
                    ?>

                    <!-- Détails abonnement -->
                    <div class="space-y-4 mb-8">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Formule</p>
                                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($priceNickname) ?></p>
                            </div>
                            <div class="px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                <i class="fas fa-check-circle mr-1"></i>
                                Actif
                            </div>
                        </div>

                        <?php if ($expireDateFormatted): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Renouvellement</p>
                                    <p class="text-lg font-semibold text-gray-900"><?= $expireDateFormatted ?></p>
                                </div>
                                <i class="fas fa-calendar-check text-gray-400 text-2xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <?php if ($subscription->cancel_at_period_end): ?>
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-6 rounded-2xl">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-exclamation-triangle text-amber-600 text-xl mt-1"></i>
                                <div>
                                    <h3 class="font-semibold text-amber-900 mb-1">Résiliation programmée</h3>
                                    <p class="text-sm text-amber-800">
                                        Votre abonnement sera résilié à la fin de la période en cours (<?= $expireDateFormatted ?>).
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php elseif (in_array($subscriptionStatus, ['active', 'trialing', 'past_due'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-6 rounded-2xl mb-6">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-info-circle text-red-600 text-xl mt-1"></i>
                                <div>
                                    <h3 class="font-semibold text-red-900 mb-1">Résilier mon abonnement</h3>
                                    <p class="text-sm text-red-800 mb-4">
                                        La résiliation prendra effet à la fin de votre période de facturation actuelle. Vous garderez l'accès à tous les services jusqu'au <?= $expireDateFormatted ?>.
                                    </p>
                                    <button onclick="confirmCancellation()" 
                                        class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-full font-semibold transition-all duration-200 shadow-sm">
                                        <i class="fas fa-times-circle mr-2"></i>
                                        Résilier l'abonnement
                                    </button>
                                </div>
                            </div>
                        </div>

                        <form id="cancelForm" method="post" style="display: none;">
                            <input type="hidden" name="cancel_subscription" value="1">
                        </form>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-600 mb-4">Aucun abonnement actif à résilier</p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-receipt text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucun abonnement</h3>
                        <p class="text-gray-600 mb-6">Vous n'avez pas encore souscrit à un abonnement</p>
                        <a href="abonnement.php" 
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full font-semibold hover:from-green-500 hover:to-green-700 transition-all duration-200 shadow-sm">
                            <i class="fas fa-plus mr-2"></i>
                            Souscrire à un abonnement
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmCancellation() {
            Swal.fire({
                title: 'Confirmer la résiliation ?',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 1rem; color: #6b7280;">
                            Êtes-vous sûr de vouloir résilier votre abonnement ?
                        </p>
                        <ul style="list-style: disc; margin-left: 1.5rem; color: #6b7280;">
                            <li style="margin-bottom: 0.5rem;">Votre abonnement restera actif jusqu'à la fin de la période</li>
                            <li style="margin-bottom: 0.5rem;">Vous recevrez une confirmation par email</li>
                            <li style="margin-bottom: 0.5rem;">Cette action est réversible</li>
                        </ul>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, résilier',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl',
                    confirmButton: 'rounded-full px-8',
                    cancelButton: 'rounded-full px-8'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('cancelForm').submit();
                }
            });
        }
    </script>

</body>
</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>