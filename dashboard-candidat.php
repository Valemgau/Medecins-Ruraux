<?php
require_once './includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
// var_dump($userRole);

if ($userRole !== 'candidat') {
    die("Rôle utilisateur inconnu");
}

$stmt = $pdo->prepare("SELECT u.email, u.created_at, c.numero_reference, c.telephone_indicatif, c.telephone, c.ville, c.pays, c.prenom, c.nom, c.photo,
    c.cv, c.diplome, c.diplome_specialite, c.reconnaissance,
    c.pays_recherche, c.autorisations_travail, c.motivations
    FROM users u JOIN candidats c ON u.id=c.id WHERE u.id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];

$alertMessages = [
    'register_success' => ['type' => 'success', 'text' => 'Compte créé avec succès !'],
    'update_success' => ['type' => 'success', 'text' => 'Profil mis à jour avec succès !'],
    'photo_success' => ['type' => 'success', 'text' => 'Photo mise à jour avec succès !'],
    'update_cv' => ['type' => 'success', 'text' => 'CV mis à jour avec succès !'],
    'update_diplome' => ['type' => 'success', 'text' => 'Diplôme mis à jour avec succès !'],
    'update_diplome_specialite' => ['type' => 'success', 'text' => 'Spécialité du diplôme mise à jour avec succès !'],
    'update_reconnaissance' => ['type' => 'success', 'text' => 'Reconnaissance mise à jour avec succès !'],
    'update_error' => ['type' => 'error', 'text' => 'Une erreur est survenue lors de la mise à jour.'],
    'photo_error' => ['type' => 'error', 'text' => 'Erreur lors de l\'upload de la photo.'],
];

// Upload photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['png', 'jpg', 'jpeg', 'webp'];
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        header("Location: dashboard-candidat.php?alert=photo_error");
        exit;
    }

    if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
        header("Location: dashboard-candidat.php?alert=photo_error");
        exit;
    }

    // Supprimer les anciennes photos
    if (!empty($user['photo']) && file_exists(__DIR__ . "/uploads/" . $user['photo'])) {
        unlink(__DIR__ . "/uploads/" . $user['photo']);
    }
    if (!empty($user['photo_blurred']) && file_exists(__DIR__ . "/uploads/" . $user['photo_blurred'])) {
        unlink(__DIR__ . "/uploads/" . $user['photo_blurred']);
    }

    // ✅ Générer des noms uniques et imprévisibles avec UUID
    $originalUuid = bin2hex(random_bytes(16)); // 32 caractères hexadécimaux
    $blurredUuid = bin2hex(random_bytes(16));
    
    $filename = "{$originalUuid}.{$ext}";
    $blurredFilename = "{$blurredUuid}.{$ext}";

    if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . "/uploads/$filename")) {
        $originalPath = __DIR__ . "/uploads/$filename";
        $blurredPath = __DIR__ . "/uploads/$blurredFilename";

        // Charger l'image
        $image = match ($ext) {
            'png' => imagecreatefrompng($originalPath),
            'webp' => imagecreatefromwebp($originalPath),
            default => imagecreatefromjpeg($originalPath)
        };

        // ✅ CORRIGER L'ORIENTATION EXIF
        if (in_array($ext, ['jpg', 'jpeg'])) {
            $exif = @exif_read_data($originalPath);
            if ($exif && isset($exif['Orientation'])) {
                $image = match ($exif['Orientation']) {
                    3 => imagerotate($image, 180, 0),
                    6 => imagerotate($image, -90, 0),
                    8 => imagerotate($image, 90, 0),
                    default => $image
                };
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Technique de flou extrême
        $tinyWidth = max(1, (int) ($width * 0.02));
        $tinyHeight = max(1, (int) ($height * 0.02));

        $tiny = imagecreatetruecolor($tinyWidth, $tinyHeight);

        if ($ext === 'png') {
            imagealphablending($tiny, false);
            imagesavealpha($tiny, true);
            imagealphablending($image, true);
        }

        imagecopyresampled($tiny, $image, 0, 0, 0, 0, $tinyWidth, $tinyHeight, $width, $height);

        $blurred = imagecreatetruecolor($width, $height);

        if ($ext === 'png') {
            imagealphablending($blurred, false);
            imagesavealpha($blurred, true);
        }

        imagecopyresampled($blurred, $tiny, 0, 0, 0, 0, $width, $height, $tinyWidth, $tinyHeight);

        for ($i = 0; $i < 3; $i++) {
            imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // ✅ Sauvegarder SANS métadonnées EXIF (orientation déjà appliquée)
        match ($ext) {
            'png' => imagepng($blurred, $blurredPath),
            'webp' => imagewebp($blurred, $blurredPath, 85),
            default => imagejpeg($blurred, $blurredPath, 85)
        };

        imagedestroy($image);
        imagedestroy($tiny);
        imagedestroy($blurred);

        $pdo->prepare("UPDATE candidats SET photo=?, photo_blurred=? WHERE id=?")->execute([$filename, $blurredFilename, $userId]);
        header("Location: dashboard-candidat.php?alert=photo_success");
        exit;
    } else {
        header("Location: dashboard-candidat.php?alert=photo_error");
        exit;
    }
}




// Upload autres fichiers
$fieldNames = ['cv', 'diplome', 'diplome_specialite', 'reconnaissance'];
foreach ($fieldNames as $f) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'])) {
            $errors[] = "Le fichier $f doit être au format PDF, DOC, DOCX, JPG, PNG ou WEBP.";
        } else {
            if (!empty($user[$f]) && file_exists(__DIR__ . "/uploads/" . $user[$f])) {
                unlink(__DIR__ . "/uploads/" . $user[$f]);
            }
            $filename = "{$f}_{$userId}_" . time() . ".$ext";
            move_uploaded_file($_FILES[$f]['tmp_name'], __DIR__ . "/uploads/$filename");
            $pdo->prepare("UPDATE candidats SET $f=? WHERE id=?")->execute([$filename, $userId]);
            header("Location: dashboard-candidat.php?alert=update_$f");
            exit;
        }
    }
}

