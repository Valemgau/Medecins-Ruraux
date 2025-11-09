<?php
require_once './includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($userRole !== 'recruteur') {
    die("Accès refusé");
}

$stmt = $pdo->prepare("SELECT u.*, r.* FROM users u JOIN recruteurs r ON u.id=r.id WHERE u.id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable");
}

$errors = [];

$alertMessages = [
    'register_success' => ['type' => 'success', 'text' => 'Compte créé avec succès !'],
    'update_success' => ['type' => 'success', 'text' => 'Mise à jour effectuée avec succès !'],
    'photo_success' => ['type' => 'success', 'text' => 'Photo mise à jour avec succès !'],
    'update_error' => ['type' => 'error', 'text' => 'Une erreur est survenue lors de la mise à jour.'],
    'photo_error' => ['type' => 'error', 'text' => 'Erreur lors de l\'upload de la photo.'],
];

$alertKey = $_GET['message'] ?? '';
$alert = $alertMessages[$alertKey] ?? null;

// Upload photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array($ext, $allowedExts)) {
            header("Location: dashboard-recruteur.php?message=photo_error");
            exit;
        }

        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['photo']['size'] > $maxSize) {
            header("Location: dashboard-recruteur.php?message=photo_error");
            exit;
        }

        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Supprimer les anciennes photos
        if (!empty($user['photo']) && file_exists($uploadDir . $user['photo'])) {
            unlink($uploadDir . $user['photo']);
        }
        if (!empty($user['photo_blurred']) && file_exists($uploadDir . $user['photo_blurred'])) {
            unlink($uploadDir . $user['photo_blurred']);
        }

        // ✅ Générer des UUID uniques et imprévisibles
        $originalUuid = bin2hex(random_bytes(16));
        $blurredUuid = bin2hex(random_bytes(16));
        
        $filename = "{$originalUuid}.{$ext}";
        $blurredFilename = "{$blurredUuid}.{$ext}";
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
            $originalPath = $uploadDir . $filename;
            $blurredPath = $uploadDir . $blurredFilename;
            
            // Charger l'image
            $image = match($ext) {
                'png' => imagecreatefrompng($originalPath),
                'webp' => imagecreatefromwebp($originalPath),
                default => imagecreatefromjpeg($originalPath)
            };
            
            // ✅ CORRIGER L'ORIENTATION EXIF
            if (in_array($ext, ['jpg', 'jpeg'])) {
                $exif = @exif_read_data($originalPath);
                if ($exif && isset($exif['Orientation'])) {
                    $image = match($exif['Orientation']) {
                        3 => imagerotate($image, 180, 0),
                        6 => imagerotate($image, -90, 0),
                        8 => imagerotate($image, 90, 0),
                        default => $image
                    };
                }
            }
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Technique de flou extrême : réduction puis agrandissement
            $tinyWidth = max(1, (int)($width * 0.02));
            $tinyHeight = max(1, (int)($height * 0.02));
            
            $tiny = imagecreatetruecolor($tinyWidth, $tinyHeight);
            
            // Préserver la transparence pour les PNG
            if ($ext === 'png') {
                imagealphablending($tiny, false);
                imagesavealpha($tiny, true);
                imagealphablending($image, true);
            }
            
            // Réduire drastiquement
            imagecopyresampled($tiny, $image, 0, 0, 0, 0, $tinyWidth, $tinyHeight, $width, $height);
            
            // Recréer l'image à la taille originale
            $blurred = imagecreatetruecolor($width, $height);
            
            if ($ext === 'png') {
                imagealphablending($blurred, false);
                imagesavealpha($blurred, true);
            }
            
            // Agrandir (cela crée un flou massif)
            imagecopyresampled($blurred, $tiny, 0, 0, 0, 0, $width, $height, $tinyWidth, $tinyHeight);
            
            // Ajouter un flou gaussien supplémentaire pour lisser les pixels
            for($i = 0; $i < 3; $i++) {
                imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
            }
            
            // Sauvegarder
            match($ext) {
                'png' => imagepng($blurred, $blurredPath),
                'webp' => imagewebp($blurred, $blurredPath, 85),
                default => imagejpeg($blurred, $blurredPath, 85)
            };
            
            imagedestroy($image);
            imagedestroy($tiny);
            imagedestroy($blurred);
            
            $pdo->prepare("UPDATE recruteurs SET photo=?, photo_blurred=? WHERE id=?")->execute([$filename, $blurredFilename, $userId]);
            header("Location: dashboard-recruteur.php?message=photo_success");
            exit;
        }
    }
    header("Location: dashboard-recruteur.php?message=photo_error");
    exit;
}


