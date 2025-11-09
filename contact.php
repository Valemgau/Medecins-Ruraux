<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userId = $_SESSION['user_id'] ?? '';
$userRole = $_SESSION['role'] ?? '';

$errors = [];
$success = false;

// Générer un captcha mathématique simple
if (!isset($_SESSION['captcha_answer'])) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

// Traitement form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captcha_response = trim($_POST['captcha'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');

    // Vérification honeypot
    if (!empty($honeypot)) {
        $errors[] = "Erreur de validation.";
    }

    if (!$nom) {
        $errors[] = "Le nom est obligatoire.";
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Un email valide est obligatoire.";
    }
    if (!$message) {
        $errors[] = "Le message est obligatoire.";
    }

    // Vérification captcha
    if ($captcha_response != $_SESSION['captcha_answer']) {
        $errors[] = "La réponse au calcul est incorrecte.";
    }

    if (empty($errors)) {
        // Envoi mail admin
        try {
            $mail = new PHPMailer(true);
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
            $mail->Subject = "Nouveau message de contact";

            $body = "
                <div style='font-family: system-ui, -apple-system, sans-serif; padding: 20px;'>
                    <h2 style='color: #22c55e;'>Nouveau message de contact</h2>
                    <p><strong>Nom :</strong> " . htmlspecialchars($nom) . "</p>
                    <p><strong>Email :</strong> " . htmlspecialchars($email) . "</p>
                    <p><strong>Message :</strong></p>
                    <div style='background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 10px;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                </div>
            ";

            $mail->Body = $body;
            $mail->send();

            $success = true;
            
            // Réinitialiser le captcha
            unset($_SESSION['captcha_answer']);
            unset($_SESSION['captcha_num1']);
            unset($_SESSION['captcha_num2']);
            
            // Vider les champs
            $_POST = [];
        } catch (Exception $e) {
            $errors[] = "Erreur lors de l'envoi du message.";
            error_log("Erreur mail contact: " . $mail->ErrorInfo);
        }
    }
    
    // Régénérer le captcha en cas d'erreur
    if (!empty($errors)) {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    }
}

$title = "Contact";
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
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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
            backdrop-filter: blur(2px);
            z-index: 1;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .input-field {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            font-size: 15px;
        }
        
        .input-field:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .input-field:focus {
            outline: none;
            background: white;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .icon-container {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .captcha-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); }
            50% { box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); }
        }
        
        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .label-text {
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        
        @media (max-width: 640px) {
            .form-card {
                border-radius: 1.5rem !important;
                padding: 2rem !important;
            }
            
            .icon-container {
                width: 4.5rem !important;
                height: 4.5rem !important;
            }
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
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4 pt-24 pb-12">
        
        <!-- Formulaire -->
        <div class="form-card w-full max-w-2xl rounded-3xl p-10 sm:p-14 fade-in">
            
            <!-- Icône -->
            <div class="mb-10 flex justify-center">
                <div class="icon-container w-24 h-24 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-envelope text-white text-4xl"></i>
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-10">
                <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-3 tracking-tight">
                    Contactez-nous
                </h1>
                <p class="text-gray-600 text-lg font-normal">
                    Une question ? Nous sommes là pour vous répondre
                </p>
            </div>

            <?php if ($errors): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            html: `
                                <div style="text-align:left; padding: 0 1rem;">
                                    <?php foreach ($errors as $error): ?>
                                        <p style="margin-bottom: 0.5rem; color: #6b7280; font-size: 15px;">• <?= htmlspecialchars($error) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            `,
                            confirmButtonText: 'Compris',
                            confirmButtonColor: '#10b981',
                            customClass: {
                                popup: 'rounded-3xl',
                                confirmButton: 'rounded-full px-8 py-3'
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
                            title: 'Message envoyé !',
                            text: 'Nous vous répondrons dans les plus brefs délais.',
                            confirmButtonText: 'Parfait',
                            confirmButtonColor: '#10b981',
                            customClass: {
                                popup: 'rounded-3xl',
                                confirmButton: 'rounded-full px-8 py-3'
                            }
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    });
                </script>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="post" novalidate class="space-y-6">
                
                <!-- Honeypot -->
                <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">
                
                <!-- Nom -->
                <div>
                    <label for="nom" class="label-text block mb-2.5 text-gray-900 text-sm">
                        Nom complet <span class="text-red-500">*</span>
                    </label>
                    <input 
                        id="nom" 
                        name="nom" 
                        type="text" 
                        required 
                        value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                        placeholder="Jean Dupont"
                        class="input-field w-full px-5 py-4 rounded-2xl"
                    />
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="label-text block mb-2.5 text-gray-900 text-sm">
                        Adresse email <span class="text-red-500">*</span>
                    </label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="nom@exemple.fr"
                        class="input-field w-full px-5 py-4 rounded-2xl"
                    />
                </div>
                
                <!-- Message -->
                <div>
                    <label for="message" class="label-text block mb-2.5 text-gray-900 text-sm">
                        Votre message <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        id="message" 
                        name="message" 
                        rows="6" 
                        required
                        placeholder="Décrivez votre demande en détail..."
                        class="input-field w-full px-5 py-4 rounded-2xl resize-none"
                    ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <!-- Captcha mathématique -->
                <div>
                    <label for="captcha" class="label-text block mb-2.5 text-gray-900 text-sm">
                        Vérification de sécurité <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-4">
                        <div class="captcha-box flex-shrink-0 text-white font-bold px-8 py-4 rounded-2xl text-lg min-w-[160px] text-center">
                            <?php echo $_SESSION['captcha_num1']; ?> + <?php echo $_SESSION['captcha_num2']; ?> = ?
                        </div>
                        <input 
                            id="captcha" 
                            name="captcha" 
                            type="number" 
                            required
                            placeholder="Votre réponse"
                            class="input-field flex-1 px-5 py-4 rounded-2xl"
                        />
                    </div>
                    <p class="mt-2.5 text-xs text-gray-500">Résolvez ce calcul simple pour confirmer que vous êtes humain</p>
                </div>
                
                <!-- Bouton submit -->
                <button type="submit" class="btn-primary w-full text-white font-semibold py-4 rounded-2xl text-base shadow-lg mt-8 relative">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Envoyer le message
                </button>
            </form>
            
            <!-- Info supplémentaire -->
            <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-lock mr-2 text-green-600"></i>
                    Vos données sont protégées et ne seront jamais partagées
                </p>
            </div>
            
        </div>
    </div>

</body>
</html>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_default.php';
?>
