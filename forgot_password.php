<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = false;

function sendResetPasswordEmail($email, $prenom, $nom, $token, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $baseUrl) {
    $resetUrl = $baseUrl . "/reset_password.php?token=$token";
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
        $mail->Subject = "Réinitialisation de votre mot de passe";
        $mail->Body = '
        <div style="font-family: Inter, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;">
            <div style="background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); border-radius: 24px; padding: 40px; text-align: center; margin-bottom: 30px;">
                <h1 style="color: white; margin: 0; font-size: 28px;">Réinitialisation du mot de passe</h1>
            </div>
            <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <p style="color: #374151; line-height: 1.6; margin-bottom: 20px;">Bonjour ' . htmlspecialchars($prenom) . ',</p>
                <p style="color: #374151; line-height: 1.6; margin-bottom: 20px;">Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour continuer :</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetUrl . '" style="background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); color: white; padding: 14px 32px; border-radius: 50px; text-decoration: none; display: inline-block; font-weight: 600;">Réinitialiser mon mot de passe</a>
                </div>
                <p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin-top: 20px;">Ce lien expirera dans 1 heure. Si vous n\'avez pas demandé cette réinitialisation, ignorez ce message.</p>
            </div>
        </div>
        ';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi mail de réinitialisation : " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Veuillez saisir une adresse email valide.";
    } else {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['role'] === 'candidat') {
                $stmt2 = $pdo->prepare("SELECT prenom, nom FROM candidats WHERE id = ?");
            } else {
                $stmt2 = $pdo->prepare("SELECT prenom, nom FROM recruteurs WHERE id = ?");
            }
            $stmt2->execute([$user['id']]);
            $profile = $stmt2->fetch();
            $prenom = $profile['prenom'] ?? '';
            $nom = $profile['nom'] ?? '';

            date_default_timezone_set('Europe/Paris');
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

            $update = $pdo->prepare("UPDATE users SET reset_password_token = ?, reset_password_expires = ? WHERE id = ?");
            $update->execute([$token, $expiresAt, $user['id']]);

            $result = sendResetPasswordEmail(
                $email,
                $prenom,
                $nom,
                $token,
                $_ENV['SMTP_HOST'],
                $_ENV['SMTP_PORT'],
                $_ENV['SMTP_USER'],
                $_ENV['SMTP_PASS'],
                $_ENV['BASE_URL']
            );

            if ($result) {
                $success = true;
            } else {
                $errors[] = "Erreur lors de l'envoi du mail.";
            }
        } else {
            // Pour la sécurité, on affiche le même message
            $success = true;
        }
    }
}

$title = "Mot de passe oublié";
ob_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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
        
        .input-field {
            transition: all 0.2s ease;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        
        .input-field:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .input-field:focus {
            outline: none;
            background: white;
            border-color: #4ade80;
            box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.1);
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
    </style>
</head>

<body class="bg-white">
    
    <!-- Image de fond -->
    <div class="background-image">
        <img src="assets/img/doctor1.jpg" alt="Medical background" onerror="this.parentElement.style.background='linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)'" />
    </div>
    
    <!-- Overlay -->
    <div class="background-overlay"></div>
    
    <!-- Contenu -->
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        
        <!-- Formulaire -->
        <div class="form-card w-full max-w-md rounded-3xl shadow-2xl p-8 sm:p-12 fade-in">
            
            <!-- Icône -->
            <div class="mb-8 flex justify-center">
                <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-10 h-10" fill="white" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" stroke="white" stroke-width="0.5"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-2 tracking-tight">
                    Mot de passe oublié ?
                </h1>
                <p class="text-gray-600 text-base font-light leading-relaxed">
                    Pas de souci, nous vous enverrons les instructions de réinitialisation
                </p>
            </div>

            <?php if ($errors): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
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
                            html: '<p style="color: #6b7280;">Si un compte existe avec cette adresse, vous recevrez un email avec les instructions de réinitialisation.</p>',
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

            <?php if (!$success): ?>
                <!-- Formulaire -->
                <form method="post" novalidate class="space-y-6">
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block mb-2 font-medium text-gray-900 text-sm">
                            Adresse email
                        </label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            required 
                            autofocus
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="nom@exemple.fr"
                            class="input-field w-full px-4 py-3.5 rounded-full text-base"
                        />
                    </div>

                    <!-- Info box -->
                    <div class="bg-blue-50 rounded-2xl p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                            <p class="text-sm text-blue-900 leading-relaxed">
                                Vous recevrez un lien de réinitialisation par email. Ce lien sera valide pendant 1 heure.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Bouton submit -->
                    <button type="submit" class="btn-primary w-full text-white font-semibold py-3.5 rounded-full text-base shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Envoyer les instructions
                    </button>
                </form>
            <?php else: ?>
                <!-- État de succès -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Email envoyé !</h3>
                    <p class="text-gray-600 mb-6 text-sm leading-relaxed">
                        Si un compte existe avec cette adresse, vous recevrez un email avec les instructions.
                    </p>
                    <div class="space-y-3">
                        <a href="login.php" 
                            class="block w-full text-center px-6 py-3 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full font-semibold hover:from-green-500 hover:to-green-700 transition-all duration-200 shadow-sm">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Retour à la connexion
                        </a>
                        <button onclick="location.reload()" 
                            class="block w-full text-center px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-full font-medium hover:bg-gray-50 transition-all duration-200">
                            <i class="fas fa-redo mr-2"></i>
                            Renvoyer un email
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lien retour connexion -->
            <div class="mt-8 text-center">
                <a href="login.php" class="text-sm text-gray-600 hover:text-green-600 transition-colors inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour à la connexion
                </a>
            </div>
            
        </div>
    </div>

</body>
</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_auth.php';
?>