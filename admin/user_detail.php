<?php
require_once '../includes/config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($userId <= 0) {
    $_SESSION['error'] = "ID utilisateur invalide.";
    header('Location: /admin/users.php');
    exit;
}

// Récupérer les informations de base de l'utilisateur
$stmtUser = $pdo->prepare("
    SELECT id, email, role, created_at, is_active, email_verified, stripe_customer_id
    FROM users 
    WHERE id = ?
");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé.";
    header('Location: /admin/users.php');
    exit;
}

$profileData = null;
$stripeSubscription = null;
$stripeCustomer = null;
$cvCredits = null;

// Récupérer les données spécifiques selon le rôle
if ($user['role'] === 'candidat') {
    $stmtProfile = $pdo->prepare("SELECT * FROM candidats WHERE id = ?");
    $stmtProfile->execute([$userId]);
    $profileData = $stmtProfile->fetch(PDO::FETCH_ASSOC);
} elseif ($user['role'] === 'recruteur') {
    $stmtProfile = $pdo->prepare("SELECT * FROM recruteurs WHERE id = ?");
    $stmtProfile->execute([$userId]);
    $profileData = $stmtProfile->fetch(PDO::FETCH_ASSOC);

    // Récupérer les crédits CV
    $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining, updated_at FROM user_cv_credits WHERE user_id = ?");
    $stmtCredits->execute([$userId]);
    $cvCredits = $stmtCredits->fetch(PDO::FETCH_ASSOC);

    // Récupérer les infos Stripe si le recruteur a un customer_id
    if (!empty($user['stripe_customer_id'])) {
        try {
            $stripeCustomer = \Stripe\Customer::retrieve($user['stripe_customer_id']);
            
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $user['stripe_customer_id'],
                'status' => 'all',
                'limit' => 1
            ]);
            
            if (count($subscriptions->data) > 0) {
                $stripeSubscription = $subscriptions->data[0];
            }
        } catch (\Exception $e) {
            error_log("Erreur Stripe: " . $e->getMessage());
        }
    }
}

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle is_active
    if (isset($_POST['toggle_active'])) {
        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['success'] = $newStatus ? "Utilisateur activé." : "Utilisateur désactivé.";
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }

    // Toggle email_verified
    if (isset($_POST['toggle_email_verified'])) {
        $newStatus = $user['email_verified'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET email_verified = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['success'] = $newStatus ? "Email marqué comme vérifié." : "Email marqué comme non vérifié.";
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }

    // Mise à jour des crédits CV
    if (isset($_POST['update_cv_credits'])) {
        $newCredits = (int) $_POST['cv_credits'];

        $stmtCheck = $pdo->prepare("SELECT user_id FROM user_cv_credits WHERE user_id = ?");
        $stmtCheck->execute([$userId]);

        if ($stmtCheck->fetch()) {
            $stmt = $pdo->prepare("UPDATE user_cv_credits SET cv_credits_remaining = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$newCredits, $userId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_cv_credits (user_id, cv_credits_remaining, updated_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $newCredits]);
        }

        $_SESSION['success'] = "Crédits CV mis à jour avec succès.";
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }

    // Suppression de fichier (photo, CV, diplôme, reconnaissance)
    if (isset($_POST['delete_file'])) {
        $fileField = $_POST['file_field'];
        $table = $user['role'] === 'candidat' ? 'candidats' : 'recruteurs';

        if (in_array($fileField, ['photo', 'cv', 'diplome', 'reconnaissance'])) {
            $stmtGetFile = $pdo->prepare("SELECT $fileField FROM $table WHERE id = ?");
            $stmtGetFile->execute([$userId]);
            $fileData = $stmtGetFile->fetch(PDO::FETCH_ASSOC);

            if (!empty($fileData[$fileField])) {
                $filePath = __DIR__ . '/../uploads' . $fileData[$fileField];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Supprimer aussi la version floutée si c'est une photo
                if ($fileField === 'photo') {
                    $stmtGetBlurred = $pdo->prepare("SELECT photo_blurred FROM $table WHERE id = ?");
                    $stmtGetBlurred->execute([$userId]);
                    $blurredData = $stmtGetBlurred->fetch(PDO::FETCH_ASSOC);
                    
                    if (!empty($blurredData['photo_blurred'])) {
                        $blurredPath = __DIR__ . '/../uploads' . $blurredData['photo_blurred'];
                        if (file_exists($blurredPath)) {
                            unlink($blurredPath);
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE $table SET photo = NULL, photo_blurred = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE $table SET $fileField = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                }
            }

            $_SESSION['success'] = "Fichier supprimé avec succès.";
        }

        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }

    // Upload de fichier
    if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
        $fileField = $_POST['file_field'];
        $table = $user['role'] === 'candidat' ? 'candidats' : 'recruteurs';

        if (in_array($fileField, ['photo', 'cv', 'diplome', 'reconnaissance'])) {
            $uploadDir = __DIR__ . '/../uploads/' . $user['role'] . 's/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = strtolower(pathinfo($_FILES['upload_file']['name'], PATHINFO_EXTENSION));
            
            // ✅ Générer un UUID unique et imprévisible
            $fileUuid = bin2hex(random_bytes(16));
            $filename = "{$fileUuid}.{$extension}";
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $filepath)) {
                $relativePath = '/' . $user['role'] . 's/' . $filename;
                
                // Si c'est une photo, créer la version floutée
                if ($fileField === 'photo' && in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
                    // ✅ UUID séparé pour l'image floutée
                    $blurredUuid = bin2hex(random_bytes(16));
                    $blurredFilename = "{$blurredUuid}.{$extension}";
                    $blurredPath = $uploadDir . $blurredFilename;
                    $blurredRelativePath = '/' . $user['role'] . 's/' . $blurredFilename;
                    
                    $image = match($extension) {
                        'png' => imagecreatefrompng($filepath),
                        'webp' => imagecreatefromwebp($filepath),
                        default => imagecreatefromjpeg($filepath)
                    };
                    
                    // Corriger l'orientation EXIF
                    if (in_array($extension, ['jpg', 'jpeg'])) {
                        $exif = @exif_read_data($filepath);
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
                    
                    // Technique de flou extrême
                    $tinyWidth = max(1, (int)($width * 0.02));
                    $tinyHeight = max(1, (int)($height * 0.02));
                    
                    $tiny = imagecreatetruecolor($tinyWidth, $tinyHeight);
                    
                    if ($extension === 'png') {
                        imagealphablending($tiny, false);
                        imagesavealpha($tiny, true);
                        imagealphablending($image, true);
                    }
                    
                    imagecopyresampled($tiny, $image, 0, 0, 0, 0, $tinyWidth, $tinyHeight, $width, $height);
                    
                    $blurred = imagecreatetruecolor($width, $height);
                    
                    if ($extension === 'png') {
                        imagealphablending($blurred, false);
                        imagesavealpha($blurred, true);
                    }
                    
                    imagecopyresampled($blurred, $tiny, 0, 0, 0, 0, $width, $height, $tinyWidth, $tinyHeight);
                    
                    for($i = 0; $i < 3; $i++) {
                        imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
                    }
                    
                    match($extension) {
                        'png' => imagepng($blurred, $blurredPath),
                        'webp' => imagewebp($blurred, $blurredPath, 85),
                        default => imagejpeg($blurred, $blurredPath, 85)
                    };
                    
                    imagedestroy($image);
                    imagedestroy($tiny);
                    imagedestroy($blurred);
                    
                    $stmt = $pdo->prepare("UPDATE $table SET photo = ?, photo_blurred = ? WHERE id = ?");
                    $stmt->execute([$relativePath, $blurredRelativePath, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE $table SET $fileField = ? WHERE id = ?");
                    $stmt->execute([$relativePath, $userId]);
                }
                
                $_SESSION['success'] = "Fichier uploadé avec succès.";
            }
        }

        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }

    // Mise à jour d'un champ individuel
    if (isset($_POST['update_field'])) {
        $fieldName = $_POST['field_name'];
        $fieldValue = $_POST['field_value'] ?? '';
        
        $table = $user['role'] === 'candidat' ? 'candidats' : 'recruteurs';
        
        // Liste des champs autorisés pour candidat
        $candidatFields = [
            'numero_reference', 'prenom', 'nom', 'fonction', 'specialite',
            'adresse', 'code_postal', 'ville', 'latitude', 'longitude', 'pays',
            'telephone', 'telephone_indicatif', 'delai_preavis', 'diplome_specialite',
            'pays_recherche', 'autorisations_travail', 'motivations', 'adresse_etablissement'
        ];
        
        // Liste des champs autorisés pour recruteur
        $recruteurFields = [
            'numero_reference', 'prenom', 'nom', 'etablissement', 'fonction',
            'adresse', 'code_postal', 'ville', 'latitude', 'longitude', 'pays',
            'telephone', 'telephone_indicatif', 'adresse_etablissement',
            'code_postal_etablissement', 'ville_etablissement', 'pays_etablissement'
        ];
        
        $allowedFields = $user['role'] === 'candidat' ? $candidatFields : $recruteurFields;
        
        if (in_array($fieldName, $allowedFields)) {
            // Gérer les champs de type array (JSON)
            if (in_array($fieldName, ['pays_recherche', 'autorisations_travail'])) {
                // Si c'est déjà un JSON valide, le garder tel quel, sinon le convertir
                $decoded = json_decode($fieldValue, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $fieldValue = $fieldValue; // Déjà en JSON
                } else {
                    // Convertir une chaîne simple en array
                    $fieldValue = json_encode([$fieldValue]);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE $table SET $fieldName = ? WHERE id = ?");
            $stmt->execute([$fieldValue, $userId]);
            
            $_SESSION['success'] = "Champ mis à jour avec succès.";
        } else {
            $_SESSION['error'] = "Champ non autorisé.";
        }
        
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }
    
    // Ajouter un élément à un array (pays_recherche ou autorisations_travail)
    if (isset($_POST['add_to_array'])) {
        $fieldName = $_POST['field_name'];
        $newValue = trim($_POST['new_value'] ?? '');
        
        if (!empty($newValue) && in_array($fieldName, ['pays_recherche', 'autorisations_travail'])) {
            $table = 'candidats';
            
            // Récupérer la valeur actuelle
            $stmt = $pdo->prepare("SELECT $fieldName FROM $table WHERE id = ?");
            $stmt->execute([$userId]);
            $currentValue = $stmt->fetchColumn();
            
            // Décoder le JSON
            $array = json_decode($currentValue, true) ?? [];
            
            // Ajouter le nouveau élément s'il n'existe pas déjà
            if (!in_array($newValue, $array)) {
                $array[] = $newValue;
                
                // Mettre à jour
                $stmt = $pdo->prepare("UPDATE $table SET $fieldName = ? WHERE id = ?");
                $stmt->execute([json_encode($array), $userId]);
                
                $_SESSION['success'] = "Élément ajouté avec succès.";
            } else {
                $_SESSION['info'] = "Cet élément existe déjà.";
            }
        }
        
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }
    
    // Supprimer un élément d'un array
    if (isset($_POST['remove_from_array'])) {
        $fieldName = $_POST['field_name'];
        $valueToRemove = $_POST['value_to_remove'];
        
        if (in_array($fieldName, ['pays_recherche', 'autorisations_travail'])) {
            $table = 'candidats';
            
            // Récupérer la valeur actuelle
            $stmt = $pdo->prepare("SELECT $fieldName FROM $table WHERE id = ?");
            $stmt->execute([$userId]);
            $currentValue = $stmt->fetchColumn();
            
            // Décoder le JSON
            $array = json_decode($currentValue, true) ?? [];
            
            // Supprimer l'élément
            $array = array_values(array_filter($array, fn($item) => $item !== $valueToRemove));
            
            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE $table SET $fieldName = ? WHERE id = ?");
            $stmt->execute([json_encode($array), $userId]);
            
            $_SESSION['success'] = "Élément supprimé avec succès.";
        }
        
        header("Location: /admin/user_detail.php?id=$userId");
        exit;
    }
}

$title = "Détails de l'utilisateur";
ob_start();
?>

<style>
.tag {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    background-color: #10b981;
    color: white;
    border-radius: 9999px;
    font-size: 0.875rem;
    margin: 0.25rem;
}

.tag button {
    margin-left: 0.5rem;
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-weight: bold;
    opacity: 0.8;
    transition: opacity 0.2s;
}

.tag button:hover {
    opacity: 1;
}

.field-group {
    position: relative;
}

.field-save-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
}

.textarea-wrapper {
    position: relative;
}

.textarea-save-btn {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
}
</style>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- En-tête avec actions -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="/admin/users.php" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    <?= htmlspecialchars($profileData['prenom'] ?? 'Utilisateur') ?>
                    <?= htmlspecialchars($profileData['nom'] ?? '') ?>
                </h1>
                <p class="text-sm text-gray-500">
                    ID: <?= $user['id'] ?> •
                    <span class="capitalize"><?= htmlspecialchars($user['role']) ?></span>
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Toggle Email Vérifié -->
            <form method="post" class="inline">
                <button type="submit" name="toggle_email_verified"
                    class="px-4 py-2 text-sm <?= $user['email_verified'] ? 'bg-blue-600 hover:bg-blue-700' : 'bg-orange-600 hover:bg-orange-700' ?> text-white rounded-lg transition-colors">
                    <i class="fas fa-envelope<?= $user['email_verified'] ? '-circle-check' : '' ?> mr-2"></i>
                    <?= $user['email_verified'] ? 'Vérifié' : 'Non vérifié' ?>
                </button>
            </form>

            <!-- Toggle Actif/Inactif -->
            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                <form method="post" class="inline" onsubmit="return confirm('Confirmer le changement de statut ?');">
                    <button type="submit" name="toggle_active"
                        class="px-4 py-2 text-sm <?= $user['is_active'] ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' ?> text-white rounded-lg transition-colors">
                        <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check-circle' ?> mr-2"></i>
                        <?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informations de compte -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-user-circle mr-2 text-green-600"></i>
            Informations de compte
        </h2>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <div class="text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
                <?php
                $roleColors = [
                    'admin' => 'bg-red-100 text-red-800',
                    'candidat' => 'bg-blue-100 text-blue-800',
                    'recruteur' => 'bg-purple-100 text-purple-800'
                ];
                $roleColor = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?= $roleColor ?>">
                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                </span>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date d'inscription</label>
                <div class="text-gray-900"><?= date('d/m/Y à H:i', strtotime($user['created_at'])) ?></div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut du compte</label>
                <div>
                    <?php if ($user['is_active']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>Actif
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-800">
                            <i class="fas fa-ban mr-2"></i>Inactif
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Abonnement Stripe (uniquement pour recruteurs) -->
    <?php if ($user['role'] === 'recruteur' && $stripeSubscription): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fab fa-stripe mr-2 text-purple-600"></i>
                Abonnement Stripe
            </h2>
            
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Note importante :</strong> La gestion des crédits CV ne concerne que les recruteurs ayant souscrit à la formule <strong>Essentielle</strong>.
                            Les autres formules (Premium, etc.) bénéficient de crédits CV illimités.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Client Stripe</label>
                    <div class="text-gray-900 font-mono text-xs break-all"><?= htmlspecialchars($user['stripe_customer_id']) ?></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Abonnement</label>
                    <div class="text-gray-900 font-mono text-xs break-all"><?= htmlspecialchars($stripeSubscription->id) ?></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Formule</label>
                    <div class="text-gray-900 font-semibold">
                        <?php
                        $planName = 'Non défini';
                        if (isset($stripeSubscription->items->data[0]->price)) {
                            $price = $stripeSubscription->items->data[0]->price;
                            if (!empty($price->nickname)) {
                                $planName = $price->nickname;
                            } elseif (!empty($price->product)) {
                                try {
                                    $product = \Stripe\Product::retrieve($price->product);
                                    $planName = $product->name;
                                } catch (\Exception $e) {
                                    $planName = 'Premium';
                                }
                            }
                        }
                        echo htmlspecialchars($planName);
                        ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Montant</label>
                    <div class="text-gray-900 font-semibold">
                        <?php
                        if (isset($stripeSubscription->items->data[0]->price)) {
                            $amount = $stripeSubscription->items->data[0]->price->unit_amount / 100;
                            $currency = strtoupper($stripeSubscription->items->data[0]->price->currency);
                            echo number_format($amount, 2, ',', ' ') . ' ' . $currency;

                            $interval = $stripeSubscription->items->data[0]->price->recurring->interval ?? 'month';
                            $intervalCount = $stripeSubscription->items->data[0]->price->recurring->interval_count ?? 1;

                            if ($intervalCount > 1) {
                                echo " / $intervalCount ";
                            } else {
                                echo " / ";
                            }

                            if ($interval === 'month') {
                                echo $intervalCount > 1 ? 'mois' : 'mois';
                            } elseif ($interval === 'year') {
                                echo $intervalCount > 1 ? 'ans' : 'an';
                            }
                        }
                        ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <?php
                    $statusColors = [
                        'active' => 'bg-green-100 text-green-800',
                        'trialing' => 'bg-blue-100 text-blue-800',
                        'past_due' => 'bg-orange-100 text-orange-800',
                        'canceled' => 'bg-red-100 text-red-800',
                        'unpaid' => 'bg-red-100 text-red-800',
                        'incomplete' => 'bg-yellow-100 text-yellow-800'
                    ];
                    $statusColor = $statusColors[$stripeSubscription->status] ?? 'bg-gray-100 text-gray-800';

                    $statusLabels = [
                        'active' => 'Actif',
                        'trialing' => 'Essai',
                        'past_due' => 'Paiement en retard',
                        'canceled' => 'Annulé',
                        'unpaid' => 'Impayé',
                        'incomplete' => 'Incomplet'
                    ];
                    $statusLabel = $statusLabels[$stripeSubscription->status] ?? ucfirst($stripeSubscription->status);
                    ?>
                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?= $statusColor ?>">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Créé le</label>
                    <div class="text-gray-900"><?= date('d/m/Y à H:i', $stripeSubscription->created) ?></div>
                </div>

                <?php if ($stripeSubscription->cancel_at_period_end): ?>
                    <div class="col-span-2">
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                            <p class="text-sm text-orange-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                L'abonnement sera annulé le <?= date('d/m/Y', $stripeSubscription->current_period_end) ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($stripeSubscription->canceled_at): ?>
                    <div class="col-span-2">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-sm text-red-800">
                                <i class="fas fa-times-circle mr-2"></i>
                                Abonnement annulé le <?= date('d/m/Y à H:i', $stripeSubscription->canceled_at) ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($user['role'] === 'recruteur' && empty($user['stripe_customer_id'])): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fab fa-stripe mr-2 text-purple-600"></i>
                Abonnement Stripe
            </h2>
            <div class="bg-gray-50 rounded-lg p-4 text-center">
                <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                <p class="text-gray-600">Aucun abonnement Stripe associé</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Crédits CV (pour recruteurs avec formule Essentielle) -->
    <?php if ($user['role'] === 'recruteur'): ?>
        <?php
        $hasUnlimitedCredits = false;
        $planName = 'Non défini';

        if ($stripeSubscription && isset($stripeSubscription->items->data[0]->price)) {
            $price = $stripeSubscription->items->data[0]->price;

            if (!empty($price->nickname)) {
                $planName = $price->nickname;
            } elseif (!empty($price->product)) {
                try {
                    $product = \Stripe\Product::retrieve($price->product);
                    $planName = $product->name;
                } catch (\Exception $e) {
                    $planName = 'Premium';
                }
            }

            if (stripos($planName, 'essentielle') === false) {
                $hasUnlimitedCredits = true;
            }
        }
        ?>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-file-alt mr-2 text-green-600"></i>
                Crédits CV
            </h2>

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Note :</strong> La gestion des crédits CV ne concerne que les recruteurs ayant souscrit à la formule <strong>Essentielle</strong>.
                            Les autres formules (Premium, etc.) bénéficient de crédits CV illimités.
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($hasUnlimitedCredits): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6 text-center">
                    <div class="flex items-center justify-center mb-3">
                        <i class="fas fa-infinity text-green-600 text-4xl"></i>
                    </div>
                    <p class="text-lg font-semibold text-green-900 mb-1">Crédits CV illimités</p>
                    <p class="text-sm text-green-700">
                        Ce recruteur bénéficie d'un accès illimité aux CV grâce à sa formule <strong><?= htmlspecialchars($planName) ?></strong>.
                    </p>
                </div>
            <?php else: ?>
                <?php if ($cvCredits): ?>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Crédits restants</p>
                            <p class="text-3xl font-bold text-green-600"><?= $cvCredits['cv_credits_remaining'] ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                Dernière mise à jour : <?= date('d/m/Y à H:i', strtotime($cvCredits['updated_at'])) ?>
                            </p>
                            <?php if (!empty($stripeSubscription)): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    Formule : <span class="font-medium"><?= htmlspecialchars($planName) ?></span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <form method="post" class="flex items-center space-x-3">
                            <input type="number" name="cv_credits" value="<?= $cvCredits['cv_credits_remaining'] ?>" min="0"
                                class="w-24 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            <button type="submit" name="update_cv_credits"
                                class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i class="fas fa-save mr-2"></i>Modifier
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-orange-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Aucun crédit CV configuré pour ce recruteur (formule Essentielle)
                        </p>
                    </div>

                    <form method="post" class="flex items-center space-x-3">
                        <label class="text-sm font-medium text-gray-700">Initialiser avec :</label>
                        <input type="number" name="cv_credits" value="0" min="0"
                            class="w-24 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                        <button type="submit" name="update_cv_credits"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Créer
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($profileData && $user['role'] !== 'admin'): ?>

        <!-- Photo de profil -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-camera mr-2 text-green-600"></i>
                Photo de profil
            </h2>

            <div class="flex items-start space-x-6">
                <?php if (!empty($profileData['photo'])): ?>
                    <div class="flex-shrink-0">
                        <img src="/uploads/<?= htmlspecialchars($profileData['photo']) ?>" alt="Photo de profil"
                            class="w-32 h-32 rounded-lg object-cover border-2 border-gray-200">
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-3">Photo actuelle</p>
                        <form method="post" class="inline">
                            <input type="hidden" name="file_field" value="photo">
                            <button type="submit" name="delete_file"
                                class="px-4 py-2 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
                                onclick="return confirm('Supprimer cette photo ?');">
                                <i class="fas fa-trash mr-2"></i>Supprimer
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-shrink-0 w-32 h-32 rounded-lg bg-gray-100 flex items-center justify-center">
                        <i class="fas fa-user text-4xl text-gray-400"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-3">Aucune photo</p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="file_field" value="photo">
                            <div class="flex items-center space-x-3">
                                <input type="file" name="upload_file" accept="image/*"
                                    class="text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                                <button type="submit"
                                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                                    <i class="fas fa-upload mr-2"></i>Uploader
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profil CANDIDAT -->
        <?php if ($user['role'] === 'candidat'): ?>

            <!-- Informations personnelles -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-id-card mr-2 text-green-600"></i>
                    Informations personnelles
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Numéro de référence -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de référence</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="numero_reference">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['numero_reference'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Fonction -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fonction</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="fonction">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['fonction'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Prénom -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="prenom">
                            <input type="text" name="field_value" required
                                value="<?= htmlspecialchars($profileData['prenom'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Nom -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="nom">
                            <input type="text" name="field_value" required
                                value="<?= htmlspecialchars($profileData['nom'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Spécialité -->
                    <div class="col-span-2 field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Spécialité</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="specialite">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['specialite'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-file-alt mr-2 text-green-600"></i>
                    Documents
                </h2>

                <div class="space-y-4">
                    <!-- CV -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                            CV
                        </h3>
                        <?php if (!empty($profileData['cv'])): ?>
                            <div class="flex items-center justify-between">
                                <a href="/uploads/<?= htmlspecialchars($profileData['cv']) ?>" target="_blank"
                                    class="text-sm text-green-600 hover:text-green-700 flex items-center">
                                    <i class="fas fa-download mr-2"></i>Télécharger le CV
                                </a>
                                <form method="post" class="inline">
                                    <input type="hidden" name="file_field" value="cv">
                                    <button type="submit" name="delete_file"
                                        class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
                                        onclick="return confirm('Supprimer ce fichier ?');">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="file_field" value="cv">
                                <div class="flex items-center space-x-3">
                                    <input type="file" name="upload_file" accept=".pdf,.doc,.docx"
                                        class="text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-green-50 file:text-green-700">
                                    <button type="submit"
                                        class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <i class="fas fa-upload mr-1"></i>Uploader
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Diplôme -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                            Diplôme
                        </h3>
                        <?php if (!empty($profileData['diplome'])): ?>
                            <div class="flex items-center justify-between mb-3">
                                <a href="/uploads/<?= htmlspecialchars($profileData['diplome']) ?>" target="_blank"
                                    class="text-sm text-green-600 hover:text-green-700 flex items-center">
                                    <i class="fas fa-download mr-2"></i>Télécharger le diplôme
                                </a>
                                <form method="post" class="inline">
                                    <input type="hidden" name="file_field" value="diplome">
                                    <button type="submit" name="delete_file"
                                        class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
                                        onclick="return confirm('Supprimer ce fichier ?');">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data" class="mb-3">
                                <input type="hidden" name="file_field" value="diplome">
                                <div class="flex items-center space-x-3">
                                    <input type="file" name="upload_file" accept=".pdf,.jpg,.jpeg,.png"
                                        class="text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-green-50 file:text-green-700">
                                    <button type="submit"
                                        class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <i class="fas fa-upload mr-1"></i>Uploader
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Spécialité du diplôme -->
                        <div class="field-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Spécialité du diplôme</label>
                            <form method="post" class="relative">
                                <input type="hidden" name="update_field" value="1">
                                <input type="hidden" name="field_name" value="diplome_specialite">
                                <input type="text" name="field_value"
                                    value="<?= htmlspecialchars($profileData['diplome_specialite'] ?? '') ?>"
                                    class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Reconnaissance -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-certificate mr-2 text-yellow-600"></i>
                            Reconnaissance
                        </h3>
                        <?php if (!empty($profileData['reconnaissance'])): ?>
                            <div class="flex items-center justify-between">
                                <a href="/uploads/<?= htmlspecialchars($profileData['reconnaissance']) ?>" target="_blank"
                                    class="text-sm text-green-600 hover:text-green-700 flex items-center">
                                    <i class="fas fa-download mr-2"></i>Télécharger la reconnaissance
                                </a>
                                <form method="post" class="inline">
                                    <input type="hidden" name="file_field" value="reconnaissance">
                                    <button type="submit" name="delete_file"
                                        class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
                                        onclick="return confirm('Supprimer ce fichier ?');">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="file_field" value="reconnaissance">
                                <div class="flex items-center space-x-3">
                                    <input type="file" name="upload_file" accept=".pdf,.jpg,.jpeg,.png"
                                        class="text-sm text-gray-600 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-green-50 file:text-green-700">
                                    <button type="submit"
                                        class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">
                                        <i class="fas fa-upload mr-1"></i>Uploader
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coordonnées -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>
                    Coordonnées
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Adresse -->
                    <div class="col-span-2 field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="adresse">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['adresse'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Code postal -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="code_postal">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['code_postal'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Ville -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="ville">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['ville'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Pays -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="pays">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['pays'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Téléphone -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                        <div class="flex space-x-2">
                            <form method="post" class="relative w-24">
                                <input type="hidden" name="update_field" value="1">
                                <input type="hidden" name="field_name" value="telephone_indicatif">
                                <input type="text" name="field_value" placeholder="+33"
                                    value="<?= htmlspecialchars($profileData['telephone_indicatif'] ?? '') ?>"
                                    class="w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <button type="submit" class="absolute right-1 top-1/2 transform -translate-y-1/2 text-green-600 hover:text-green-700 text-xs">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                            <form method="post" class="relative flex-1">
                                <input type="hidden" name="update_field" value="1">
                                <input type="hidden" name="field_name" value="telephone">
                                <input type="text" name="field_value"
                                    value="<?= htmlspecialchars($profileData['telephone'] ?? '') ?>"
                                    class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations professionnelles -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-briefcase mr-2 text-green-600"></i>
                    Informations professionnelles
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Délai de préavis -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Délai de préavis</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="delai_preavis">
                            <select name="field_value"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                onchange="this.form.submit()">
                                <option value="0" <?= ($profileData['delai_preavis'] ?? '') == '0' ? 'selected' : '' ?>>Immédiat</option>
                                <option value="1" <?= ($profileData['delai_preavis'] ?? '') == '1' ? 'selected' : '' ?>>1 mois</option>
                                <option value="2" <?= ($profileData['delai_preavis'] ?? '') == '2' ? 'selected' : '' ?>>2 mois</option>
                                <option value="3" <?= ($profileData['delai_preavis'] ?? '') == '3' ? 'selected' : '' ?>>3 mois</option>
                                <option value="4" <?= ($profileData['delai_preavis'] ?? '') == '4' ? 'selected' : '' ?>>4 mois</option>
                                <option value="5" <?= ($profileData['delai_preavis'] ?? '') == '5' ? 'selected' : '' ?>>5 mois</option>
                                <option value="6" <?= ($profileData['delai_preavis'] ?? '') == '6' ? 'selected' : '' ?>>6 mois</option>
                            </select>
                        </form>
                    </div>

                    <!-- Adresse établissement actuel -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse établissement actuel</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="adresse_etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['adresse_etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recherche et motivations -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-search mr-2 text-green-600"></i>
                    Recherche et motivations
                </h2>

                <div class="space-y-6">
                    <!-- Pays recherchés -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays recherchés</label>
                        
                        <?php
                        $paysRecherche = json_decode($profileData['pays_recherche'] ?? '[]', true) ?? [];
                        ?>
                        
                        <!-- Affichage des tags -->
                        <div class="flex flex-wrap mb-3">
                            <?php if (!empty($paysRecherche)): ?>
                                <?php foreach ($paysRecherche as $pays): ?>
                                    <span class="tag">
                                        <?= htmlspecialchars($pays) ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="remove_from_array" value="1">
                                            <input type="hidden" name="field_name" value="pays_recherche">
                                            <input type="hidden" name="value_to_remove" value="<?= htmlspecialchars($pays) ?>">
                                            <button type="submit">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-sm text-gray-500 italic">Aucun pays ajouté</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire d'ajout -->
                        <form method="post" class="flex items-center space-x-2">
                            <input type="hidden" name="add_to_array" value="1">
                            <input type="hidden" name="field_name" value="pays_recherche">
                            <input type="text" name="new_value" placeholder="Ajouter un pays..."
                                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit"
                                class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 whitespace-nowrap">
                                <i class="fas fa-plus mr-1"></i>Ajouter
                            </button>
                        </form>
                    </div>

                    <!-- Autorisations de travail -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Autorisations de travail</label>
                        
                        <?php
                        $autorisationsTravail = json_decode($profileData['autorisations_travail'] ?? '[]', true) ?? [];
                        ?>
                        
                        <!-- Affichage des tags -->
                        <div class="flex flex-wrap mb-3">
                            <?php if (!empty($autorisationsTravail)): ?>
                                <?php foreach ($autorisationsTravail as $autorisation): ?>
                                    <span class="tag">
                                        <?= htmlspecialchars($autorisation) ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="remove_from_array" value="1">
                                            <input type="hidden" name="field_name" value="autorisations_travail">
                                            <input type="hidden" name="value_to_remove" value="<?= htmlspecialchars($autorisation) ?>">
                                            <button type="submit">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-sm text-gray-500 italic">Aucune autorisation ajoutée</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire d'ajout -->
                        <form method="post" class="flex items-center space-x-2">
                            <input type="hidden" name="add_to_array" value="1">
                            <input type="hidden" name="field_name" value="autorisations_travail">
                            <input type="text" name="new_value" placeholder="Ajouter une autorisation..."
                                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit"
                                class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 whitespace-nowrap">
                                <i class="fas fa-plus mr-1"></i>Ajouter
                            </button>
                        </form>
                    </div>

                    <!-- Motivations -->
                    <div class="textarea-wrapper">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivations</label>
                        <form method="post">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="motivations">
                            <textarea name="field_value" rows="4"
                                class="w-full px-3 py-2 pb-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"><?= htmlspecialchars($profileData['motivations'] ?? '') ?></textarea>
                            <button type="submit" class="textarea-save-btn px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                <i class="fas fa-save mr-1"></i>Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <!-- Profil RECRUTEUR -->
        <?php if ($user['role'] === 'recruteur'): ?>

            <!-- Informations personnelles -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-id-card mr-2 text-green-600"></i>
                    Informations personnelles
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Numéro de référence -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de référence</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="numero_reference">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['numero_reference'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Établissement -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Établissement</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Prénom -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="prenom">
                            <input type="text" name="field_value" required
                                value="<?= htmlspecialchars($profileData['prenom'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Nom -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="nom">
                            <input type="text" name="field_value" required
                                value="<?= htmlspecialchars($profileData['nom'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Fonction -->
                    <div class="col-span-2 field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fonction</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="fonction">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['fonction'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Coordonnées personnelles -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>
                    Coordonnées personnelles
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Adresse -->
                    <div class="col-span-2 field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="adresse">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['adresse'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Code postal -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="code_postal">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['code_postal'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Ville -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="ville">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['ville'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Pays -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="pays">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['pays'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Téléphone -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                        <div class="flex space-x-2">
                            <form method="post" class="relative w-24">
                                <input type="hidden" name="update_field" value="1">
                                <input type="hidden" name="field_name" value="telephone_indicatif">
                                <input type="text" name="field_value" placeholder="+33"
                                    value="<?= htmlspecialchars($profileData['telephone_indicatif'] ?? '') ?>"
                                    class="w-full px-3 py-2 pr-8 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <button type="submit" class="absolute right-1 top-1/2 transform -translate-y-1/2 text-green-600 hover:text-green-700 text-xs">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                            <form method="post" class="relative flex-1">
                                <input type="hidden" name="update_field" value="1">
                                <input type="hidden" name="field_name" value="telephone">
                                <input type="text" name="field_value"
                                    value="<?= htmlspecialchars($profileData['telephone'] ?? '') ?>"
                                    class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coordonnées de l'établissement -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-building mr-2 text-green-600"></i>
                    Coordonnées de l'établissement
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Adresse établissement -->
                    <div class="col-span-2 field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse établissement</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="adresse_etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['adresse_etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Code postal établissement -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Code postal</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="code_postal_etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['code_postal_etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Ville établissement -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="ville_etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['ville_etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Pays établissement -->
                    <div class="field-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays</label>
                        <form method="post" class="relative">
                            <input type="hidden" name="update_field" value="1">
                            <input type="hidden" name="field_name" value="pays_etablissement">
                            <input type="text" name="field_value"
                                value="<?= htmlspecialchars($profileData['pays_etablissement'] ?? '') ?>"
                                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <button type="submit" class="field-save-btn text-green-600 hover:text-green-700">
                                <i class="fas fa-save"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <!-- Bouton retour -->
    <div class="flex justify-start">
        <a href="/admin/users.php"
            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
        </a>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/layouts/layout_admin.php';
?>