// Mise à jour profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['photo'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $telephoneIndicatif = trim($_POST['telephone_indicatif'] ?? '');
    $telephoneNum = trim($_POST['telephone'] ?? '');
    $etablissement = trim($_POST['etablissement'] ?? '');
    $fonction = trim($_POST['fonction'] ?? '');
    $pays = trim($_POST['pays'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $pays_etablissement = trim($_POST['pays_etablissement'] ?? '');
    $ville_etablissement = trim($_POST['ville_etablissement'] ?? '');

    if (!$prenom || !$nom) {
        $errors[] = "Le prénom et le nom sont obligatoires.";
    }

    // Validation indicatif téléphonique (doit commencer par +)
    if ($telephoneIndicatif && !preg_match('/^\+\d+$/', $telephoneIndicatif)) {
        $errors[] = "L'indicatif téléphonique doit être au format +XXX";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $sql = "UPDATE recruteurs SET 
                    prenom=?, nom=?, telephone_indicatif=?, telephone=?, 
                    etablissement=?, fonction=?, ville=?, pays=?, 
                    ville_etablissement=?, pays_etablissement=? 
                    WHERE id=?";

            $pdo->prepare($sql)->execute([
                $prenom,
                $nom,
                $telephoneIndicatif,
                $telephoneNum,
                $etablissement,
                $fonction,
                $ville,
                $pays,
                $ville_etablissement,
                $pays_etablissement,
                $userId
            ]);

            $pdo->commit();
            header("Location: dashboard-recruteur.php?message=update_success");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur update recruteur: " . $e->getMessage());
            header("Location: dashboard-recruteur.php?message=update_error");
            exit;
        }
    }
}

// Récupération infos abonnement Stripe
$stripeSubscriptionId = $user['stripe_subscription_id'] ?? null;
$isStripeSubscriber = false;
$stripeStatus = null;
$currentPriceName = "";

if ($stripeSubscriptionId) {
    try {
        $subscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
        $stripeStatus = $subscription->status;
        $isStripeSubscriber = in_array($stripeStatus, ['active', 'trialing']);

        if ($isStripeSubscriber && !empty($subscription->items->data)) {
            $item = $subscription->items->data[0];
            $currentPriceName = $item->price->nickname ?: "Abonnement";
        }
    } catch (Exception $e) {
        error_log("Erreur Stripe: " . $e->getMessage());
        $isStripeSubscriber = false;
    }
}

$currentPriceNameDisplay = htmlspecialchars($currentPriceName);

if ($isStripeSubscriber && $currentPriceName === 'Essentielle') {
    $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
    $stmtCredits->execute([$userId]);
    $creditsRestants = $stmtCredits->fetchColumn();
    $creditsRestants = $creditsRestants !== false ? (int) $creditsRestants : 0;
    $currentPriceNameDisplay .= " (" . $creditsRestants . " CV consultable" . ($creditsRestants > 1 ? "s" : "") . " restant" . ($creditsRestants > 1 ? "s" : "") . ")";
}

$title = "Tableau de bord";
ob_start();
?>

<style>
    .subscription-banner {
        animation: slideDown 0.5s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .subscription-banner.closing {
        animation: slideUp 0.3s ease-out forwards;
    }

    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }

        to {
            opacity: 0;
            transform: translateY(-20px);
        }
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

    input:checked+.toggle-slider {
        background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
    }

    input:checked+.toggle-slider:before {
        transform: translateX(24px);
    }
</style>

