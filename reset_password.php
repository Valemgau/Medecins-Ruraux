<?php
require_once './includes/config.php';

$errors = [];
$success = false;

$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: login.php');
    exit;
}

// Vérifier token en base et expiration
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_password_token = ? AND reset_password_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $errors[] = "Lien invalide ou expiré, veuillez renouveler la demande de réinitialisation.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!$password || strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($password !== $passwordConfirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password = ?, reset_password_token = NULL, reset_password_expires = NULL WHERE id = ?");
        $update->execute([$passwordHash, $user['id']]);

        $success = true;
        header("Refresh:3; url=login.php");
    }
}

$title = "Réinitialisation du mot de passe";
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
        
        .password-toggle {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .password-toggle:hover {
            opacity: 0.7;
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
        <img src="assets/img/doctor2.jpg" alt="Medical background" onerror="this.parentElement.style.background='linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)'" />
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
                        <path d="M12 1C8.676 1 6 3.676 6 7v3H5c-1.1 0-2 .9-2 2v9c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-9c0-1.1-.9-2-2-2h-1V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v3H8V7c0-2.276 1.724-4 4-4zm0 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z" stroke="white" stroke-width="0.5" stroke-linecap="round" stroke-linejoin="round" fill="white"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-2 tracking-tight">
                    Nouveau mot de passe
                </h1>
                <p class="text-gray-600 text-base font-light">
                    Choisissez un mot de passe sécurisé
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
                            title: 'Mot de passe modifié !',
                            text: 'Redirection vers la page de connexion...',
                            timer: 3000,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'rounded-3xl'
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if (!$success): ?>
                <!-- Formulaire -->
                <form action="?token=<?= htmlspecialchars($token) ?>" method="post" novalidate class="space-y-5">
                    
                    <!-- Nouveau mot de passe -->
                    <div>
                        <label for="password" class="block mb-2 font-medium text-gray-900 text-sm">
                            Nouveau mot de passe
                        </label>
                        <div class="relative">
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                required
                                placeholder="Au moins 6 caractères"
                                class="input-field w-full px-4 py-3.5 pr-12 rounded-full text-base"
                            />
                            <button 
                                type="button" 
                                class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 flex items-center justify-center"
                                onclick="togglePassword('password')"
                                aria-label="Afficher/masquer le mot de passe"
                            >
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirmation mot de passe -->
                    <div>
                        <label for="password_confirm" class="block mb-2 font-medium text-gray-900 text-sm">
                            Confirmer le mot de passe
                        </label>
                        <div class="relative">
                            <input 
                                id="password_confirm" 
                                name="password_confirm" 
                                type="password" 
                                required
                                placeholder="Retapez votre mot de passe"
                                class="input-field w-full px-4 py-3.5 pr-12 rounded-full text-base"
                            />
                            <button 
                                type="button" 
                                class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 flex items-center justify-center"
                                onclick="togglePassword('password_confirm')"
                                aria-label="Afficher/masquer le mot de passe"
                            >
                                <i class="fas fa-eye" id="password_confirm-icon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Indicateur de force -->
                    <div class="bg-gray-50 rounded-2xl p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-shield-alt text-green-600 mt-0.5"></i>
                            <div class="text-sm text-gray-600">
                                <p class="font-medium text-gray-900 mb-1">Mot de passe sécurisé</p>
                                <ul class="space-y-1 text-xs">
                                    <li>• Au moins 6 caractères</li>
                                    <li>• Mélangez lettres et chiffres</li>
                                    <li>• Évitez les mots courants</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bouton submit -->
                    <button type="submit" class="btn-primary w-full text-white font-semibold py-3.5 rounded-full text-base shadow-lg mt-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        Réinitialiser le mot de passe
                    </button>
                </form>

                <!-- Lien retour connexion -->
                <div class="mt-6 text-center">
                    <a href="login.php" class="text-sm text-gray-600 hover:text-green-600 transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Retour à la connexion
                    </a>
                </div>
            <?php else: ?>
                <!-- Succès -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check text-green-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Mot de passe modifié !</h3>
                    <p class="text-gray-600 mb-6">Redirection en cours...</p>
                    <a href="login.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-400 to-green-600 text-white rounded-full font-semibold hover:from-green-500 hover:to-green-700 transition-all duration-200 shadow-sm">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Se connecter maintenant
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Validation en temps réel
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');

        if (password && passwordConfirm) {
            passwordConfirm.addEventListener('input', function() {
                if (this.value && password.value !== this.value) {
                    this.style.borderColor = '#ef4444';
                } else if (this.value && password.value === this.value) {
                    this.style.borderColor = '#22c55e';
                } else {
                    this.style.borderColor = '#e5e7eb';
                }
            });
        }
    </script>

</body>
</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_auth.php';
?>