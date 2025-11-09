<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $prenom, $nom, $token, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $adminEmail, $baseUrl)
{
    $verifyUrl = $baseUrl . "/verify_email.php?token=$token";
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
        $mail->addAddress($email, "$prenom $nom");
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "Validation de votre adresse email";
        $mail->Body = '
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Validation Email</title>
  <style>
    body { margin: 0; padding: 0; background-color: #22c55e; font-family: Inter, sans-serif; color: #111827; }
    .container { max-width: 600px; margin: 40px auto; background: white; border-radius: 24px; padding: 40px; text-align: center; box-shadow: 0 4px 18px rgba(0,0,0,0.1); }
    h1 { color: #22c55e; margin-bottom: 20px; font-weight: 600; font-size: 28px; }
    p { font-size: 16px; line-height: 1.6; margin-bottom: 20px; color: #6b7280; }
    a.button { background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); color: white; padding: 14px 32px; border-radius: 50px; font-weight: 600; text-decoration: none; display: inline-block; }
    .footer { margin-top: 30px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Bienvenue ' . htmlspecialchars($prenom) . ' !</h1>
    <p>Merci de vous être inscrit sur <strong>Médecins Ruraux</strong>.</p>
    <p>Pour activer votre compte, cliquez sur le bouton ci-dessous :</p>
    <p><a href="' . $verifyUrl . '" class="button">Valider mon compte</a></p>
    <p class="footer">Si vous n\'avez pas créé ce compte, ignorez ce message.</p>
  </div>
</body>
</html>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail validation: " . $mail->ErrorInfo);
        return false;
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$messages = [];
$success = false;

$stmt = $pdo->prepare("SELECT id, email, role, email_verified, email_verify_token, updated_at, next_email_send_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['email_verified']) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruteur') {
        header('Location: dashboard-recruteur.php');
    } else {
        header('Location: dashboard-candidat.php');
    }
    exit;
}

$prenom = '';
$nom = '';
if ($user['role'] === 'candidat') {
    $stmt2 = $pdo->prepare("SELECT prenom, nom FROM candidats WHERE id = ?");
    $stmt2->execute([$_SESSION['user_id']]);
    $profile = $stmt2->fetch();
    $prenom = $profile['prenom'] ?? '';
    $nom = $profile['nom'] ?? '';
} elseif ($user['role'] === 'recruteur') {
    $stmt2 = $pdo->prepare("SELECT prenom, nom FROM recruteurs WHERE id = ?");
    $stmt2->execute([$_SESSION['user_id']]);
    $profile = $stmt2->fetch();
    $prenom = $profile['prenom'] ?? '';
    $nom = $profile['nom'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = new DateTime();
    $nextSend = $user['next_email_send_at'] ? new DateTime($user['next_email_send_at']) : null;

    if ($nextSend && $now < $nextSend) {
        $diffSec = $nextSend->getTimestamp() - $now->getTimestamp();
        $errors[] = "Veuillez patienter encore $diffSec secondes avant de renvoyer l'email.";
    } else {
        $token = $user['email_verify_token'];
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            $updateToken = $pdo->prepare("UPDATE users SET email_verify_token = ? WHERE id = ?");
            $updateToken->execute([$token, $_SESSION['user_id']]);
        }

        $result = sendVerificationEmail(
            $user['email'],
            $prenom,
            $nom,
            $token,
            $_ENV['SMTP_HOST'],
            $_ENV['SMTP_PORT'],
            $_ENV['SMTP_USER'],
            $_ENV['SMTP_PASS'],
            $_ENV['ADMIN_EMAIL'],
            $_ENV['BASE_URL']
        );

        if ($result) {
            $delayMinutes = 1;
            if ($nextSend && $nextSend > $now) {
                $interval = $nextSend->getTimestamp() - $now->getTimestamp();
                $delayMinutes = ($interval / 60) + 3;
            }

            $newNextSend = (clone $now)->modify("+$delayMinutes minutes");

            $update = $pdo->prepare("UPDATE users SET updated_at = NOW(), next_email_send_at = ? WHERE id = ?");
            $update->execute([$newNextSend->format('Y-m-d H:i:s'), $_SESSION['user_id']]);

            $success = true;
            $messages[] = "Email envoyé avec succès ! Vérifiez votre boîte de réception.";
        } else {
            $errors[] = "Erreur lors de l'envoi. Veuillez réessayer plus tard.";
        }
    }
}

$title = "Validation d'Email";
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

        body {
            margin: 0;
            padding: 0;
        }

        .background-image {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        .background-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .background-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(34, 197, 94, 0.25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>

<body class="bg-white">

    <!-- Image de fond -->
    <div class="background-image">
        <img src="assets/img/doctor3.jpg" alt="Medical background"
            onerror="this.parentElement.style.background='linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)'" />
    </div>

    <!-- Overlay -->
    <div class="background-overlay"></div>

    <!-- Contenu -->
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">

        <!-- Formulaire -->
        <div class="form-card w-full max-w-lg rounded-3xl shadow-2xl p-8 sm:p-12 fade-in">

            <!-- Icône -->
            <div class="mb-8 flex justify-center">
                <div
                    class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                        </path>
                    </svg>
                </div>
            </div>

            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-3 tracking-tight">
                    Vérifiez votre email
                </h1>
                <p class="text-gray-600 text-base leading-relaxed">
                    Un email de validation a été envoyé à<br>
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($user['email']) ?></span>
                </p>
            </div>

            <?php if ($errors): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Attention',
                            html: `
                                <div style="text-align:left; padding-left: 1rem;">
                                    <?php foreach ($errors as $error): ?>
                                        <p style="margin-bottom: 0.5rem; color: #6b7280;"><?= htmlspecialchars($error) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            `,
                            confirmButtonText: 'Compris',
                            confirmButtonColor: '#22c55e',
                            customClass: {
                                popup: 'rounded-3xl',
                                confirmButton: 'rounded-full px-8'
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Email envoyé !',
                            text: 'Vérifiez votre boîte de réception.',
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

            <!-- Instructions -->
            <div class="bg-green-50 rounded-2xl p-6 mb-8">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Suivez ces étapes :</h3>
                        <ol class="text-sm text-gray-600 space-y-2">
                            <li class="flex items-start">
                                <span class="mr-2">1.</span>
                                <span>Consultez votre boîte de réception</span>
                            </li>
                            <li class="flex items-start">
                                <span class="mr-2">2.</span>
                                <span>Ouvrez l'email de vérification</span>
                            </li>
                            <li class="flex items-start">
                                <span class="mr-2">3.</span>
                                <span>Cliquez sur le lien de validation</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Boutons actions -->
            <form method="post" class="space-y-4">
                <button type="submit"
                    class="btn-primary w-full text-white font-semibold py-3.5 rounded-full text-base shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Renvoyer l'email
                </button>
            </form>

            <button onclick="location.reload()"
                class="btn-secondary w-full bg-white text-gray-700 font-medium py-3.5 rounded-full text-base mt-3">
                <i class="fas fa-sync-alt mr-2"></i>
                Vérifier la validation
            </button>

            <!-- Aide -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <details class="group">
                    <summary
                        class="flex items-center justify-between cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900">
                        <span>Vous ne recevez pas l'email ?</span>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="mt-4 text-sm text-gray-600 space-y-2">
                        <p><i class="fas fa-check text-green-500 mr-2"></i>Vérifiez votre dossier spam</p>
                        <p><i class="fas fa-check text-green-500 mr-2"></i>Vérifiez l'adresse email saisie</p>
                        <p><i class="fas fa-check text-green-500 mr-2"></i>Attendez quelques minutes</p>
                        <p><i class="fas fa-check text-green-500 mr-2"></i>Contactez le support si le problème persiste
                        </p>
                    </div>
                </details>
            </div>

            <!-- Déconnexion -->
            <div class="mt-6 text-center">
                <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    Se déconnecter
                </a>
            </div>

        </div>
    </div>

</body>

</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_no.php';
?>