<div class="min-h-screen bg-gray-100 py-8 px-4 sm:px-6 lg:px-8">

    <?php if ($alert): ?>
        <div class="max-w-7xl mx-auto mb-6">
            <div
                class="<?= $alert['type'] === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-800' : 'bg-red-50 border-l-4 border-red-500 text-red-800' ?> p-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <i
                        class="fas <?= $alert['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                    <span><?= htmlspecialchars($alert['text']) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bannière d'abonnement (si non abonné) -->
    <?php if (!$isStripeSubscriber): ?>
        <div id="subscriptionBanner" class="max-w-7xl mx-auto mb-6 subscription-banner">
            <div
                class="bg-gradient-to-r from-green-500 via-green-600 to-green-700 rounded-2xl shadow-2xl overflow-hidden relative">
                <button onclick="closeBanner()"
                    class="absolute top-4 right-4 text-white/80 hover:text-white transition z-10">
                    <i class="fas fa-times text-xl"></i>
                </button>

                <div class="p-6 md:p-8 flex flex-col md:flex-row items-center gap-6">
                    <div class="flex-shrink-0">
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fas fa-crown text-4xl text-yellow-300"></i>
                        </div>
                    </div>

                    <div class="flex-grow text-center md:text-left text-white">
                        <h3 class="text-xl font-bold mb-2">Accédez à tous les candidats !</h3>
                        <p class="text-green-50 text-base">Débloquez l'accès illimité aux profils des candidats et trouvez
                            la perle rare pour votre établissement.</p>
                    </div>

                    <div class="flex-shrink-0">
                        <a href="abonnement.php"
                            class="inline-flex items-center gap-3 bg-white text-green-600 px-8 py-4 rounded-xl font-bold text-base hover:bg-green-50 transition-all shadow-lg hover:shadow-xl hover:scale-105">
                            <span>Voir les offres</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

            <!-- Carte avec dégradé -->
            <div class="relative h-48 bg-gradient-to-r from-green-400 via-green-500 to-green-600">
                <div id="map" class="absolute inset-0 opacity-60"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
            </div>

            <!-- Section profil -->
            <div class="relative px-6 pb-6">
                <div class="flex flex-col md:flex-row md:items-end md:space-x-8 -mt-20 md:mt-10">

                    <!-- Avatar -->
                    <div class="relative flex-shrink-0 z-10 mx-auto md:mx-0">
                        <div class="relative group">
                            <?php if (!empty($user['photo'])): ?>
                                <img src="uploads/<?= htmlspecialchars($user['photo']) ?>?v=<?= time() ?>" alt="Avatar"
                                    class="h-40 w-40 rounded-full border border-gray-200 object-cover shadow-2xl">
                            <?php else: ?>
                                <div
                                    class="h-40 w-40 rounded-full border border-gray-200 bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center shadow-2xl">
                                    <i class="fas fa-user-md text-5xl text-white"></i>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" class="absolute bottom-2 right-2">
                                <label
                                    class="cursor-pointer bg-green-500 hover:bg-green-600 text-white p-2.5 rounded-full shadow-lg transition-all hover:scale-105 flex items-center justify-center">
                                    <i class="fas fa-camera text-sm"></i>
                                    <input type="file" name="photo" accept="image/png,image/jpeg,image/jpg,image/webp"
                                        class="hidden" onchange="this.form.submit()" />
                                </label>
                            </form>
                        </div>
                    </div>

                    <!-- Infos utilisateur -->
                    <div class="flex-grow mt-6 md:mt-0 text-center md:text-left">
                        <div
                            class="flex flex-col md:flex-row items-center md:items-start justify-between flex-wrap gap-4">
                            <div>
                                <h1
                                    class="text-3xl font-bold text-gray-900 flex flex-col md:flex-row items-center gap-3">
                                    <span><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></span>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <?= htmlspecialchars($user['numero_reference']) ?>
                                    </span>
                                </h1>
                                <p class="text-gray-600 mt-2 flex items-center gap-2 justify-center md:justify-start">
                                    <i class="fas fa-envelope text-sm"></i>
                                    <?= htmlspecialchars($user['email']) ?>
                                </p>
                                <p
                                    class="text-gray-500 mt-1 text-sm flex items-center gap-2 justify-center md:justify-start">
                                    <i class="fas fa-calendar-alt text-sm"></i>
                                    Inscrit depuis le <?= date("d/m/Y", strtotime($user['created_at'])) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Badge abonnement -->
                        <div class="mt-4 flex justify-center md:justify-start">
                            <?php if ($isStripeSubscriber): ?>
                                <div
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl">
                                    <i class="fas fa-crown text-green-600"></i>
                                    <span class="text-sm font-semibold text-gray-700">Formule :</span>
                                    <span class="text-green-600 font-bold"><?= $currentPriceNameDisplay ?></span>
                                </div>
                            <?php else: ?>
                                <a href="abonnement.php"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl hover:bg-gray-100 transition">
                                    <i class="fas fa-info-circle text-gray-500"></i>
                                    <span class="text-sm text-gray-600">Aucun abonnement actif</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-8 pt-8 border-t border-gray-200">
                    <a href="index.php"
                        class="group flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-6 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg hover:scale-[1.02]">
                        <i class="fas fa-users"></i>
                        <span>Liste candidats</span>
                    </a>

                    <?php if ($isStripeSubscriber): ?>
                        <a href="tarifs.php"
                            class="group flex items-center justify-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-6 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg hover:scale-[1.02]">
                            <i class="fas fa-crown"></i>
                            <span>Voir les tarifs</span>
                        </a>

                        <a href="resilier.php"
                            class="group flex items-center justify-center gap-2 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white py-3 px-6 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg hover:scale-[1.02]">
                            <i class="fas fa-times-circle"></i>
                            <span>Résilier abonnement</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de modification -->
                <div class="mt-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-edit text-green-600"></i>
                        Modifier mes informations
                    </h2>

                    <form method="post" class="space-y-8">

                        <!-- Informations personnelles -->
                        <div
                            class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-user text-green-600"></i>
                                Informations personnelles
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="email"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                    <div class="relative">
                                        <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>"
                                            disabled
                                            class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-600 cursor-not-allowed">
                                        <i
                                            class="fas fa-lock absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                </div>

                                <div>
                                    <label for="prenom" class="block text-sm font-semibold text-gray-700 mb-2">Prénom
                                        *</label>
                                    <input type="text" id="prenom" name="prenom"
                                        value="<?= htmlspecialchars($user['prenom']) ?>" required
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>

                                <div>
                                    <label for="nom" class="block text-sm font-semibold text-gray-700 mb-2">Nom
                                        *</label>
                                    <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>"
                                        required
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>

                                <div class="md:col-span-2">
                                    <div class="flex items-center justify-between mb-2">
                                        <label for="telephone"
                                            class="block text-sm font-semibold text-gray-700">Téléphone</label>
                                        <label class="flex items-center space-x-2 cursor-pointer">
                                            <span class="text-xs text-gray-500">Saisie manuelle</span>
                                            <div class="toggle-switch">
                                                <input type="checkbox" id="toggle_indicatif"
                                                    onchange="toggleIndicatifMode()">
                                                <span class="toggle-slider"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3">
                                        <select id="telephone_indicatif_select" name="telephone_indicatif"
                                            class="col-span-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                            <option value="" disabled selected>Indicatif</option>
                                        </select>
                                        <input type="text" id="telephone_indicatif_manual" placeholder="Ex: +33"
                                            pattern="^\+\d+$"
                                            class="col-span-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition hidden">
                                        <input type="tel" id="telephone" name="telephone"
                                            value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                                            class="col-span-2 px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Localisation personnelle -->
                        <div
                            class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-map-marker-alt text-green-600"></i>
                                    Localisation personnelle
                                </h3>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <span class="text-xs text-gray-500">Saisie manuelle</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="toggle_location" onchange="toggleLocationMode()">
                                        <span class="toggle-slider"></span>
                                    </div>
                                </label>
                            </div>

                            <div id="location_select_mode" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="pays"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Pays</label>
                                    <select id="pays" name="pays" required
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <option value="" disabled>Chargement...</option>
                                    </select>
                                    <input type="hidden" id="pays_nom" name="pays_nom"
                                        value="<?= htmlspecialchars($user['pays'] ?? '') ?>">
                                </div>

                                <div>
                                    <label for="ville"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                                    <select id="ville" name="ville" required
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <?php if (!empty($user['ville'])): ?>
                                            <option value="<?= htmlspecialchars($user['ville']) ?>" selected>
                                                <?= htmlspecialchars($user['ville']) ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="" disabled selected>Choisir une ville</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="location_manual_mode" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="pays_manual"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Pays</label>
                                    <input type="text" id="pays_manual" placeholder="Ex: France"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>

                                <div>
                                    <label for="ville_manual"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                                    <input type="text" id="ville_manual" placeholder="Ex: Paris"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>
                            </div>
                        </div>

                        <!-- Informations professionnelles -->
                        <div
                            class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-briefcase text-green-600"></i>
                                Informations professionnelles
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="etablissement"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Établissement</label>
                                    <input type="text" id="etablissement" name="etablissement"
                                        value="<?= htmlspecialchars($user['etablissement']) ?>"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>

                                <div>
                                    <label for="fonction"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Fonction</label>
                                    <input type="text" id="fonction" name="fonction"
                                        value="<?= htmlspecialchars($user['fonction']) ?>"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>
                            </div>
                        </div>

                        <!-- Localisation établissement -->
                        <div
                            class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-hospital text-green-600"></i>
                                    Localisation établissement
                                </h3>
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <span class="text-xs text-gray-500">Saisie manuelle</span>
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="toggle_location_etab"
                                            onchange="toggleLocationEtabMode()">
                                        <span class="toggle-slider"></span>
                                    </div>
                                </label>
                            </div>

                            <div id="location_etab_select_mode" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="pays_etablissement"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Pays</label>
                                    <select id="pays_etablissement" name="pays_etablissement" required
                                        class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <option value="" disabled>Chargement...</option>
                                    </select>
                                    <input type="hidden" id="pays_etablissement_nom" name="pays_etablissement_nom"
                                        value="<?= htmlspecialchars($user['pays_etablissement'] ?? '') ?>">
                                </div>

                                <div>
                                    <label for="ville_etablissement"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                                    <select id="ville_etablissement" name="ville_etablissement" required
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                        <?php if (!empty($user['ville_etablissement'])): ?>
                                            <option value="<?= htmlspecialchars($user['ville_etablissement']) ?>" selected>
                                                <?= htmlspecialchars($user['ville_etablissement']) ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="" disabled selected>Choisir une ville</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="location_etab_manual_mode" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="pays_etablissement_manual"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Pays</label>
                                    <input type="text" id="pays_etablissement_manual" placeholder="Ex: France"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>

                                <div>
                                    <label for="ville_etablissement_manual"
                                        class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                                    <input type="text" id="ville_etablissement_manual" placeholder="Ex: Paris"
                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-full focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                </div>
                            </div>
                        </div>

                        <!-- Bouton submit -->
                        <div class="flex justify-end pt-4">
                            <button type="submit"
                                class="group flex items-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 px-8 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg hover:scale-[1.02]">
                                <i class="fas fa-save"></i>
                                <span>Enregistrer les modifications</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Zone danger (suppression compte) -->
                <div class="mt-12 pt-8 border-t-2 border-red-200">
                    <details class="group">
                        <summary class="cursor-pointer list-none">
                            <div
                                class="flex items-center justify-between p-4 bg-red-50 rounded-lg border border-red-200 hover:bg-red-100 transition">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                                    <span class="font-semibold text-red-900">Zone dangereuse</span>
                                </div>
                                <i
                                    class="fas fa-chevron-down text-red-600 transition-transform group-open:rotate-180"></i>
                            </div>
                        </summary>
                        <div class="mt-4 p-6 bg-red-50 rounded-lg border border-red-200">
                            <h3 class="text-lg font-bold text-red-900 mb-2">Supprimer mon compte</h3>
                            <p class="text-red-700 mb-4">Cette action est irréversible. Toutes vos données seront
                                définitivement supprimées.</p>
                            <a href="supprimer_compte.php"
                                class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white py-3 px-6 rounded-lg font-semibold transition-all">
                                <i class="fas fa-trash-alt"></i>
                                <span>Supprimer définitivement mon compte</span>
                            </a>
                        </div>
                    </details>
                </div>

            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // ==========================================
    // VARIABLES GLOBALES
    // ==========================================
    let allCountries = [];
    let mapInstance = null;
    let isAppInitialized = false;

    // ==========================================
    // CONSTANTES DE CACHE
    // ==========================================
    const CACHE_DURATION_COUNTRIES = 86400000; // 24 heures
    const CACHE_DURATION_CITIES = 604800000; // 7 jours
    const CACHE_DURATION_INDICATIFS = 86400000; // 24 heures

    // ==========================================
    // FONCTION FERMETURE BANNIÈRE
    // ==========================================
    function closeBanner() {
        const banner = document.getElementById('subscriptionBanner');
        if (banner) {
            banner.classList.add('closing');
            setTimeout(() => banner.remove(), 300);
        }
    }

    // ==========================================
    // TOGGLE INDICATIF TÉLÉPHONIQUE
    // ==========================================
    function toggleIndicatifMode() {
        const checked = document.getElementById('toggle_indicatif').checked;
        const selectEl = document.getElementById('telephone_indicatif_select');
        const manualEl = document.getElementById('telephone_indicatif_manual');

        selectEl.classList.toggle('hidden', checked);
        manualEl.classList.toggle('hidden', !checked);

        if (checked) {
            selectEl.removeAttribute('name');
            selectEl.required = false;
            manualEl.setAttribute('name', 'telephone_indicatif');
            manualEl.required = true;
            
            if (!manualEl.value.startsWith('+')) {
                manualEl.value = '+' + manualEl.value.replace(/\+/g, '');
            }
        } else {
            selectEl.setAttribute('name', 'telephone_indicatif');
            selectEl.required = true;
            manualEl.removeAttribute('name');
            manualEl.required = false;
        }
    }

    // ==========================================
    // TOGGLE LOCALISATION
    // ==========================================
    function toggleLocationMode() {
        const checked = document.getElementById('toggle_location').checked;
        document.getElementById('location_select_mode').classList.toggle('hidden', checked);
        document.getElementById('location_manual_mode').classList.toggle('hidden', !checked);

        if (checked) {
            document.getElementById('pays').removeAttribute('name');
            document.getElementById('pays').required = false;
            document.getElementById('ville').removeAttribute('name');
            document.getElementById('ville').required = false;
            document.getElementById('pays_manual').setAttribute('name', 'pays');
            document.getElementById('pays_manual').required = true;
            document.getElementById('ville_manual').setAttribute('name', 'ville');
            document.getElementById('ville_manual').required = true;
        } else {
            document.getElementById('pays').setAttribute('name', 'pays');
            document.getElementById('pays').required = true;
            document.getElementById('ville').setAttribute('name', 'ville');
            document.getElementById('ville').required = true;
            document.getElementById('pays_manual').removeAttribute('name');
            document.getElementById('pays_manual').required = false;
            document.getElementById('ville_manual').removeAttribute('name');
            document.getElementById('ville_manual').required = false;
        }
    }

    // ==========================================
    // TOGGLE LOCALISATION ÉTABLISSEMENT
    // ==========================================
    function toggleLocationEtabMode() {
        const checked = document.getElementById('toggle_location_etab').checked;
        document.getElementById('location_etab_select_mode').classList.toggle('hidden', checked);
        document.getElementById('location_etab_manual_mode').classList.toggle('hidden', !checked);

        if (checked) {
            document.getElementById('pays_etablissement').removeAttribute('name');
            document.getElementById('pays_etablissement').required = false;
            document.getElementById('ville_etablissement').removeAttribute('name');
            document.getElementById('ville_etablissement').required = false;
            document.getElementById('pays_etablissement_manual').setAttribute('name', 'pays_etablissement');
            document.getElementById('pays_etablissement_manual').required = true;
            document.getElementById('ville_etablissement_manual').setAttribute('name', 'ville_etablissement');
            document.getElementById('ville_etablissement_manual').required = true;
        } else {
            document.getElementById('pays_etablissement').setAttribute('name', 'pays_etablissement');
            document.getElementById('pays_etablissement').required = true;
            document.getElementById('ville_etablissement').setAttribute('name', 'ville_etablissement');
            document.getElementById('ville_etablissement').required = true;
            document.getElementById('pays_etablissement_manual').removeAttribute('name');
            document.getElementById('pays_etablissement_manual').required = false;
            document.getElementById('ville_etablissement_manual').removeAttribute('name');
            document.getElementById('ville_etablissement_manual').required = false;
        }
    }

    // ==========================================
    // CHARGEMENT DES INDICATIFS AVEC CACHE
    // ==========================================
    async function loadIndicatifs() {
        const select = document.getElementById('telephone_indicatif_select');
        if (!select) return;

        select.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';

        const cached = localStorage.getItem('indicatifs_cache');
        const cacheTime = localStorage.getItem('indicatifs_cache_time');
        const now = Date.now();

        if (cached && cacheTime && (now - parseInt(cacheTime)) < CACHE_DURATION_INDICATIFS) {
            const countries = JSON.parse(cached);
            renderIndicatifs(select, countries);
            setupIndicatifManualInput();
            return;
        }

        try {
            const response = await fetch('https://restcountries.com/v3.1/all?fields=idd,name', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const countries = await response.json();
            countries.sort((a, b) => a.name.common.localeCompare(b.name.common));

            localStorage.setItem('indicatifs_cache', JSON.stringify(countries));
            localStorage.setItem('indicatifs_cache_time', now.toString());

            renderIndicatifs(select, countries);
            setupIndicatifManualInput();

        } catch (error) {
            console.error('❌ Erreur chargement indicatifs:', error);
            select.innerHTML = '<option value="" disabled>❌ Erreur - Cliquez pour réessayer</option>';
            select.addEventListener('click', () => {
                localStorage.removeItem('indicatifs_cache');
                localStorage.removeItem('indicatifs_cache_time');
                loadIndicatifs();
            }, { once: true });
        }
    }

    function renderIndicatifs(select, countries) {
        let options = '<option value="" disabled selected>Choisir un indicatif</option>';
        countries.forEach(country => {
            if (country.idd?.root) {
                const callingCode = country.idd.root + (country.idd.suffixes?.[0] || '');
                options += `<option value="${callingCode}">${country.name.common} (${callingCode})</option>`;
            }
        });
        select.innerHTML = options;

        const currentIndicatif = '<?= addslashes($user['telephone_indicatif'] ?? '') ?>';
        if (currentIndicatif) {
            select.value = currentIndicatif;
            
            // Si l'indicatif n'est pas trouvé dans la liste, activer le mode manuel
            if (!select.value && currentIndicatif) {
                const toggle = document.getElementById('toggle_indicatif');
                const manualInput = document.getElementById('telephone_indicatif_manual');
                
                if (toggle && !toggle.checked) {
                    toggle.checked = true;
                    toggleIndicatifMode();
                }
                
                if (manualInput) {
                    manualInput.value = currentIndicatif.startsWith('+') ? currentIndicatif : '+' + currentIndicatif;
                }
            }
        }
    }

    function setupIndicatifManualInput() {
        const manualInput = document.getElementById('telephone_indicatif_manual');
        if (!manualInput) return;

        if (manualInput.value && !manualInput.value.startsWith('+')) {
            manualInput.value = '+' + manualInput.value.replace(/\+/g, '');
        }

        manualInput.addEventListener('input', function(e) {
            let value = e.target.value;
            if (!value.startsWith('+')) {
                value = '+' + value.replace(/\+/g, '');
            }
            const numbers = value.substring(1).replace(/\D/g, '');
            e.target.value = '+' + numbers.substring(0, 4);
        });

        manualInput.addEventListener('keydown', function(e) {
            if ((e.key === 'Backspace' || e.key === 'Delete') && e.target.selectionStart <= 1) {
                e.preventDefault();
            }
        });

        manualInput.addEventListener('focus', function(e) {
            if (!e.target.value) {
                e.target.value = '+';
            }
        });
    }

    // ==========================================
    // CHARGEMENT DES PAYS AVEC CACHE
    // ==========================================
    async function loadCountries() {
        const paysSelects = [
            document.getElementById('pays'),
            document.getElementById('pays_etablissement')
        ].filter(x => x);

        if (paysSelects.length === 0) return;

        paysSelects.forEach(select => {
            select.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';
            select.disabled = true;
        });

        const cached = localStorage.getItem('countries_cache');
        const cacheTime = localStorage.getItem('countries_cache_time');
        const now = Date.now();

        if (cached && cacheTime && (now - parseInt(cacheTime)) < CACHE_DURATION_COUNTRIES) {
            allCountries = JSON.parse(cached);
            renderCountryOptions(paysSelects);
            return;
        }

        try {
            const response = await fetch('https://restcountries.com/v3.1/all?fields=name,translations,cca2', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const countries = await response.json();
            allCountries = countries.sort((a, b) =>
                a.translations.fra.common.localeCompare(b.translations.fra.common)
            );

            localStorage.setItem('countries_cache', JSON.stringify(allCountries));
            localStorage.setItem('countries_cache_time', now.toString());

            renderCountryOptions(paysSelects);

        } catch (error) {
            console.error('❌ Erreur lors du chargement des pays:', error);
            paysSelects.forEach(select => {
                select.innerHTML = '<option value="" disabled>❌ Erreur - Cliquez pour réessayer</option>';
                select.disabled = false;

                select.addEventListener('click', () => {
                    localStorage.removeItem('countries_cache');
                    localStorage.removeItem('countries_cache_time');
                    loadCountries();
                }, { once: true });
            });
        }
    }

    function renderCountryOptions(paysSelects) {
        let options = '<option value="" disabled>Choisir un pays</option>';

        allCountries.forEach(country => {
            const nomFr = country.translations.fra.common;
            options += `<option value="${nomFr}" data-nom="${nomFr}" data-iso2="${country.cca2}">${nomFr}</option>`;
        });

        paysSelects.forEach(select => {
            select.innerHTML = options;
            select.disabled = false;
        });

        const currentPays = '<?= addslashes($user['pays'] ?? '') ?>';
        const currentVille = '<?= addslashes($user['ville'] ?? '') ?>';
        const currentPaysEtab = '<?= addslashes($user['pays_etablissement'] ?? '') ?>';
        const currentVilleEtab = '<?= addslashes($user['ville_etablissement'] ?? '') ?>';

        // Gestion localisation utilisateur
        if (currentPays && document.getElementById('pays')) {
            const paysSelect = document.getElementById('pays');
            const paysManual = document.getElementById('pays_manual');
            const villeManual = document.getElementById('ville_manual');
            const paysExists = allCountries.some(c => c.translations.fra.common === currentPays);
            
            if (!paysExists && currentPays) {
                // Pays personnalisé → activer mode manuel
                const toggle = document.getElementById('toggle_location');
                if (toggle && !toggle.checked) {
                    toggle.checked = true;
                    toggleLocationMode();
                }
                if (paysManual) paysManual.value = currentPays;
                if (villeManual) villeManual.value = currentVille;
            } else {
                // Pays trouvé dans l'API → mode select
                paysSelect.value = currentPays;
                if (paysSelect.value) {
                    updateCities('pays', 'ville');
                }
            }
        }

        // Gestion localisation établissement
        if (currentPaysEtab && document.getElementById('pays_etablissement')) {
            const paysEtabSelect = document.getElementById('pays_etablissement');
            const paysEtabManual = document.getElementById('pays_etablissement_manual');
            const villeEtabManual = document.getElementById('ville_etablissement_manual');
            const paysEtabExists = allCountries.some(c => c.translations.fra.common === currentPaysEtab);
            
            if (!paysEtabExists && currentPaysEtab) {
                // Pays personnalisé → activer mode manuel
                const toggle = document.getElementById('toggle_location_etab');
                if (toggle && !toggle.checked) {
                    toggle.checked = true;
                    toggleLocationEtabMode();
                }
                if (paysEtabManual) paysEtabManual.value = currentPaysEtab;
                if (villeEtabManual) villeEtabManual.value = currentVilleEtab;
            } else {
                // Pays trouvé dans l'API → mode select
                paysEtabSelect.value = currentPaysEtab;
                if (paysEtabSelect.value) {
                    updateCities('pays_etablissement', 'ville_etablissement');
                }
            }
        }
    }

    // ==========================================
    // MISE À JOUR DES VILLES AVEC CACHE
    // ==========================================
    async function updateCities(selectPaysId, selectVilleId) {
        const selectPays = document.getElementById(selectPaysId);
        const villeSelect = document.getElementById(selectVilleId);
        
        if (!selectPays || !villeSelect) return;

        const selectedOption = selectPays.selectedOptions[0];
        const countryCode = selectedOption?.dataset.iso2 || '';
        const countryName = selectedOption?.dataset.nom || '';

        villeSelect.innerHTML = '<option value="" disabled selected>Chargement des villes...</option>';
        villeSelect.disabled = true;

        if (!countryCode) {
            villeSelect.innerHTML = '<option value="" disabled>Choisir un pays d\'abord</option>';
            return;
        }

        const cacheKey = `cities_${countryCode}`;
        const cached = localStorage.getItem(cacheKey);
        const cacheTime = localStorage.getItem(`${cacheKey}_time`);
        const now = Date.now();

        if (cached && cacheTime && (now - parseInt(cacheTime)) < CACHE_DURATION_CITIES) {
            const cities = JSON.parse(cached);
            renderCityOptions(villeSelect, cities, selectPaysId);
            return;
        }

        try {
            const username = 'sunderr';
            const url = `https://secure.geonames.org/searchJSON?country=${countryCode}&featureClass=P&maxRows=100&lang=fr&orderby=population&username=${username}`;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Erreur GeoNames: ' + response.status);
            }

            const data = await response.json();

            if (!data.geonames || data.geonames.length === 0) {
                villeSelect.innerHTML = '<option value="" disabled>Aucune ville trouvée</option>';
                villeSelect.disabled = false;
                return;
            }

            localStorage.setItem(cacheKey, JSON.stringify(data.geonames));
            localStorage.setItem(`${cacheKey}_time`, now.toString());

            renderCityOptions(villeSelect, data.geonames, selectPaysId);

        } catch (error) {
            console.error('❌ Erreur lors du chargement des villes:', error);
            villeSelect.innerHTML = '<option value="" disabled>Erreur de chargement - Réessayez</option>';
            villeSelect.disabled = false;
        }
    }

    function renderCityOptions(villeSelect, cities, selectPaysId) {
        villeSelect.innerHTML = '<option value="" disabled>Choisir une ville</option>';

        const currentVille = selectPaysId === 'pays' 
            ? '<?= addslashes($user['ville'] ?? '') ?>'
            : '<?= addslashes($user['ville_etablissement'] ?? '') ?>';
            
        const currentPays = selectPaysId === 'pays'
            ? '<?= addslashes($user['pays'] ?? '') ?>'
            : '<?= addslashes($user['pays_etablissement'] ?? '') ?>';
            
        const selectedPays = document.getElementById(selectPaysId)?.value;
        
        let villeFound = false;

        cities.forEach(city => {
            const selected = city.name === currentVille ? 'selected' : '';
            if (selected) villeFound = true;
            villeSelect.insertAdjacentHTML('beforeend', `<option value="${city.name}" ${selected}>${city.name}</option>`);
        });

        villeSelect.disabled = false;

        if (currentVille && !villeFound && selectedPays === currentPays) {
            const toggleId = selectPaysId === 'pays' ? 'toggle_location' : 'toggle_location_etab';
            const toggle = document.getElementById(toggleId);
            
            if (toggle && !toggle.checked) {
                toggle.checked = true;
                if (selectPaysId === 'pays') {
                    toggleLocationMode();
                } else {
                    toggleLocationEtabMode();
                }
            }
        } else if (!villeFound && selectedPays !== currentPays) {
            villeSelect.value = '';
        }
    }

    // ==========================================
    // MISE À JOUR DU NOM DU PAYS
    // ==========================================
    function updatePaysNom(selectId, inputId) {
        const selectPays = document.getElementById(selectId);
        const inputPaysNom = document.getElementById(inputId);
        
        if (!selectPays || !inputPaysNom) return;

        const option = selectPays.selectedOptions[0];
        if (option) {
            inputPaysNom.value = option.dataset.nom;
        }
    }

    // ==========================================
    // INITIALISATION DE LA CARTE
    // ==========================================
    async function initMap() {
        const ville = '<?= addslashes($user['ville'] ?? '') ?>';
        const pays = '<?= addslashes($user['pays'] ?? '') ?>';
        const mapContainer = document.getElementById('map');

        if (!mapContainer) {
            console.warn('⚠️ Conteneur de carte introuvable');
            return;
        }

        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }

        mapContainer.innerHTML = '';
        mapContainer.style.height = '';

        if (!ville && !pays) {
            return;
        }

        const adresse = `${ville}, ${pays}`;

        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(adresse)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!data.length) return;

            const lat = parseFloat(data[0].lat);
            const lon = parseFloat(data[0].lon);

            mapInstance = L.map('map', {
                zoomControl: false,
                attributionControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false
            }).setView([lat, lon], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(mapInstance);

            const customIcon = L.divIcon({
                html: '<i class="fas fa-map-marker-alt text-3xl text-white drop-shadow-lg"></i>',
                className: 'custom-marker',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });

            L.marker([lat, lon], { icon: customIcon }).addTo(mapInstance);

        } catch (error) {
            console.error('❌ Erreur chargement carte:', error);
        }
    }

    // ==========================================
    // FONCTION UTILITAIRE : VIDER LE CACHE
    // ==========================================
    window.clearAllCache = function() {
        localStorage.removeItem('countries_cache');
        localStorage.removeItem('countries_cache_time');
        localStorage.removeItem('indicatifs_cache');
        localStorage.removeItem('indicatifs_cache_time');

        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('cities_')) {
                localStorage.removeItem(key);
            }
        }

        alert('Cache vidé ! La page va se recharger.');
        location.reload();
    };

    // ==========================================
    // INITIALISATION
    // ==========================================
    document.addEventListener('DOMContentLoaded', async () => {
        if (isAppInitialized) {
            console.warn('⚠️ Application déjà initialisée, abandon');
            return;
        }
        
        isAppInitialized = true;

        // Initialiser les valeurs des champs manuels si présentes
        initializeManualFields();

        loadIndicatifs();
        await loadCountries();

        const paysVillePairs = [
            { pays: 'pays', ville: 'ville', paysNom: 'pays_nom' },
            { pays: 'pays_etablissement', ville: 'ville_etablissement', paysNom: 'pays_etablissement_nom' }
        ];

        paysVillePairs.forEach(({ pays, ville, paysNom }) => {
            const selectPays = document.getElementById(pays);
            if (selectPays) {
                selectPays.addEventListener('change', function() {
                    const inputPaysNom = document.getElementById(paysNom);
                    if (inputPaysNom) {
                        updatePaysNom(pays, paysNom);
                    }
                    updateCities(pays, ville);
                });
            }
        });

        initMap();
    });

    // ==========================================
    // INITIALISATION DES CHAMPS MANUELS
    // ==========================================
    function initializeManualFields() {
        // Indicatif manuel
        const indicatifManual = document.getElementById('telephone_indicatif_manual');
        const currentIndicatif = '<?= addslashes($user['telephone_indicatif'] ?? '') ?>';
        if (indicatifManual && currentIndicatif) {
            indicatifManual.value = currentIndicatif.startsWith('+') ? currentIndicatif : '+' + currentIndicatif;
        }

        // Localisation utilisateur manuelle
        const paysManual = document.getElementById('pays_manual');
        const villeManual = document.getElementById('ville_manual');
        const currentPays = '<?= addslashes($user['pays'] ?? '') ?>';
        const currentVille = '<?= addslashes($user['ville'] ?? '') ?>';
        
        if (paysManual && currentPays) {
            paysManual.value = currentPays;
        }
        if (villeManual && currentVille) {
            villeManual.value = currentVille;
        }

        // Localisation établissement manuelle
        const paysEtabManual = document.getElementById('pays_etablissement_manual');
        const villeEtabManual = document.getElementById('ville_etablissement_manual');
        const currentPaysEtab = '<?= addslashes($user['pays_etablissement'] ?? '') ?>';
        const currentVilleEtab = '<?= addslashes($user['ville_etablissement'] ?? '') ?>';
        
        if (paysEtabManual && currentPaysEtab) {
            paysEtabManual.value = currentPaysEtab;
        }
        if (villeEtabManual && currentVilleEtab) {
            villeEtabManual.value = currentVilleEtab;
        }
    }
</script>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>