// Suppression de documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $fieldToDelete = $_POST['delete_document'];
    if (in_array($fieldToDelete, $fieldNames)) {
        if (!empty($user[$fieldToDelete]) && file_exists(__DIR__ . "/uploads/" . $user[$fieldToDelete])) {
            unlink(__DIR__ . "/uploads/" . $user[$fieldToDelete]);
        }
        $pdo->prepare("UPDATE candidats SET $fieldToDelete=NULL WHERE id=?")->execute([$userId]);
        header("Location: dashboard-candidat.php?alert=update_success");
        exit;
    }
}

// Traitement formulaire principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['photo']) && !isset($_FILES['cv']) && !isset($_FILES['diplome']) && !isset($_FILES['diplome_specialite']) && !isset($_FILES['reconnaissance'])) {

    $villeMode = $_POST['ville_mode'] ?? 'select';
    $paysMode = $_POST['pays_mode'] ?? 'select';
    $indicatifMode = $_POST['indicatif_mode'] ?? 'select';

    $fields = [
        'prenom' => trim($_POST['prenom'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'telephone_indicatif' => $indicatifMode === 'select' ? ($_POST['telephone_indicatif'] ?? '') : trim($_POST['indicatif_manual'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'ville' => $villeMode === 'select' ? trim($_POST['ville'] ?? '') : trim($_POST['ville_manual'] ?? ''),
        'pays' => $paysMode === 'select' ? trim($_POST['pays'] ?? '') : trim($_POST['pays_manual'] ?? ''),
        'pays_recherche' => isset($_POST['pays_recherche']) ? json_encode(array_slice($_POST['pays_recherche'], 0, 10)) : json_encode([]),
        'autorisations_travail' => isset($_POST['autorisations_travail']) ? json_encode(array_slice($_POST['autorisations_travail'], 0, 10)) : json_encode([]),
        'motivations' => trim($_POST['motivations'] ?? ''),
    ];

    if ($indicatifMode === 'manual' && !preg_match('/^\+\d{1,4}$/', $fields['telephone_indicatif'])) {
        $errors[] = "L'indicatif téléphonique doit être au format +XXX.";
    }

    if (!$fields['prenom'] || !$fields['nom']) {
        $errors[] = "Le prénom et le nom sont obligatoires.";
    }

    if (!$fields['pays']) {
        $errors[] = "Le pays est obligatoire.";
    }

    if (!$fields['ville']) {
        $errors[] = "La ville est obligatoire.";
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE candidats SET prenom=?, nom=?, telephone_indicatif=?, telephone=?, ville=?, pays=?, pays_recherche=?, autorisations_travail=?, motivations=? WHERE id=?")
            ->execute([
                $fields['prenom'],
                $fields['nom'],
                $fields['telephone_indicatif'],
                $fields['telephone'],
                $fields['ville'],
                $fields['pays'],
                $fields['pays_recherche'],
                $fields['autorisations_travail'],
                $fields['motivations'],
                $userId
            ]);
        header("Location: dashboard-candidat.php?alert=update_success");
        exit;
    }
}

$requiredFields = [
    'prenom' => 'Prénom',
    'nom' => 'Nom',
    'telephone_indicatif' => 'Indicatif téléphonique',
    'telephone' => 'Téléphone',
    'ville' => 'Ville',
    'pays' => 'Pays',
    'photo' => 'Photo',
    'cv' => 'CV',
    'diplome' => 'Diplôme',
    'diplome_specialite' => 'Spécialité du diplôme',
    'motivations' => 'Motivations',
];

$missingFields = [];
foreach ($requiredFields as $field => $label) {
    if ($field === 'pays_recherche' || $field === 'autorisations_travail') {
        if (empty($user[$field]) || (is_string($user[$field]) && count(json_decode($user[$field], true) ?: []) === 0)) {
            $missingFields[] = $label;
        }
    } else {
        if (empty($user[$field]) || (is_string($user[$field]) && trim($user[$field]) === '')) {
            $missingFields[] = $label;
        }
    }
}

$title = "Dashboard candidat";
ob_start();
?>

<style>
    /* Toggle Switch amélioré */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
        background-color: #cbd5e1;
        border-radius: 9999px;
        transition: background-color 0.3s ease;
        cursor: pointer;
        flex-shrink: 0;
    }

    .toggle-switch.active {
        background-color: #10b981;
    }

    .toggle-dot {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        background-color: white;
        border-radius: 9999px;
        transition: transform 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .toggle-switch.active .toggle-dot {
        transform: translateX(20px);
    }

    /* Styles pour les tags de pays */
    .country-tag {
        animation: slideIn 0.2s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .country-tag:hover {
        transform: scale(1.05);
    }

    /* Dropdown personnalisé avec scroll */
    .country-dropdown {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
        opacity: 0;
    }

    .country-dropdown.open {
        max-height: 300px;
        opacity: 1;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #10b981 #f3f4f6;
    }

    .country-dropdown.open::-webkit-scrollbar {
        width: 8px;
    }

    .country-dropdown.open::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 0 0 8px 0;
    }

    .country-dropdown.open::-webkit-scrollbar-thumb {
        background-color: #10b981;
        border-radius: 4px;
    }

    .country-dropdown.open::-webkit-scrollbar-thumb:hover {
        background-color: #059669;
    }

    .country-option:hover {
        background-color: #f0fdf4;
        transform: translateX(4px);
        transition: all 0.2s ease;
    }

    /* Compteur de sélection */
    .selection-counter {
        transition: all 0.3s ease;
    }

    .selection-counter.warning {
        color: #f59e0b;
        font-weight: 600;
    }

    .selection-counter.danger {
        color: #ef4444;
        font-weight: 700;
    }

    /* Animation pour le focus sur les inputs */
    .focus-ring:focus {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
</style>

<div class="md:p-6">
    <?php if (!empty($_GET['alert']) && isset($alertMessages[$_GET['alert']])): ?>
        <div class="max-w-7xl mx-auto my-4 bg-green-100">
            <div id="alertBanner"
                class="<?= $alertMessages[$_GET['alert']]['type'] === 'success' ? 'border border-green-200 text-green-800' : 'bg-gradient-to-r from-red-50 to-red-100 border border-red-200 text-red-800' ?> p-4 rounded-lg shadow-sm flex items-center justify-between">
                <span class="font-medium"><?= htmlspecialchars($alertMessages[$_GET['alert']]['text']) ?></span>
                <button onclick="document.getElementById('alertBanner').style.display='none'"
                    class="ml-4 text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">

        <div id="map" class="h-40 w-full"></div>

        <div class="md:flex p-8 pb-5 space-x-8">
            <div class="flex-shrink-0 -mt-20 relative">
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Avatar"
                        style="position:relative;z-index:1000;"
                        class="h-40 w-40 rounded-full border-4 border-white object-cover shadow-lg" />
                <?php else: ?>
                    <div style="position:relative;z-index:1000;"
                        class="h-40 w-40 rounded-full border-4 border-white flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 shadow-lg">
                        <svg class="h-20 w-20 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                <?php endif; ?>

                <?php $isComplete = empty($missingFields); ?>
                <div style="position:absolute; top: 0; right: 0; z-index: 1100;"
                    class="rounded-full w-8 h-8 border-3 flex items-center justify-center shadow-lg <?= $isComplete ? 'border-green-500 bg-green-400' : 'border-orange-500 bg-orange-400' ?>"
                    title="<?= $isComplete ? 'Profil complet' : 'Profil incomplet' ?>">
                    <?php if ($isComplete): ?>
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                clip-rule="evenodd" />
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data"
                    class="absolute bottom-0 left-1/2 transform -translate-x-1/2" style="z-index:1100;">
                    <label
                        class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm font-medium rounded-full cursor-pointer hover:from-green-600 hover:to-green-700 transition shadow-md">
                        Changer
                        <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()" />
                    </label>
                </form>
            </div>

            <div class="flex-grow pt-2">
                <h1
                    class="text-3xl font-bold bg-gradient-to-r from-green-600 to-green-500 bg-clip-text text-transparent">
                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                </h1>
                <p class="text-gray-600 mt-1 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <?= htmlspecialchars($user['email']) ?>
                </p>
                <p class="text-gray-500 mt-2 text-sm flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    Référence: <?= $user['numero_reference'] ?? 'N° non attribué' ?>
                </p>
                <p class="text-gray-500 mt-1 text-sm flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Inscrit depuis le <?= date("d/m/Y", strtotime($user['created_at'])) ?>
                </p>
            </div>
        </div>

        <div class="border-t border-gray-200 px-8 pb-8">

            <?php if (!empty($errors)): ?>
                <div class="mt-6 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 text-red-700 p-4 rounded-lg">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($missingFields)): ?>
                <div class="mt-6 bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-5">
                    <h3 class="text-orange-800 font-semibold mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Complétez votre profil
                    </h3>
                    <ul class="flex flex-wrap gap-3 text-sm">
                        <?php foreach ($requiredFields as $field => $label):
                            if ($field === 'pays_recherche') {
                                $filled = !empty($user[$field]) && (count(json_decode($user[$field], true) ?: []) > 0);
                            } else {
                                $filled = !empty($user[$field]) && (is_string($user[$field]) ? trim($user[$field]) !== '' : true);
                            }
                            ?>
                            <li class="flex items-center space-x-2 bg-white px-3 py-2 rounded-lg shadow-sm">
                                <span
                                    class="w-3 h-3 rounded-full flex-shrink-0 <?= $filled ? 'bg-green-500' : 'bg-orange-500' ?>"></span>
                                <span class="<?= $filled ? 'text-green-700' : 'text-orange-700 font-medium' ?>">
                                    <?= htmlspecialchars($label) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6 max-w-3xl mt-10">

                <div class="border bg-gray-50 border-gray-100 p-5 rounded-r-lg">
                    <h2 class="text-lg font-bold text-green-800 mb-4">Informations personnelles</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-2">Prénom <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                autocomplete="off" />
                        </div>
                        <div>
                            <label class="block font-semibold text-gray-700 mb-2">Nom <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                autocomplete="off" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-100 cursor-not-allowed text-gray-500" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <div class="md:col-span-1">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block font-semibold text-gray-700">Indicatif <span
                                        class="text-red-500">*</span></label>
                                <label for="indicatif_toggle" class="flex items-center cursor-pointer">
                                    <span class="text-xs text-gray-600 mr-2">Manuel</span>
                                    <input type="checkbox" id="indicatif_toggle" class="sr-only"
                                        onchange="toggleIndicatifMode()">
                                    <div class="toggle-switch">
                                        <div class="toggle-dot"></div>
                                    </div>
                                </label>
                            </div>
                            <input type="hidden" name="indicatif_mode" id="indicatif_mode" value="select">
                            <select id="telephone_indicatif" name="telephone_indicatif" required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"></select>
                            <input type="text" id="indicatif_manual" name="indicatif_manual"
                                value="<?= htmlspecialchars($user['telephone_indicatif'] ?? '') ?>"
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition hidden"
                                placeholder="+33" maxlength="5" autocomplete="off" spellcheck="false">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold text-gray-700 mb-2">Téléphone <span
                                    class="text-red-500">*</span></label>
                            <input type="tel" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                                required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                autocomplete="off" />
                        </div>
                    </div>
                </div>

                <div class="border bg-gray-50 border-gray-100 p-5 rounded-r-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-green-800">Localisation</h2>
                        <label for="localisation_toggle" class="flex items-center cursor-pointer">
                            <span class="text-sm text-gray-600 mr-3">Saisie manuelle</span>
                            <input type="checkbox" id="localisation_toggle" class="sr-only"
                                onchange="toggleLocalisationMode()">
                            <div class="toggle-switch">
                                <div class="toggle-dot"></div>
                            </div>
                        </label>
                    </div>

                    <input type="hidden" name="pays_mode" id="pays_mode" value="select">
                    <input type="hidden" name="ville_mode" id="ville_mode" value="select">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-2">Pays <span
                                    class="text-red-500">*</span></label>
                            <select id="pays" name="pays" required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition"></select>
                            <input type="text" id="pays_manual" name="pays_manual"
                                value="<?= htmlspecialchars($user['pays'] ?? '') ?>"
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition hidden"
                                placeholder="Entrez le pays manuellement" autocomplete="off" spellcheck="false">
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-2">Ville <span
                                    class="text-red-500">*</span></label>
                            <select id="ville" name="ville" required
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                                <option value="" disabled selected>Choisir une ville</option>
                                <?php if (!empty($user['ville'])): ?>
                                    <option value="<?= htmlspecialchars($user['ville']) ?>" selected>
                                        <?= htmlspecialchars($user['ville']) ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <input type="text" id="ville_manual" name="ville_manual"
                                value="<?= htmlspecialchars($user['ville'] ?? '') ?>"
                                class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition hidden"
                                placeholder="Entrez la ville manuellement" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                </div>

                <!-- Section Recherche d'emploi améliorée -->
                <div class="border bg-gray-50 border-gray-100 p-5 rounded-r-lg">
                    <h2 class="text-lg font-bold text-green-800 mb-4 flex items-center">

                        Recherche d'emploi
                    </h2>

                    <!-- Pays de recherche -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block font-semibold text-gray-700">
                                Dans quels pays recherchez-vous un emploi ?
                            </label>
                            <span id="pays_recherche_counter" class="text-sm selection-counter">
                                <span class="font-bold">0</span>/10 pays
                            </span>
                        </div>

                        <!-- Tags des pays sélectionnés -->
                        <div id="pays_recherche_tags"
                            class="flex flex-wrap gap-2 mb-3 min-h-[40px] p-2 bg-white rounded-lg border border-gray-300">
                            <div class="flex items-center text-gray-400 text-sm">
                                Cliquez pour ajouter des pays
                            </div>
                        </div>

                        <!-- Champ de recherche avec dropdown -->
                        <div class="relative">
                            <div class="relative">
                                <input type="text" id="pays_recherche_search" placeholder="Rechercher un pays..."
                                    autocomplete="off" spellcheck="false"
                                    class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition" />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>

                            <!-- Dropdown des pays -->
                            <div id="pays_recherche_dropdown"
                                class="country-dropdown absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg overflow-y-auto">
                                <!-- Les options seront ajoutées dynamiquement -->
                            </div>
                        </div>

                        <p class="text-gray-500 text-xs mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            Sélectionnez jusqu'à 10 pays maximum
                        </p>
                    </div>

                    <!-- Autorisations de travail -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block font-semibold text-gray-700">
                                Pour quels pays détenez-vous les autorisations de travail ?
                            </label>
                            <span id="autorisations_travail_counter" class="text-sm selection-counter">
                                <span class="font-bold">0</span>/10 pays
                            </span>
                        </div>

                        <!-- Tags des pays sélectionnés -->
                        <div id="autorisations_travail_tags"
                            class="flex flex-wrap gap-2 mb-3 min-h-[40px] p-2 bg-white rounded-lg border border-gray-300">
                            <div class="flex items-center text-gray-400 text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Sélectionnez vos autorisations
                            </div>
                        </div>

                        <!-- Champ de recherche avec dropdown -->
                        <div class="relative">
                            <div class="relative">
                                <input type="text" id="autorisations_travail_search" placeholder="Rechercher un pays..."
                                    autocomplete="off" spellcheck="false"
                                    class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition" />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>

                            <!-- Dropdown des pays -->
                            <div id="autorisations_travail_dropdown"
                                class="country-dropdown absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg overflow-y-auto">
                                <!-- Les options seront ajoutées dynamiquement -->
                            </div>
                        </div>

                        <p class="text-gray-500 text-xs mt-2 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            Indiquez les pays où vous pouvez légalement travailler
                        </p>
                    </div>

                    <!-- Motivations -->
                    <div>
                        <label class="block font-semibold text-gray-700 mb-2 flex items-center">

                            Motivations
                        </label>
                        <textarea name="motivations" id="motivations" rows="6"
                            class="focus-ring w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none"
                            placeholder="Parlez-nous de vos aspirations professionnelles, de vos objectifs de carrière et de ce qui vous motive..."
                            autocomplete="off"
                            spellcheck="true"><?= htmlspecialchars($user['motivations'] ?? '') ?></textarea>
                        <p class="text-gray-500 text-xs mt-1">Exprimez librement vos motivations et vos ambitions
                            professionnelles</p>
                    </div>
                </div>

                <button type="submit"
                    class="w-full md:w-auto bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 transition text-white font-semibold px-8 py-3 rounded-lg shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Mettre à jour le profil
                </button>
            </form>

            <div class="mt-8 space-y-4 max-w-3xl">
                <div class="bg-gray-50 border-l-4 border-gray-400 p-4 rounded">
                    <h2 class="text-base font-semibold text-gray-700 mb-4">Documents</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- CV -->
                        <div
                            class="bg-white p-4 rounded border <?= !empty($user['cv']) ? 'border-green-400' : 'border-gray-300' ?>">
                            <label class="block font-medium text-gray-700 mb-2">
                                CV (PDF/DOC/Image)
                            </label>

                            <?php if (!empty($user['cv'])): ?>
                                <div class="space-y-2">
                                    <a href="uploads/<?= htmlspecialchars($user['cv']) ?>" target="_blank"
                                        class="block bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2 px-3 rounded text-center transition">
                                        Voir
                                    </a>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" onclick="document.getElementById('cv_input').click()"
                                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-3 rounded transition">
                                            Modifier
                                        </button>

                                        <form method="post" style="display: inline;"
                                            onsubmit="return confirm('Supprimer ce document ?');">
                                            <input type="hidden" name="delete_document" value="cv">
                                            <button type="submit"
                                                class="w-full bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded transition">
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <p class="text-xs text-green-600 mt-2">✓ Document envoyé</p>

                                <form method="post" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" id="cv_input" name="cv"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()" />
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-orange-600 mb-2">⚠ Document manquant</p>

                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100 file:cursor-pointer"
                                        onchange="this.form.submit()" />
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Diplôme -->
                        <div
                            class="bg-white p-4 rounded border <?= !empty($user['diplome']) ? 'border-green-400' : 'border-gray-300' ?>">
                            <label class="block font-medium text-gray-700 mb-2">
                                Diplôme (PDF/Image)
                            </label>

                            <?php if (!empty($user['diplome'])): ?>
                                <div class="space-y-2">
                                    <a href="uploads/<?= htmlspecialchars($user['diplome']) ?>" target="_blank"
                                        class="block bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2 px-3 rounded text-center transition">
                                        Voir
                                    </a>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" onclick="document.getElementById('diplome_input').click()"
                                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-3 rounded transition">
                                            Modifier
                                        </button>

                                        <form method="post" style="display: inline;"
                                            onsubmit="return confirm('Supprimer ce document ?');">
                                            <input type="hidden" name="delete_document" value="diplome">
                                            <button type="submit"
                                                class="w-full bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded transition">
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <p class="text-xs text-green-600 mt-2">✓ Document envoyé</p>

                                <form method="post" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" id="diplome_input" name="diplome"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()" />
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-orange-600 mb-2">⚠ Document manquant</p>

                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="diplome" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100 file:cursor-pointer"
                                        onchange="this.form.submit()" />
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Spécialité du diplôme -->
                        <div
                            class="bg-white p-4 rounded border <?= !empty($user['diplome_specialite']) ? 'border-green-400' : 'border-gray-300' ?>">
                            <label class="block font-medium text-gray-700 mb-2">
                                Spécialité du diplôme (PDF/Image)
                            </label>

                            <?php if (!empty($user['diplome_specialite'])): ?>
                                <div class="space-y-2">
                                    <a href="uploads/<?= htmlspecialchars($user['diplome_specialite']) ?>" target="_blank"
                                        class="block bg-green-500 hover:bg-green-600 text-white text-sm font-medium py-2 px-3 rounded text-center transition">
                                        Voir
                                    </a>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button"
                                            onclick="document.getElementById('diplome_specialite_input').click()"
                                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-3 rounded transition">
                                            Modifier
                                        </button>

                                        <form method="post" style="display: inline;"
                                            onsubmit="return confirm('Supprimer ce document ?');">
                                            <input type="hidden" name="delete_document" value="diplome_specialite">
                                            <button type="submit"
                                                class="w-full bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded transition">
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <p class="text-xs text-green-600 mt-2">✓ Document envoyé</p>

                                <form method="post" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" id="diplome_specialite_input" name="diplome_specialite"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()" />
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-orange-600 mb-2">⚠ Document manquant</p>

                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="diplome_specialite"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100 file:cursor-pointer"
                                        onchange="this.form.submit()" />
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Reconnaissance -->
                        <div
                            class="bg-white p-4 rounded border <?= !empty($user['reconnaissance']) ? 'border-blue-400' : 'border-gray-300' ?>">
                            <label class="block font-medium text-gray-700 mb-2">
                                Reconnaissance <span class="text-xs text-gray-500">(Optionnel)</span>
                            </label>

                            <?php if (!empty($user['reconnaissance'])): ?>
                                <div class="space-y-2">
                                    <a href="uploads/<?= htmlspecialchars($user['reconnaissance']) ?>" target="_blank"
                                        class="block bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-3 rounded text-center transition">
                                        Voir
                                    </a>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button"
                                            onclick="document.getElementById('reconnaissance_input').click()"
                                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-3 rounded transition">
                                            Modifier
                                        </button>

                                        <form method="post" style="display: inline;"
                                            onsubmit="return confirm('Supprimer ce document ?');">
                                            <input type="hidden" name="delete_document" value="reconnaissance">
                                            <button type="submit"
                                                class="w-full bg-red-500 hover:bg-red-600 text-white text-sm py-2 px-3 rounded transition">
                                                Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <p class="text-xs text-blue-600 mt-2">✓ Document envoyé</p>

                                <form method="post" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" id="reconnaissance_input" name="reconnaissance"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()" />
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-gray-500 mb-2">ℹ Document optionnel</p>

                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="reconnaissance" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 file:cursor-pointer"
                                        onchange="this.form.submit()" />
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 border-t border-gray-200 pt-8">
                <div class=" border-l-4 border-red-500 p-5 rounded-r-lg">
                    <h3 class="text-red-800 font-bold mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Zone de danger
                    </h3>
                    <p class="text-gray-700 text-sm mb-4">La suppression de votre compte est définitive et irréversible.
                    </p>
                    <a href="supprimer_compte.php"
                        class="inline-block bg-red-500 hover:bg-red-600 transition text-white font-semibold py-2 px-6 rounded-lg shadow-md hover:shadow-lg">
                        Supprimer mon compte
                    </a>
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
    let selectedPaysRecherche = <?= json_encode(array_map('trim', json_decode($user['pays_recherche'] ?? '[]', true) ?: [])) ?>;
    let selectedAutorisations = <?= json_encode(array_map('trim', json_decode($user['autorisations_travail'] ?? '[]', true) ?: [])) ?>;
    let mapInstance = null;
    let isAppInitialized = false;

    // ==========================================
    // CONSTANTES DE CACHE
    // ==========================================
    const CACHE_DURATION_COUNTRIES = 86400000; // 24 heures
    const CACHE_DURATION_CITIES = 604800000; // 7 jours
    const CACHE_DURATION_INDICATIFS = 86400000; // 24 heures

    // ==========================================
    // FONCTIONS DE TOGGLE (MODE MANUEL)
    // ==========================================
    function toggleIndicatifMode() {
        const toggle = document.getElementById('indicatif_toggle');
        const select = document.getElementById('telephone_indicatif');
        const manual = document.getElementById('indicatif_manual');
        const mode = document.getElementById('indicatif_mode');

        const label = toggle.closest('label');
        const toggleSwitch = label ? label.querySelector('.toggle-switch') : null;

        if (!toggleSwitch) {
            console.error('Toggle switch non trouvé pour indicatif');
            return;
        }

        if (toggle.checked) {
            toggleSwitch.classList.add('active');
            select.classList.add('hidden');
            manual.classList.remove('hidden');
            manual.required = true;
            select.required = false;
            mode.value = 'manual';

            if (!manual.value.startsWith('+')) {
                manual.value = '+' + manual.value.replace(/\+/g, '');
            }
        } else {
            toggleSwitch.classList.remove('active');
            select.classList.remove('hidden');
            manual.classList.add('hidden');
            manual.required = false;
            select.required = true;
            mode.value = 'select';
        }
    }

    function toggleLocalisationMode() {
        const toggle = document.getElementById('localisation_toggle');
        const paysSelect = document.getElementById('pays');
        const paysManual = document.getElementById('pays_manual');
        const villeSelect = document.getElementById('ville');
        const villeManual = document.getElementById('ville_manual');
        const paysMode = document.getElementById('pays_mode');
        const villeMode = document.getElementById('ville_mode');

        const label = toggle.closest('label');
        const toggleSwitch = label ? label.querySelector('.toggle-switch') : null;

        if (!toggleSwitch) {
            console.error('Toggle switch non trouvé pour localisation');
            return;
        }

        if (toggle.checked) {
            toggleSwitch.classList.add('active');
            paysSelect.classList.add('hidden');
            paysManual.classList.remove('hidden');
            paysManual.required = true;
            paysSelect.required = false;
            paysMode.value = 'manual';

            villeSelect.classList.add('hidden');
            villeManual.classList.remove('hidden');
            villeManual.required = true;
            villeSelect.required = false;
            villeMode.value = 'manual';
        } else {
            toggleSwitch.classList.remove('active');
            paysSelect.classList.remove('hidden');
            paysManual.classList.add('hidden');
            paysManual.required = false;
            paysSelect.required = true;
            paysMode.value = 'select';

            villeSelect.classList.remove('hidden');
            villeManual.classList.add('hidden');
            villeManual.required = false;
            villeSelect.required = true;
            villeMode.value = 'select';
        }
    }

    // ==========================================
    // CHARGEMENT DES INDICATIFS AVEC CACHE
    // ==========================================
    async function loadIndicatifs() {
        const select = document.getElementById('telephone_indicatif');
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
                const callingCode = country.idd.root + (country.idd.suffixes ? country.idd.suffixes[0] : '');
                options += `<option value="${callingCode}">${callingCode}</option>`;
            }
        });
        select.innerHTML = options;

        const currentIndicatif = '<?= addslashes($user['telephone_indicatif'] ?? '') ?>';
        if (currentIndicatif) {
            select.value = currentIndicatif;
        }
    }

    function setupIndicatifManualInput() {
        const manualInput = document.getElementById('indicatif_manual');
        if (!manualInput) return;

        if (manualInput.value && !manualInput.value.startsWith('+')) {
            manualInput.value = '+' + manualInput.value.replace(/\+/g, '');
        }

        manualInput.addEventListener('input', function (e) {
            let value = e.target.value;
            if (!value.startsWith('+')) {
                value = '+' + value.replace(/\+/g, '');
            }
            const numbers = value.substring(1).replace(/\D/g, '');
            e.target.value = '+' + numbers.substring(0, 4);
        });

        manualInput.addEventListener('keydown', function (e) {
            if ((e.key === 'Backspace' || e.key === 'Delete') && e.target.selectionStart <= 1) {
                e.preventDefault();
            }
        });

        manualInput.addEventListener('focus', function (e) {
            if (!e.target.value) {
                e.target.value = '+';
            }
        });
    }

    // ==========================================
    // CHARGEMENT DES PAYS AVEC CACHE
    // ==========================================
    async function loadCountries() {
        const selectPays = document.getElementById('pays');
        selectPays.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';

        const cached = localStorage.getItem('countries_cache');
        const cacheTime = localStorage.getItem('countries_cache_time');
        const now = Date.now();

        if (cached && cacheTime && (now - parseInt(cacheTime)) < CACHE_DURATION_COUNTRIES) {
            allCountries = JSON.parse(cached);
            renderCountryOptions();
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

            renderCountryOptions();

        } catch (error) {
            console.error('❌ Erreur lors du chargement des pays:', error);
            selectPays.innerHTML = '<option value="" disabled>❌ Erreur - Cliquez pour réessayer</option>';
            selectPays.disabled = false;

            selectPays.addEventListener('click', () => {
                localStorage.removeItem('countries_cache');
                localStorage.removeItem('countries_cache_time');
                loadCountries();
            }, { once: true });
        }
    }

    function renderCountryOptions() {
        const selectPays = document.getElementById('pays');
        let options = '<option value="" disabled>Choisir un pays</option>';

        allCountries.forEach(country => {
            const countryName = country.translations.fra.common || '';
            const selected = (countryName === '<?= addslashes($user['pays'] ?? '') ?>') ? 'selected' : '';
            options += `<option value="${countryName}" ${selected}>${countryName}</option>`;
        });

        selectPays.innerHTML = options;
        selectPays.disabled = false;

        const currentPays = '<?= addslashes($user['pays'] ?? '') ?>';
        const paysExists = allCountries.some(c => c.translations.fra.common === currentPays);

        if (currentPays && !paysExists) {
            const toggle = document.getElementById('localisation_toggle');
            if (toggle) {
                toggle.checked = true;
                toggleLocalisationMode();
            }
        } else if (selectPays.value) {
            updateCities('pays', 'ville');
        }
    }

    // ==========================================
    // SÉLECTEUR DE PAYS AVEC TAGS
    // ==========================================
    function setupCountrySelector(fieldName, selectedCountries) {
        const searchInput = document.getElementById(`${fieldName}_search`);
        const dropdown = document.getElementById(`${fieldName}_dropdown`);
        const tagsContainer = document.getElementById(`${fieldName}_tags`);
        const counter = document.getElementById(`${fieldName}_counter`);

        if (!searchInput || !dropdown || !tagsContainer || !counter) {
            console.error('Éléments DOM manquants pour setupCountrySelector');
            return;
        }

        let isDropdownOpen = false;

        function updateCounter() {
            const count = selectedCountries.length;
            const counterElement = counter.querySelector('.font-bold');
            if (counterElement) {
                counterElement.textContent = count;
            }

            counter.classList.remove('warning', 'danger');
            if (count >= 9) {
                counter.classList.add('danger');
            } else if (count >= 7) {
                counter.classList.add('warning');
            }
        }

        function renderTags() {
            if (selectedCountries.length === 0) {
                tagsContainer.innerHTML = `
                    <div class="flex items-center text-gray-400 text-sm">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        ${fieldName === 'pays_recherche' ? 'Cliquez pour ajouter des pays' : 'Sélectionnez vos autorisations'}
                    </div>
                `;
            } else {
                tagsContainer.innerHTML = selectedCountries.map(country => `
                    <div class="country-tag inline-flex items-center bg-gradient-to-r from-green-500 to-green-600 text-white px-3 py-1 rounded-full text-sm font-medium shadow-sm hover:shadow-md transition-all">
                        <span>${country}</span>
                        <button type="button" onclick="removeCountry('${fieldName}', '${country.replace(/'/g, "\\'")}', event)" 
                            class="ml-2 hover:bg-white hover:bg-opacity-20 rounded-full p-0.5 transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <input type="hidden" name="${fieldName}[]" value="${country}" />
                `).join('');
            }
            updateCounter();
        }

        function renderDropdown(filter = '') {
            const filtered = allCountries.filter(c =>
                c.translations.fra.common.toLowerCase().includes(filter.toLowerCase()) &&
                !selectedCountries.includes(c.translations.fra.common)
            );

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="px-4 py-3 text-gray-500 text-sm">Aucun pays trouvé</div>';
            } else {
                dropdown.innerHTML = filtered.map(country => `
                    <div class="country-option px-4 py-2.5 hover:bg-green-50 cursor-pointer flex items-center justify-between border-b border-gray-100 last:border-b-0"
                        onclick="addCountry('${fieldName}', '${country.translations.fra.common.replace(/'/g, "\\'")}')">
                        <span class="text-gray-700">${country.translations.fra.common}</span>
                        <svg class="w-4 h-4 text-green-500 opacity-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                `).join('');
            }
        }

        function openDropdown() {
            dropdown.classList.add('open');
            isDropdownOpen = true;
            renderDropdown(searchInput.value);
        }

        function closeDropdown() {
            dropdown.classList.remove('open');
            isDropdownOpen = false;
        }

        searchInput.addEventListener('focus', () => {
            openDropdown();
            if (searchInput.value === '') {
                renderDropdown('');
            }
        });

        searchInput.addEventListener('input', (e) => {
            renderDropdown(e.target.value);
            if (!isDropdownOpen) openDropdown();
        });

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                closeDropdown();
            }
        });

        renderTags();
        updateCounter();
    }

    // ==========================================
    // AJOUTER/RETIRER UN PAYS
    // ==========================================
    window.addCountry = function (fieldName, countryName) {
        const selectedArray = fieldName === 'pays_recherche' ? selectedPaysRecherche : selectedAutorisations;

        if (selectedArray.length >= 10) {
            alert('Vous pouvez sélectionner jusqu\'à 10 pays maximum.');
            return;
        }

        if (!selectedArray.includes(countryName)) {
            selectedArray.push(countryName);
            setupCountrySelector(fieldName, selectedArray);

            const searchInput = document.getElementById(`${fieldName}_search`);
            const dropdown = document.getElementById(`${fieldName}_dropdown`);

            if (searchInput) {
                searchInput.value = '';
            }

            if (dropdown) {
                dropdown.classList.add('open');

                const filtered = allCountries.filter(c =>
                    !selectedArray.includes(c.translations.fra.common)
                );

                if (filtered.length > 0) {
                    dropdown.innerHTML = filtered.slice(0, 50).map(country => `
                        <div class="country-option px-4 py-2.5 hover:bg-green-50 cursor-pointer flex items-center justify-between border-b border-gray-100 last:border-b-0"
                            onclick="addCountry('${fieldName}', '${country.translations.fra.common.replace(/'/g, "\\'")}')">
                            <span class="text-gray-700">${country.translations.fra.common}</span>
                            <svg class="w-4 h-4 text-green-500 opacity-0 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                    `).join('');
                } else {
                    dropdown.innerHTML = '<div class="px-4 py-3 text-gray-500 text-sm">Tous les pays ont été sélectionnés</div>';
                }
            }
        }
    };

    window.removeCountry = function (fieldName, countryName, event) {
        if (event) {
            event.stopPropagation();
        }

        const selectedArray = fieldName === 'pays_recherche' ? selectedPaysRecherche : selectedAutorisations;
        const index = selectedArray.indexOf(countryName);

        if (index > -1) {
            selectedArray.splice(index, 1);
            setupCountrySelector(fieldName, selectedArray);
        }
    };

    // ==========================================
    // MISE À JOUR DES VILLES AVEC CACHE
    // ==========================================
    async function updateCities(selectPaysId, selectVilleId) {
        const selectPays = document.getElementById(selectPaysId);
        const villeSelect = document.getElementById(selectVilleId);
        const countryName = selectPays.value;

        villeSelect.innerHTML = '<option value="" disabled selected>Chargement des villes...</option>';
        villeSelect.disabled = true;

        if (!countryName) {
            villeSelect.innerHTML = '<option value="" disabled>Choisir un pays d\'abord</option>';
            return;
        }

        const country = allCountries.find(c => c.translations.fra.common === countryName);

        if (!country || !country.cca2) {
            console.error('❌ Code pays introuvable pour:', countryName);
            villeSelect.innerHTML = '<option value="" disabled>Code pays introuvable</option>';
            villeSelect.disabled = false;
            return;
        }

        const countryCode = country.cca2;

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

        const currentVille = '<?= addslashes($user['ville'] ?? '') ?>';
        const currentPays = '<?= addslashes($user['pays'] ?? '') ?>';
        const selectedPays = document.getElementById('pays')?.value;

        let villeFound = false;

        cities.forEach(city => {
            const selected = city.name === currentVille ? 'selected' : '';
            if (selected) villeFound = true;
            villeSelect.insertAdjacentHTML('beforeend', `<option value="${city.name}" ${selected}>${city.name}</option>`);
        });

        villeSelect.disabled = false;

        if (currentVille && !villeFound && selectPaysId === 'pays' && selectedPays === currentPays) {
            const toggle = document.getElementById('localisation_toggle');
            if (toggle && !toggle.checked) {
                toggle.checked = true;
                toggleLocalisationMode();
            }
        } else if (!villeFound && selectedPays !== currentPays) {
            villeSelect.value = '';
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
            mapContainer.innerHTML = `
                <div class="h-full w-full bg-gradient-to-br from-green-500/90 to-green-600/90 flex items-center justify-center">
                    <div class="text-center text-white p-6">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <h3 class="text-xl font-bold mb-2">Localisation non définie</h3>
                        <p class="text-sm opacity-90">Complétez votre ville et pays pour afficher la carte</p>
                    </div>
                </div>
            `;
            return;
        }

        const adresse = `${ville}, ${pays}`;

        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(adresse)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!data.length) {
                mapContainer.innerHTML = `
                    <div class="h-full w-full bg-gradient-to-br from-green-500/90 to-green-600/90 flex items-center justify-center">
                        <div class="text-center text-white p-6">
                            <svg class="w-16 h-16 mx-auto mb-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <h3 class="text-xl font-bold mb-2">${ville}, ${pays}</h3>
                            <p class="text-sm opacity-90">Coordonnées introuvables pour cette localisation</p>
                        </div>
                    </div>
                `;
                return;
            }

            const lat = data[0].lat;
            const lon = data[0].lon;

            mapInstance = L.map('map').setView([lat, lon], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(mapInstance);

            L.marker([lat, lon]).addTo(mapInstance).bindPopup(adresse).openPopup();

        } catch (err) {
            mapContainer.innerHTML = `
                <div class="h-full w-full bg-gradient-to-br from-green-500/90 to-green-600/90 flex items-center justify-center">
                    <div class="text-center text-white p-6">
                        <svg class="w-16 h-16 mx-auto mb-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-xl font-bold mb-2">Erreur de chargement</h3>
                        <p class="text-sm opacity-90">Impossible de charger la carte pour le moment</p>
                    </div>
                </div>
            `;
            console.error('❌ Erreur chargement carte:', err);
        }
    }

    // ==========================================
    // FONCTION UTILITAIRE : VIDER LE CACHE
    // ==========================================
    window.clearAllCache = function () {
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

        loadIndicatifs();
        await loadCountries();

        const paysSelect = document.getElementById('pays');
        if (paysSelect) {
            paysSelect.addEventListener('change', function () {
                const paysMode = document.getElementById('pays_mode');

                if (paysMode && paysMode.value === 'select') {
                    updateCities('pays', 'ville');
                }
            });
        }

        setupCountrySelector('pays_recherche', selectedPaysRecherche);
        setupCountrySelector('autorisations_travail', selectedAutorisations);

        initMap();
    });
</script>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>