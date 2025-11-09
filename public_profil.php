<?php
require_once './includes/config.php';
use \Stripe\Subscription;
use \Stripe\Stripe;
require 'vendor/autoload.php';

// ==========================================
// VALIDATION ET SÉCURITÉ
// ==========================================

// Validation de la référence
$ref = trim($_GET['ref'] ?? '');
if (empty($ref)) {
    http_response_code(400);
    die('Référence candidat manquante.');
}

// Validation du format de la référence (alphanumérique + tirets uniquement)
if (!preg_match('/^[A-Z0-9\-]+$/i', $ref)) {
    http_response_code(400);
    die('Format de référence invalide.');
}

$recruteurId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

// ==========================================
// RÉCUPÉRATION DES DONNÉES CANDIDAT
// ==========================================

$stmt = $pdo->prepare("
    SELECT u.email, u.created_at, c.*
    FROM candidats c
    JOIN users u ON u.id = c.id
    WHERE c.numero_reference = ?
    LIMIT 1
");
$stmt->execute([$ref]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    die('Candidat non trouvé.');
}

// ==========================================
// STATISTIQUES ET SUIVI
// ==========================================

// Incrémenter les vues
$stmtUpdate = $pdo->prepare("UPDATE candidats SET views = views + 1 WHERE id = ?");
$stmtUpdate->execute([$user['id']]);

// Suivi consultation (uniquement si connecté)
$alreadyConsulted = false;
if ($recruteurId) {
    // INSERT IGNORE pour éviter les doublons
    $stmt = $pdo->prepare("INSERT IGNORE INTO consultations (user_id, candidat_id, consulted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$recruteurId, $user['id']]);
    
    // Vérifier si déjà consulté
    $stmt = $pdo->prepare("SELECT 1 FROM consultations WHERE user_id = ? AND candidat_id = ? LIMIT 1");
    $stmt->execute([$recruteurId, $user['id']]);
    $alreadyConsulted = $stmt->fetchColumn() !== false;
}

// ==========================================
// VÉRIFICATION ABONNEMENT STRIPE
// ==========================================

$stripeSubscriptionId = null;
$subscription = null;
$isSubscriber = false;
$subscriptionName = "";

if ($recruteurId) {
    $stmtUser = $pdo->prepare("SELECT stripe_subscription_id FROM users WHERE id = ?");
    $stmtUser->execute([$recruteurId]);
    $stripeSubscriptionId = $stmtUser->fetchColumn();

    if ($stripeSubscriptionId) {
        try {
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');
            $subscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
            $status = $subscription->status;
            
            if (in_array($status, ['active', 'trialing'], true)) {
                $isSubscriber = true;
                $subscriptionName = $subscription->items->data[0]->price->nickname ?? "";
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Erreur Stripe: " . $e->getMessage());
            $isSubscriber = false;
        } catch (Exception $e) {
            error_log("Erreur inattendue Stripe: " . $e->getMessage());
            $isSubscriber = false;
        }
    }
}

// ==========================================
// GESTION TÉLÉCHARGEMENT DOCUMENTS
// ==========================================

if (isset($_GET['download']) && $isSubscriber) {
    $type = $_GET['download'];
    $allowedDocs = ['cv', 'diplome', 'diplome_specialite', 'reconnaissance'];
    
    // Validation du type de document
    if (!in_array($type, $allowedDocs, true)) {
        http_response_code(400);
        die('Type de document invalide.');
    }
    
    // Vérification que le document existe
    if (empty($user[$type])) {
        http_response_code(404);
        die('Document non disponible.');
    }

    // Gestion des crédits CV pour abonnement Essentielle
    if ($subscriptionName === 'Essentielle' && $type === 'cv') {
        $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
        $stmtCredits->execute([$recruteurId]);
        $credits = (int) $stmtCredits->fetchColumn();
        
        if ($credits <= 0) {
            http_response_code(403);
            die('Crédits CV épuisés. Veuillez upgrader votre abonnement.');
        }

        // Décrémenter les crédits
        $stmtUpdateCredits = $pdo->prepare("UPDATE user_cv_credits SET cv_credits_remaining = cv_credits_remaining - 1 WHERE user_id = ?");
        $stmtUpdateCredits->execute([$recruteurId]);
    }

    // Validation et sécurisation du chemin fichier
    $filename = basename($user[$type]); // Évite les directory traversal
    $filepath = __DIR__ . "/uploads/" . $filename;
    
    // Vérification d'existence et de lisibilité
    if (!file_exists($filepath) || !is_readable($filepath)) {
        http_response_code(404);
        die('Fichier introuvable.');
    }
    
    // Validation que le fichier est bien dans le dossier uploads (sécurité)
    $realPath = realpath($filepath);
    $uploadsPath = realpath(__DIR__ . "/uploads");
    
    if (strpos($realPath, $uploadsPath) !== 0) {
        http_response_code(403);
        die('Accès interdit.');
    }

    // Headers sécurisés pour le téléchargement
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Lecture et envoi du fichier
    readfile($filepath);
    exit;
}

// ==========================================
// RÉCUPÉRATION CRÉDITS CV RESTANTS
// ==========================================

$cvCreditsRemaining = 0;
if ($isSubscriber && $subscriptionName === 'Essentielle') {
    $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
    $stmtCredits->execute([$recruteurId]);
    $cvCreditsRemaining = (int) $stmtCredits->fetchColumn();
}

// ==========================================
// VARIABLES D'AFFICHAGE
// ==========================================

$hasAccess = $isSubscriber;

// Fonction d'échappement sécurisé
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Masquage des données sensibles
function maskName($name) {
    if (empty($name)) return '***';
    $firstChar = mb_substr($name, 0, 1);
    $remaining = max(mb_strlen($name) - 1, 0);
    return h($firstChar) . str_repeat('*', $remaining);
}

$title = "Profil candidat - " . h($user['prenom'] . ' ' . $user['nom']);
ob_start();
?>
<div class="max-w-3xl mx-auto bg-white rounded shadow-md relative p-0 overflow-hidden">
    
    <!-- Bannière : Non abonné (recruteur) -->
    <?php if ($userRole === "recruteur" && $recruteurId && !$isSubscriber): ?>
        <div class="p-4 bg-red-100 text-red-800 font-semibold text-center rounded mb-6">
            Pour accéder aux CV et informations complètes des candidats, activez votre abonnement recruteur !
            <a href="abonnement.php" class="underline font-bold text-red-900 ml-1">Choisir un abonnement</a>
        </div>
    <?php endif; ?>

    <!-- Bannière : Non connecté -->
    <?php if (!$recruteurId): ?>
        <div class="p-4 bg-yellow-100 text-yellow-800 font-semibold text-center rounded mb-6">
            Vous êtes recruteur ? 
            <a href="login.php?role=recruteur" class="underline font-bold text-yellow-900">Connectez-vous ici</a><br />
            Vous êtes candidat ? 
            <a href="register.php" class="underline font-bold text-yellow-900">Créez votre compte</a>
            pour avoir votre profil consultable.
        </div>
    <?php endif; ?>

    <!-- Badge : Déjà consulté -->
    <?php if ($alreadyConsulted): ?>
        <div class="absolute right-0 top-0 z-10">
            <span class="inline-block bg-purple-600 text-white text-xs font-bold px-4 py-1 rounded-bl-xl shadow uppercase tracking-wider" style="border-top-right-radius:0.6rem;">
                Déjà consulté
            </span>
        </div>
    <?php endif; ?>

    <!-- Bannière : Crédits CV restants (Essentielle) -->
    <?php if ($isSubscriber && $subscriptionName === 'Essentielle' && $cvCreditsRemaining >= 0): ?>
        <div class="px-8 pt-6">
            <div class="p-3 bg-gradient-to-r from-orange-50 to-orange-100 border-l-4 border-orange-500 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-download text-orange-600"></i>
                    <span class="text-sm font-semibold text-orange-800">
                        Crédits CV restants : <span class="text-lg font-bold"><?= $cvCreditsRemaining ?></span>
                    </span>
                </div>
                <?php if ($cvCreditsRemaining === 0): ?>
                    <p class="text-xs text-orange-700 mt-1">Vous avez épuisé vos crédits. 
                        <a href="abonnement.php" class="underline font-semibold">Upgrader vers Premium</a> pour un accès illimité.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contenu principal -->
    <div class="flex flex-col md:flex-row items-start gap-6 p-8">
        
        <!-- Photo du candidat -->
        <div class="w-40 h-40 rounded-lg bg-gray-100 border border-gray-300 flex-shrink-0 overflow-hidden flex justify-center items-center shadow">
            <?php if ($hasAccess && !empty($user['photo'])): ?>
                <!-- Photo nette pour abonnés -->
                <img src="uploads/<?= h($user['photo']) ?>"
                    alt="Photo de <?= h($user['prenom'] . ' ' . $user['nom']) ?>"
                    class="w-full h-full object-cover rounded-lg" />
            <?php elseif (!empty($user['photo_blurred'])): ?>
                <!-- Photo floutée pour non-abonnés -->
                <img src="uploads/<?= h($user['photo_blurred']) ?>"
                    alt="Photo floutée"
                    class="w-full h-full object-cover rounded-lg" />
            <?php else: ?>
                <!-- Placeholder si pas de photo -->
                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300">
                    <i class="fas fa-user text-gray-400 text-5xl"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Informations du candidat -->
        <section class="flex-1 space-y-2">
            <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">
                <?= $hasAccess 
                    ? h($user['prenom'] . ' ' . $user['nom'])
                    : maskName($user['prenom']) . ' ' . maskName($user['nom']) ?>
            </h1>
            
            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                <!-- Référence -->
                <div>
                    <div class="text-xs text-gray-400 uppercase">Référence</div>
                    <div class="font-mono text-sm"><?= h($user['numero_reference'] ?? '-') ?></div>
                </div>
                
                <!-- Métier -->
                <div>
                    <div class="text-xs text-gray-400 uppercase">Métier</div>
                    <div class="font-semibold"><?= h($user['fonction']) ?></div>
                </div>
                
                <!-- Pays de recherche -->
                <?php
                $paysRecherche = $user['pays_recherche'] ?? '[]';
                $paysArray = json_decode($paysRecherche, true);
                if (!empty($paysArray) && is_array($paysArray)):
                ?>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Pays de recherche</div>
                        <div><?= h(implode(', ', $paysArray)) ?></div>
                    </div>
                <?php endif; ?>
                
                <!-- Autorisations de travail -->
                <?php
                $autorisationsTravail = $user['autorisations_travail'] ?? '[]';
                $autorisationsArray = json_decode($autorisationsTravail, true);
                if (!empty($autorisationsArray) && is_array($autorisationsArray)):
                ?>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Autorisations de travail</div>
                        <div><?= h(implode(', ', $autorisationsArray)) ?></div>
                    </div>
                <?php endif; ?>
                
                <!-- Date d'inscription -->
                <div>
                    <div class="text-xs text-gray-400 uppercase">Date d'inscription</div>
                    <div>
                        <?php
                        if (!empty($user['created_at'])) {
                            try {
                                $date = new DateTime($user['created_at']);
                                echo $date->format('d/m/Y');
                            } catch (Exception $e) {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Délai de préavis -->
                <?php if (isset($user['delai_preavis']) && $user['delai_preavis'] !== null && $user['delai_preavis'] !== ''): ?>
                    <div>
                        <div class="text-xs text-gray-400 uppercase">Délai de préavis</div>
                        <div><?= h($user['delai_preavis']) ?> mois</div>
                    </div>
                <?php endif; ?>
                
                <!-- Email (masqué si pas d'accès) -->
                <div>
                    <div class="text-xs text-gray-400 uppercase">Email</div>
                    <div class="break-all w-full text-sm">
                        <?= $hasAccess ? h($user['email'] ?? '-') : '****@****.***' ?>
                    </div>
                </div>
                
                <!-- Ville, pays (masqué si pas d'accès) -->
                <div>
                    <div class="text-xs text-gray-400 uppercase">Ville, pays</div>
                    <div class="break-all w-full text-sm">
                        <?= $hasAccess
                            ? h(trim(($user['ville'] ?? '-') . ', ' . ($user['pays'] ?? '-')))
                            : '****, ****' ?>
                    </div>
                </div>
            </div>
            
            <!-- Motivations -->
            <?php if (!empty($user['motivations'])): ?>
                <div class="pt-3">
                    <div class="text-xs text-gray-400 uppercase mb-1">Motivations</div>
                    <div class="text-gray-700">
                        <?= $hasAccess ? nl2br(h($user['motivations'])) : '<span class="text-gray-400">Accessible avec un abonnement</span>' ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Section Documents -->
    <div class="px-8 pb-6">
        <h2 class="text-lg font-semibold mt-2 mb-3 flex items-center gap-2">
            <i class="fas fa-file-alt text-green-600"></i>
            Documents
        </h2>
        
        <ul class="flex flex-wrap gap-3">
            <?php
            $docs = [
                'cv' => ['label' => 'CV', 'icon' => 'file-pdf'],
                'diplome' => ['label' => 'Diplôme', 'icon' => 'graduation-cap'],
                'diplome_specialite' => ['label' => 'Diplôme spécialité', 'icon' => 'certificate'],
                'reconnaissance' => ['label' => 'Reconnaissance', 'icon' => 'award']
            ];
            
            $hasDocs = false;
            
            foreach ($docs as $doc => $info):
                $present = !empty($user[$doc]);
                if ($present) $hasDocs = true;
            ?>
                <li class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 text-xs rounded-full font-semibold <?= $present ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' ?>">
                        <i class="fas fa-<?= $info['icon'] ?>"></i>
                        <?= h($info['label']) ?> : <?= $present ? 'Oui' : 'Non' ?>
                    </span>
                    
                    <?php if ($present && $hasAccess): ?>
                        <?php if ($doc === 'cv' && $subscriptionName === 'Essentielle'): ?>
                            <!-- Bouton orange pour CV avec crédits limités -->
                            <a href="?ref=<?= urlencode($user['numero_reference']) ?>&download=<?= urlencode($doc) ?>"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-xs font-semibold transition-all shadow-sm hover:shadow"
                                <?= $cvCreditsRemaining <= 0 ? 'onclick="alert(\'Crédits CV épuisés\'); return false;"' : '' ?>>
                                <i class="fas fa-download"></i>
                                Télécharger (<?= $cvCreditsRemaining ?> crédit<?= $cvCreditsRemaining > 1 ? 's' : '' ?>)
                            </a>
                        <?php else: ?>
                            <!-- Bouton vert pour les autres documents ou abonnement Premium -->
                            <a href="?ref=<?= urlencode($user['numero_reference']) ?>&download=<?= urlencode($doc) ?>"
                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-semibold transition-all shadow-sm hover:shadow">
                                <i class="fas fa-download"></i>
                                Télécharger
                            </a>
                        <?php endif; ?>
                    <?php elseif ($present && !$hasAccess): ?>
                        <!-- Message si pas d'accès -->
                        <span class="text-xs text-gray-500 italic">
                            <i class="fas fa-lock"></i> Abonnement requis
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            
            <?php if (!$hasDocs): ?>
                <li class="italic text-gray-500 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    Aucun document envoyé.
                </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- Bouton retour -->
    <div class="px-8 pb-8">
        <a href="index.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 font-semibold rounded-lg transition-all shadow hover:shadow-md">
            <i class="fas fa-arrow-left"></i>
            Retour à la liste
        </a>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
$customJS = '';

try {
    include './includes/layouts/layout_default.php';
} catch (Exception $e) {
    echo $pageContent;
}
?>