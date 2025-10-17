<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$userId = $_SESSION['user_id'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

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
    $honeypot = trim($_POST['website'] ?? ''); // Champ caché anti-bot

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

            $mail->setFrom($smtpUser, 'Site Médecins Ruraux');
            $mail->addAddress($adminEmail);

            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = "Nouveau message contact via site";

            $body = "
                <div style='font-family: Inter, sans-serif; padding: 20px;'>
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
        
        .back-button {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
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
        
        @media (max-width: 640px) {
            .back-button {
                top: 1rem !important;
                left: 1rem !important;
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
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4 pt-20 sm:pt-8 py-12">
        
        <!-- Bouton retour -->
        <button onclick="history.back()" class="back-button fixed top-6 left-6 w-11 h-11 flex items-center justify-center rounded-full text-gray-700 hover:text-gray-900 shadow-lg z-20">
            <i class="fas fa-arrow-left"></i>
        </button>
        
        <!-- Formulaire -->
        <div class="form-card w-full max-w-xl rounded-3xl shadow-2xl p-8 sm:p-12 fade-in">
            
            <!-- Icône -->
            <div class="mb-8 flex justify-center">
                <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-10 h-10" fill="black" viewBox="0 0 24 24">
                        <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-2 tracking-tight">
                    Contactez-nous
                </h1>
                <p class="text-gray-600 text-base font-light">
                    Nous sommes là pour vous aider
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
                            title: 'Message envoyé !',
                            text: 'Nous vous répondrons dans les plus brefs délais.',
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

            <!-- Formulaire -->
            <form method="post" novalidate class="space-y-5">
                
                <!-- Honeypot (champ caché anti-bot) -->
                <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">
                
                <!-- Nom -->
                <div>
                    <label for="nom" class="block mb-2 font-medium text-gray-900 text-sm">
                        Nom complet
                    </label>
                    <input 
                        id="nom" 
                        name="nom" 
                        type="text" 
                        required 
                        value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                        placeholder="Jean Dupont"
                        class="input-field w-full px-4 py-3.5 rounded-full text-base"
                    />
                </div>
                
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
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="nom@exemple.fr"
                        class="input-field w-full px-4 py-3.5 rounded-full text-base"
                    />
                </div>
                
                <!-- Message -->
                <div>
                    <label for="message" class="block mb-2 font-medium text-gray-900 text-sm">
                        Votre message
                    </label>
                    <textarea 
                        id="message" 
                        name="message" 
                        rows="5" 
                        required
                        placeholder="Décrivez votre demande..."
                        class="input-field w-full px-4 py-3.5 rounded-3xl text-base resize-none"
                    ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <!-- Captcha mathématique -->
                <div>
                    <label for="captcha" class="block mb-2 font-medium text-gray-900 text-sm">
                        Vérification anti-robot
                    </label>
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 bg-gradient-to-br from-green-400 to-green-600  font-semibold px-6 py-3.5 rounded-full text-base min-w-[140px] text-center shadow-lg">
                            <?php echo $_SESSION['captcha_num1']; ?> + <?php echo $_SESSION['captcha_num2']; ?> = ?
                        </div>
                        <input 
                            id="captcha" 
                            name="captcha" 
                            type="number" 
                            required
                            placeholder="Réponse"
                            class="input-field flex-1 px-4 py-3.5 rounded-full text-base"
                        />
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Résolvez ce calcul pour vérifier que vous n'êtes pas un robot</p>
                </div>
                
                <!-- Bouton submit -->
                <button type="submit" class="btn-primary w-full  font-semibold py-3.5 rounded-full text-base shadow-lg mt-6">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Envoyer le message
                </button>
            </form>
            
        </div>
    </div>

</body>
</html>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_default.php';
?>