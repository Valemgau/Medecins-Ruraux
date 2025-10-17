<?php
require_once './includes/config.php';
require_once './vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];

function clean_string(string $str): string
{
    return htmlspecialchars(trim($str), ENT_QUOTES | ENT_HTML5);
}

function sendVerificationEmail(
    string $email,
    string $prenom,
    string $nom,
    string $token,
    string $smtpHost,
    int $smtpPort,
    string $smtpUser,
    string $smtpPass,
    string $adminEmail,
    string $baseUrl
): bool {
    $verifyUrl = $baseUrl . "/verify_email.php?token=$token";
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpPort === 465 ? 'ssl' : 'tls';

        $mail->setFrom($smtpUser, 'Médecins Ruraux');
        $mail->addAddress($email, "$prenom $nom");
        $mail->addBCC($adminEmail);
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
    <h1>Bienvenue !</h1>
    <p>Merci pour votre inscription. Veuillez activer votre compte en cliquant sur le bouton ci-dessous :</p>
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

$allowedRoles = ['candidat', 'recruteur'];
$role = $_GET['role'] ?? 'candidat';
if (!in_array($role, $allowedRoles, true)) {
    $role = 'candidat';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'candidat';
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = "Rôle invalide.";
        $role = 'candidat';
    }

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $prenom = clean_string($_POST['prenom'] ?? '');
    $nom = clean_string($_POST['nom'] ?? '');

    $ville = clean_string($_POST['ville'] ?? '');
    $pays = clean_string($_POST['pays_nom'] ?? '');
    $fonction = clean_string($_POST['fonction'] ?? '');

    if ($role === 'candidat') {
        $delai_preavis = $_POST['delai_preavis'] ?? null;
        $ville_etablissement = null;
        $pays_etablissement = null;
        $etablissement = null;
    } else {
        $etablissement = clean_string($_POST['etablissement'] ?? '');
        $ville_etablissement = clean_string($_POST['ville_etablissement'] ?? '');
        $pays_etablissement = clean_string($_POST['pays_etablissement_nom'] ?? '');
        $delai_preavis = null;
    }

    if (!$email)
        $errors[] = "Email invalide.";
    if (strlen($password) < 6)
        $errors[] = "Mot de passe trop court (min 6 caractères).";
    if ($password !== $confirm_password)
        $errors[] = "Les mots de passe ne correspondent pas.";
    if (!$prenom)
        $errors[] = "Prénom obligatoire.";
    if (!$nom)
        $errors[] = "Nom obligatoire.";
    if (!$ville)
        $errors[] = "Ville obligatoire.";
    if (!$pays)
        $errors[] = "Pays obligatoire.";
    if (!$fonction)
        $errors[] = "Fonction obligatoire.";

    if ($role === 'candidat') {
        if ($delai_preavis === null || !in_array($delai_preavis, ['1', '2', '3', '4', '5', '6'], true)) {
            $errors[] = "Délai préavis obligatoire et valide.";
        }
    } else {
        if (!$ville_etablissement)
            $errors[] = "Ville établissement obligatoire.";
        if (!$pays_etablissement)
            $errors[] = "Pays établissement obligatoire.";
        if (!$etablissement)
            $errors[] = "Etablissement obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Un utilisateur avec cet email existe déjà.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $verifyToken = bin2hex(random_bytes(32));
            $pdo->beginTransaction();
            try {
                $stmtUser = $pdo->prepare("INSERT INTO users (role, email, password, email_verified, email_verify_token) VALUES (?, ?, ?, 0, ?)");
                $stmtUser->execute([$role, $email, $hash, $verifyToken]);
                $userId = $pdo->lastInsertId();

                $prefix = ($role === 'candidat') ? 'C' : 'R';
                $numero_reference = $prefix . date('Hi') . rand(10, 99);

                if ($role === 'candidat') {
                    $stmtCandidat = $pdo->prepare("INSERT INTO candidats (id, numero_reference, prenom, nom, fonction, ville, pays, delai_preavis) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtCandidat->execute([$userId, $numero_reference, $prenom, $nom, $fonction, $ville, $pays, $delai_preavis]);
                } else {
                    $stmtRecruteur = $pdo->prepare("INSERT INTO recruteurs (id, numero_reference, prenom, nom, etablissement, fonction, ville, pays, ville_etablissement, pays_etablissement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtRecruteur->execute([$userId, $numero_reference, $prenom, $nom, $etablissement, $fonction, $ville, $pays, $ville_etablissement, $pays_etablissement]);
                }

                $pdo->commit();

                global $smtpHost, $smtpPort, $smtpUser, $smtpPass, $adminEmail, $baseUrl;
                sendVerificationEmail($email, $prenom, $nom, $verifyToken, $smtpHost, $smtpPort, $smtpUser, $smtpPass, $adminEmail, $baseUrl);

                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = $role;
                header("Location: dashboard-$role.php?message=register_success");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de l'inscription : " . $e->getMessage();
            }
        }
    }
}

$title = "Inscription";
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
        
        .back-button {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #d1d5db;
            transition: 0.3s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
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
        <img src="assets/img/doctor2.jpg" alt="Medical background" onerror="this.parentElement.style.background='linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)'" />
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
        <div class="form-card w-full max-w-2xl rounded-3xl shadow-2xl p-8 sm:p-12 fade-in max-h-[85vh] overflow-y-auto">
            
            <!-- Logo -->
            <div class="mb-6 flex justify-center">
                <div class="w-16 h-16 rounded-3xl overflow-hidden bg-gradient-to-br from-green-400 to-green-600 shadow-lg">
                    <img src="assets/img/logo.jpg" alt="Logo" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-green-400 to-green-600\'><i class=\'fas fa-user-md text-white text-xl\'></i></div>'" />
                </div>
            </div>
            
            <!-- Titre -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-semibold text-gray-900 mb-2 tracking-tight">
                    Inscription
                </h1>
                <p class="text-gray-600 text-base font-light">
                    <?= $role === 'recruteur' ? 'Créer un compte recruteur' : 'Créer un compte candidat' ?>
                </p>
            </div>

            <?php if ($errors): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreurs détectées',
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
            <form method="post" novalidate class="space-y-6">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>" />

                <!-- Section: Localisation -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Localisation</h2>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <span class="text-sm text-gray-600">Saisie manuelle</span>
                            <div class="toggle-switch">
                                <input type="checkbox" id="toggle_location" onchange="toggleLocationMode()">
                                <span class="toggle-slider"></span>
                            </div>
                        </label>
                    </div>

                    <!-- Mode sélection -->
                    <div id="location_select_mode" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="pays" class="block mb-2 font-medium text-gray-900 text-sm">Pays</label>
                            <select id="pays" name="pays"
                                class="input-field w-full px-4 py-3.5 rounded-full text-base">
                                <option value="">Chargement...</option>
                            </select>
                            <input type="hidden" name="pays_nom" id="pays_nom" />
                        </div>
                        <div>
                            <label for="ville" class="block mb-2 font-medium text-gray-900 text-sm">Ville</label>
                            <select id="ville" name="ville" disabled
                                class="input-field w-full px-4 py-3.5 rounded-full text-base">
                                <option value="">Choisir un pays d'abord</option>
                            </select>
                        </div>
                    </div>

                    <!-- Mode saisie manuelle -->
                    <div id="location_manual_mode" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="pays_manual" class="block mb-2 font-medium text-gray-900 text-sm">Pays</label>
                            <input type="text" id="pays_manual" 
                                class="input-field w-full px-4 py-3.5 rounded-full text-base"
                                placeholder="Ex: France" />
                        </div>
                        <div>
                            <label for="ville_manual" class="block mb-2 font-medium text-gray-900 text-sm">Ville</label>
                            <input type="text" id="ville_manual"
                                class="input-field w-full px-4 py-3.5 rounded-full text-base"
                                placeholder="Ex: Paris" />
                        </div>
                    </div>
                </div>

                <!-- Section: Informations personnelles -->
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Informations personnelles</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="prenom" class="block mb-2 font-medium text-gray-900 text-sm">Prénom</label>
                            <input id="prenom" name="prenom" type="text" required
                                value="<?= clean_string($_POST['prenom'] ?? '') ?>"
                                class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                        </div>
                        <div>
                            <label for="nom" class="block mb-2 font-medium text-gray-900 text-sm">Nom</label>
                            <input id="nom" name="nom" type="text" required
                                value="<?= clean_string($_POST['nom'] ?? '') ?>"
                                class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                        </div>
                    </div>
                </div>

                <!-- Section: Informations professionnelles -->
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Informations professionnelles</h2>
                    
                    <?php if ($role === 'candidat'): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="fonction" class="block mb-2 font-medium text-gray-900 text-sm">Fonction</label>
                                <input id="fonction" name="fonction" type="text" required
                                    value="<?= clean_string($_POST['fonction'] ?? '') ?>"
                                    class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label for="delai_preavis" class="font-medium text-gray-900 text-sm">Préavis (mois)</label>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <span class="text-xs text-gray-500">Saisir</span>
                                        <div class="toggle-switch scale-75">
                                            <input type="checkbox" id="toggle_preavis" onchange="togglePreavisMode()">
                                            <span class="toggle-slider"></span>
                                        </div>
                                    </label>
                                </div>
                                <select id="delai_preavis" name="delai_preavis" required
                                    class="input-field w-full px-4 py-3.5 rounded-full text-base">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?> mois</option>
                                    <?php endfor; ?>
                                </select>
                                <input type="number" id="delai_preavis_manual" min="0" max="12"
                                    class="input-field w-full px-4 py-3.5 rounded-full text-base hidden"
                                    placeholder="Nombre de mois" />
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <div>
                                <label for="etablissement" class="block mb-2 font-medium text-gray-900 text-sm">Établissement</label>
                                <input id="etablissement" name="etablissement" type="text" required
                                    value="<?= clean_string($_POST['etablissement'] ?? '') ?>"
                                    class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                            </div>
                            <div>
                                <label for="fonction" class="block mb-2 font-medium text-gray-900 text-sm">Fonction</label>
                                <input id="fonction" name="fonction" type="text" required
                                    value="<?= clean_string($_POST['fonction'] ?? '') ?>"
                                    class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                            </div>

                            <!-- Localisation établissement -->
                            <div class="pt-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-semibold text-gray-900">Localisation établissement</h3>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <span class="text-sm text-gray-600">Saisie manuelle</span>
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="toggle_location_etab" onchange="toggleLocationEtabMode()">
                                            <span class="toggle-slider"></span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Mode sélection établissement -->
                                <div id="location_etab_select_mode" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pays_etablissement" class="block mb-2 font-medium text-gray-900 text-sm">Pays</label>
                                        <select id="pays_etablissement" name="pays_etablissement"
                                            class="input-field w-full px-4 py-3.5 rounded-full text-base">
                                            <option value="">Chargement...</option>
                                        </select>
                                        <input type="hidden" name="pays_etablissement_nom" id="pays_etablissement_nom" />
                                    </div>
                                    <div>
                                        <label for="ville_etablissement" class="block mb-2 font-medium text-gray-900 text-sm">Ville</label>
                                        <select id="ville_etablissement" name="ville_etablissement" disabled
                                            class="input-field w-full px-4 py-3.5 rounded-full text-base">
                                            <option value="">Choisir un pays d'abord</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mode saisie manuelle établissement -->
                                <div id="location_etab_manual_mode" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pays_etablissement_manual" class="block mb-2 font-medium text-gray-900 text-sm">Pays</label>
                                        <input type="text" id="pays_etablissement_manual"
                                            class="input-field w-full px-4 py-3.5 rounded-full text-base"
                                            placeholder="Ex: France" />
                                    </div>
                                    <div>
                                        <label for="ville_etablissement_manual" class="block mb-2 font-medium text-gray-900 text-sm">Ville</label>
                                        <input type="text" id="ville_etablissement_manual"
                                            class="input-field w-full px-4 py-3.5 rounded-full text-base"
                                            placeholder="Ex: Paris" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section: Connexion -->
                <div class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Connexion</h2>
                    <div>
                        <label for="email" class="block mb-2 font-medium text-gray-900 text-sm">Email</label>
                        <input id="email" name="email" type="email" required
                            value="<?= clean_string($_POST['email'] ?? '') ?>"
                            class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block mb-2 font-medium text-gray-900 text-sm">Mot de passe</label>
                            <input id="password" name="password" type="password" required
                                class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                        </div>
                        <div>
                            <label for="confirm_password" class="block mb-2 font-medium text-gray-900 text-sm">Confirmer</label>
                            <input id="confirm_password" name="confirm_password" type="password" required
                                class="input-field w-full px-4 py-3.5 rounded-full text-base" />
                        </div>
                    </div>
                </div>

                <!-- Bouton submit -->
                <button type="submit" class="btn-primary w-full text-white font-semibold py-3.5 rounded-full text-base shadow-lg">
                    Créer mon compte
                </button>

                <!-- Lien connexion -->
                <div class="text-center pt-4">
                    <p class="text-gray-600 text-sm mb-2">
                        Vous avez déjà un compte ?
                    </p>
                    <a href="login.php?role=<?= $role ?>" class="text-green-600 hover:text-green-700 font-semibold">
                        Se connecter
                    </a>
                </div>
            </form>
            
        </div>
    </div>

    <script>
        // Toggle modes
        function toggleLocationMode() {
            const checked = document.getElementById('toggle_location').checked;
            document.getElementById('location_select_mode').classList.toggle('hidden', checked);
            document.getElementById('location_manual_mode').classList.toggle('hidden', !checked);
            
            if (checked) {
                document.getElementById('pays').removeAttribute('name');
                document.getElementById('ville').removeAttribute('name');
                document.getElementById('pays_manual').setAttribute('name', 'pays_nom');
                document.getElementById('ville_manual').setAttribute('name', 'ville');
            } else {
                document.getElementById('pays').setAttribute('name', 'pays');
                document.getElementById('ville').setAttribute('name', 'ville');
                document.getElementById('pays_manual').removeAttribute('name');
                document.getElementById('ville_manual').removeAttribute('name');
            }
        }

        function toggleLocationEtabMode() {
            const checked = document.getElementById('toggle_location_etab').checked;
            document.getElementById('location_etab_select_mode').classList.toggle('hidden', checked);
            document.getElementById('location_etab_manual_mode').classList.toggle('hidden', !checked);
            
            if (checked) {
                document.getElementById('pays_etablissement').removeAttribute('name');
                document.getElementById('ville_etablissement').removeAttribute('name');
                document.getElementById('pays_etablissement_manual').setAttribute('name', 'pays_etablissement_nom');
                document.getElementById('ville_etablissement_manual').setAttribute('name', 'ville_etablissement');
            } else {
                document.getElementById('pays_etablissement').setAttribute('name', 'pays_etablissement');
                document.getElementById('ville_etablissement').setAttribute('name', 'ville_etablissement');
                document.getElementById('pays_etablissement_manual').removeAttribute('name');
                document.getElementById('ville_etablissement_manual').removeAttribute('name');
            }
        }

        function togglePreavisMode() {
            const checked = document.getElementById('toggle_preavis').checked;
            const selectEl = document.getElementById('delai_preavis');
            const manualEl = document.getElementById('delai_preavis_manual');
            
            selectEl.classList.toggle('hidden', checked);
            manualEl.classList.toggle('hidden', !checked);
            
            if (checked) {
                selectEl.removeAttribute('name');
                manualEl.setAttribute('name', 'delai_preavis');
            } else {
                selectEl.setAttribute('name', 'delai_preavis');
                manualEl.removeAttribute('name');
            }
        }

        // Load countries
        async function loadCountries() {
            try {
                const response = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2');
                const countries = await response.json();
                countries.sort((a, b) => a.name.common.localeCompare(b.name.common));
                
                let options = '<option value="">Choisir un pays</option>';
                countries.forEach(country => {
                    options += `<option value="${country.cca2}" data-nom="${country.name.common}">${country.name.common}</option>`;
                });
                
                document.getElementById('pays').innerHTML = options;
                const paysEtab = document.getElementById('pays_etablissement');
                if (paysEtab) paysEtab.innerHTML = options;
            } catch (e) {
                console.error('Erreur chargement pays:', e);
            }
        }

        // Load cities
        async function loadCities(countryCode, villeSelectId) {
            const villeSelect = document.getElementById(villeSelectId);
            villeSelect.innerHTML = '<option value="">Chargement...</option>';
            villeSelect.disabled = true;

            try {
                const url = `https://secure.geonames.org/searchJSON?country=${countryCode}&featureClass=P&maxRows=100&username=sunderr`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.geonames && data.geonames.length > 0) {
                    let options = '<option value="">Choisir une ville</option>';
                    data.geonames.forEach(city => {
                        options += `<option value="${city.name}">${city.name}</option>`;
                    });
                    villeSelect.innerHTML = options;
                    villeSelect.disabled = false;
                } else {
                    villeSelect.innerHTML = '<option value="">Aucune ville trouvée</option>';
                }
            } catch (e) {
                villeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                console.error(e);
            }
        }

        // Event listeners
        document.getElementById('pays').addEventListener('change', function() {
            const option = this.selectedOptions[0];
            document.getElementById('pays_nom').value = option ? option.dataset.nom : '';
            if (this.value) {
                loadCities(this.value, 'ville');
            }
        });

        const paysEtab = document.getElementById('pays_etablissement');
        if (paysEtab) {
            paysEtab.addEventListener('change', function() {
                const option = this.selectedOptions[0];
                document.getElementById('pays_etablissement_nom').value = option ? option.dataset.nom : '';
                if (this.value) {
                    loadCities(this.value, 'ville_etablissement');
                }
            });
        }

        // Init
        loadCountries();
    </script>

</body>
</html>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_auth.php';
?>