<?php
require_once './includes/config.php';

$role = $_GET['role'] ?? 'candidat';
$role = in_array($role, ['candidat', 'recruteur']) ? $role : 'candidat';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
  $password = $_POST['password'] ?? '';

  if (!$email) {
    $errors[] = "Email invalide.";
  }
  if (!$password) {
    $errors[] = "Le mot de passe est requis.";
  }

  if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
      if ($user['role'] !== $role && $user['role'] !== 'admin') {
        $errors[] = "Une erreur est survenue, vous tentez de vous connecter en tant que candidat alors que votre compte est enregistré en tant que recruteur, ou inversement.";
      } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
          header('Location: /admin');
          exit;
        } elseif ($role === 'recruteur') {
          header('Location: dashboard-recruteur.php');
          exit;
        } else {
          header('Location: dashboard-candidat.php');
          exit;
        }
      }
    } else {
      $errors[] = "Email ou mot de passe incorrect.";
    }
  }
}

$title = $role === 'recruteur' ? "Connexion recruteur" : "Connexion candidat";
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
            /* Positionnement du background à gauche sur desktop */
            object-position: 35% center;
        }
        
        .background-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .input-field {
            transition: all 0.2s ease;
            background: #f9fafb;
           border: 2px solid lightgray;
        }
        
        .input-field:hover {
            background: #f3f4f6;
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
        
        .link-text {
            transition: color 0.2s ease;
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
        
        /* Layout pour desktop - formulaire à droite */
        @media (min-width: 1024px) {
            .content-wrapper {
                justify-content: flex-end;
                padding-right: 8%;
            }
            
            .background-image img {
                object-position: 30% center;
            }
        }
        
        @media (min-width: 1280px) {
            .content-wrapper {
                padding-right: 10%;
            }
            
            .background-image img {
                object-position: 25% center;
            }
        }
        
        @media (max-width: 640px) {
            .back-button {
                top: 1rem !important;
                left: 1rem !important;
            }
            
            .form-card {
                margin-top: 4rem;
            }
            
            .background-image img {
                object-position: center center;
            }
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

<body class="bg-gray-50">
    
    <!-- Image de fond -->
    <div class="background-image">
        <img src="assets/img/doctor4.jpg" alt="Medical background" onerror="this.parentElement.style.background='linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)'" />
    </div>
    
    <!-- Overlay -->
    <div class="background-overlay"></div>
    
    <!-- Contenu -->
    <div class="content-wrapper relative z-10 min-h-screen flex items-center justify-center p-4 sm:pt-4">
        
        <!-- Bouton retour -->
        <button onclick="history.back()" class="back-button fixed top-6 left-6 w-11 h-11 flex items-center justify-center rounded-full text-gray-700 hover:text-gray-900 shadow-lg z-20">
            <i class="fas fa-arrow-left"></i>
        </button>
        
        <!-- Formulaire -->
        <div class="form-card w-full max-w-md rounded-3xl shadow-2xl p-8 sm:p-10 fade-in">
            
            <!-- Logo -->
            <div class="mb-8 flex justify-center">
                <div class="w-20 h-20 rounded-3xl overflow-hidden bg-gradient-to-br from-green-400 to-green-600 shadow-lg">
                    <img src="assets/img/logo.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-green-400 to-green-600\'><i class=\'fas fa-user-md text-white text-2xl\'></i></div>'" />
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-2 tracking-tight">
                    <?= $role === 'recruteur' ? 'Espace recruteur' : 'Espace candidat' ?>
                </h1>
                <p class="text-gray-600 text-base font-light">
                    Connexion
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

            <!-- Formulaire -->
            <form method="post" novalidate class="space-y-5">
                
                <!-- Email -->
                <div>
                    <label for="email" class="block mb-2.5 font-medium text-gray-900 text-sm">
                        Adresse email
                    </label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        required 
                        autocomplete="email" 
                        autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="nom@exemple.fr"
                        class="input-field w-full px-4 py-3.5 rounded-full text-base"
                    />
                </div>
                
                <!-- Mot de passe -->
                <div>
                    <label for="password" class="block mb-2.5 font-medium text-gray-900 text-sm">
                        Mot de passe
                    </label>
                    <div class="relative">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field w-full px-4 py-3.5 pr-12 rounded-full text-base"
                        />
                        <button 
                            type="button" 
                            class="password-toggle absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 flex items-center justify-center"
                            onclick="togglePassword()"
                            aria-label="Afficher/masquer le mot de passe"
                        >
                            <i class="fas fa-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Lien mot de passe oublié -->
                <div class="text-right">
                    <a href="forgot_password.php" class="link-text text-sm text-green-600 hover:text-green-700 font-medium">
                        Mot de passe oublié ?
                    </a>
                </div>
                
                <!-- Bouton connexion -->
                <button type="submit" class="btn-primary w-full text-white font-semibold py-3.5 rounded-full text-base mt-6 shadow-lg">
                    Se connecter
                </button>
            </form>
            
            <!-- Séparateur -->
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white text-gray-400 font-light">ou</span>
                </div>
            </div>
            
            <!-- Liens -->
            <div class="space-y-5 text-center">
                
                <!-- Créer un compte -->
                <div>
                    <p class="text-gray-600 text-sm mb-2">
                        Pas encore de compte ?
                    </p>
                    <a 
                        href="register.php?role=<?= $role ?>" 
                        class="link-text text-green-600 hover:text-green-700 font-semibold"
                    >
                        Créer un compte <?= $role === 'recruteur' ? 'recruteur' : 'candidat' ?>
                    </a>
                </div>
                
                <!-- Changer de rôle -->
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-gray-600 text-sm mb-2">
                        Vous êtes <?= $role === 'recruteur' ? 'candidat' : 'recruteur' ?> ?
                    </p>
                    <a 
                        href="login.php?role=<?= $role === 'recruteur' ? 'candidat' : 'recruteur' ?>" 
                        class="link-text text-green-600 hover:text-green-700 font-semibold"
                    >
                        Se connecter en tant que <?= $role === 'recruteur' ? 'candidat' : 'recruteur' ?>
                    </a>
                </div>
                
            </div>
            
        </div>
        
    </div>

    <!-- Scripts -->
    <script>
        // Toggle password
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('password-icon');
            
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
        
        // Validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Champs requis',
                    text: 'Veuillez remplir tous les champs',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#22c55e',
                    customClass: {
                        popup: 'rounded-3xl',
                        confirmButton: 'rounded-full px-8'
                    }
                });
            }
        });
    </script>

</body>
</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_auth.php';
?>