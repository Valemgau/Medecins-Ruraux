<?php
require_once './includes/config.php';
use \Stripe\Subscription;
use \Stripe\Stripe;
require 'vendor/autoload.php';

$ref = $_GET['ref'] ?? '';
if (!$ref)
    die('Référence candidat manquante.');

$recruteurId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// Récupérer infos candidat + user (candidat)
$stmt = $pdo->prepare("
    SELECT u.email, u.created_at, c.*
    FROM candidats c
    JOIN users u ON u.id = c.id
    WHERE c.numero_reference = ?
    LIMIT 1
");
$stmt->execute([$ref]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user)
    die('Candidat non trouvé.');

// Incrémenter vues
$stmtUpdate = $pdo->prepare("UPDATE candidats SET views = views + 1 WHERE id = ?");
$stmtUpdate->execute([$user['id']]);

// Suivi consultation
$alreadyConsulted = false;
if ($recruteurId) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO consultations (user_id, candidat_id, consulted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$recruteurId, $user['id']]);
    $stmt = $pdo->prepare("SELECT 1 FROM consultations WHERE user_id = ? AND candidat_id = ? LIMIT 1");
    $stmt->execute([$recruteurId, $user['id']]);
    $alreadyConsulted = $stmt->fetchColumn() !== false;
}

// Vérifier abonnement Stripe du recruteur
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
            $subscription = Subscription::retrieve($stripeSubscriptionId);
            $status = $subscription->status;
            if (in_array($status, ['active', 'trialing'])) {
                $isSubscriber = true;
                $subscriptionName = $subscription->items->data[0]->price->nickname ?? "";
            }
        } catch (Exception $e) {
            $isSubscriber = false;
        }
    }
}

// Gestion téléchargement et décrémentation crédit CV pour Essentielle
if (isset($_GET['download']) && $isSubscriber) {
    $type = $_GET['download'];
    $allowedDocs = ['cv', 'diplome', 'diplome_specialite', 'reconnaissance'];
    if (!in_array($type, $allowedDocs))
        die('Type de document invalide.');
    if (empty($user[$type]))
        die('Document non disponible.');

    if ($subscriptionName === 'Essentielle' && $type === 'cv') {
        $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
        $stmtCredits->execute([$recruteurId]);
        $credits = (int) $stmtCredits->fetchColumn();
        if ($credits <= 0)
            die('Crédits CV épuisés. Veuillez upgrader votre abonnement.');

        $stmtUpdateCredits = $pdo->prepare("UPDATE user_cv_credits SET cv_credits_remaining = cv_credits_remaining - 1 WHERE user_id = ?");
        $stmtUpdateCredits->execute([$recruteurId]);
    }

    $filepath = __DIR__ . "/uploads/" . $user[$type];
    if (!file_exists($filepath))
        die('Fichier introuvable.');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    readfile($filepath);
    exit;
}

// Récupérer crédits CV restants pour affichage
$cvCreditsRemaining = 0;
if ($isSubscriber && $subscriptionName === 'Essentielle') {
    $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
    $stmtCredits->execute([$recruteurId]);
    $cvCreditsRemaining = (int) $stmtCredits->fetchColumn();
}

// Variables pour affichage sécurisé si pas abonnement
$hasAccess = $isSubscriber;

$title = "Profil candidat - " . htmlspecialchars($user['prenom'] . ' ' . $user['nom']);
ob_start();
?>
<div class="max-w-3xl mx-auto bg-white rounded shadow-md relative p-0 overflow-hidden">
    <?php if ($recruteurId && !$isSubscriber): ?>
    <div class="p-4 bg-red-100 text-red-800 font-semibold text-center rounded mb-6">
        Pour accéder aux CV et informations complètes des candidats, activez votre abonnement recruteur !
        <a href="abonnement.php"
           class="underline font-bold text-red-900 ml-1">Choisir un abonnement</a>
    </div>
<?php endif; ?>

    <?php if (!$recruteurId): ?>
        <div class="p-4 bg-yellow-100 text-yellow-800 font-semibold text-center rounded mb-6">
            Vous êtes recruteur ? <a href="login.php?role=recruteur"
                class="underline font-bold text-yellow-900">Connectez-vous ici</a><br />
            Vous êtes candidat ? <a href="register.php" class="underline font-bold text-yellow-900">Créez votre compte</a>
            pour avoir votre profil consultable.
        </div>
    <?php endif; ?>

    <?php if ($alreadyConsulted): ?>
        <div class="absolute right-0 top-0">
            <span
                class="inline-block bg-purple-600 text-white text-xs font-bold px-4 py-1 rounded-bl-xl shadow uppercase tracking-wider z-10"
                style="border-top-right-radius:0.6rem;">
                Déjà consulté
            </span>
        </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row items-start gap-6 p-8">
        <div
            class="w-40 h-40 rounded-lg bg-gray-100 border border-gray-300 flex-shrink-0 overflow-hidden flex justify-center items-center shadow">
            <?php if (!empty($user['photo'])): ?>
                <img src="uploads/<?= htmlspecialchars($user['photo']) ?>"
                    alt="Photo de <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>"
                    class="w-full h-full object-cover rounded-lg" />
            <?php else: ?>
                <span class="text-gray-400 text-lg">Pas de photo</span>
            <?php endif; ?>
        </div>
        <section class="flex-1 space-y-2">
            <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">
                <?= $hasAccess ? htmlspecialchars($user['prenom'] . ' ' . $user['nom'])
                    : (htmlspecialchars(substr($user['prenom'], 0, 1)) . str_repeat('*', max(strlen($user['prenom']) - 1, 0)) . ' ' . htmlspecialchars(substr($user['nom'], 0, 1)) . str_repeat('*', max(strlen($user['nom']) - 1, 0))); ?>
            </h1>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                <div>
                    <div class="text-xs text-gray-400 uppercase">Référence</div>
                    <div class="font-mono text-sm"><?= htmlspecialchars($user['numero_reference'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Métier</div>
                    <div class="font-semibold"><?= htmlspecialchars($user['fonction']) ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Pays de recherche</div>
                    <div>
                        <?php
                        $paysRecherche = $user['pays_recherche'] ?? '[]';
                        $paysArray = json_decode($paysRecherche, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($paysArray)) {
                            echo htmlspecialchars($paysRecherche);
                        } else {
                            echo htmlspecialchars(implode(', ', $paysArray));
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Autorisations de travail</div>
                    <div>
                        <?php
                        $autorisationsTravail = $user['autorisations_travail'] ?? '[]';
                        $autorisationsArray = json_decode($autorisationsTravail, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($autorisationsArray)) {
                            echo htmlspecialchars($autorisationsTravail);
                        } else {
                            echo htmlspecialchars(implode(', ', $autorisationsArray));
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Date d'inscription</div>
                    <div><?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '-' ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Délai de préavis</div>
                    <div>
                        <?= isset($user['delai_preavis']) && $user['delai_preavis'] !== null ? htmlspecialchars($user['delai_preavis']) . ' mois' : '-' ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Email</div>
                    <div class="break-all w-full text-sm">
                        <?= $hasAccess ? htmlspecialchars($user['email'] ?? '-') : '****' ?></div>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Ville, pays</div>
                    <div class="break-all w-full text-sm">
                        <?= $hasAccess
            ? htmlspecialchars(trim(($user['ville'] ?? '-') . ', ' . ($user['pays'] ?? '-')))
            : '****' ?>
                </div>
            </div>
            <?php if (!empty($user['motivations'])): ?>
                <div>
                    <div class="text-xs text-gray-400 uppercase">Motivations</div>
                    <div class="text-gray-700"><?= $hasAccess ? nl2br(htmlspecialchars($user['motivations'])) : '****' ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="px-8 pb-6">
        <h2 class="text-lg font-semibold mt-2 mb-2">Documents</h2>
        <ul class="flex flex-wrap gap-3">
            <?php
            $docs = ['cv' => 'CV', 'diplome' => 'Diplôme', 'diplome_specialite' => 'Diplôme spécialité', 'reconnaissance' => 'Reconnaissance'];
            $hasDocs = false;
            foreach ($docs as $doc => $label):
                $present = !empty($user[$doc]);
                if ($present)
                    $hasDocs = true;
                ?>
                <li>
                    <span
                        class="inline-block px-3 py-1 text-xs rounded-full font-semibold <?= $present ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' ?>">
                        <?= $label ?> : <?= $present ? 'Oui' : 'Non' ?>
                    </span>
                    <?php if ($present && $hasAccess): ?>
                        <?php if ($doc === 'cv' && $subscriptionName === 'Essentielle'): ?>
                            <a href="?ref=<?= urlencode($user['numero_reference']) ?>&download=cv"
                                class="ml-2 px-3 py-1 bg-orange-500 text-white rounded hover:bg-orange-600 text-xs font-semibold transition">Télécharger</a>
                        <?php else: ?>
                            <a href="?ref=<?= urlencode($user['numero_reference']) ?>&download=<?= $doc ?>"
                                class="ml-2 px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-xs font-semibold transition">Télécharger</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$hasDocs): ?>
                <li class="italic text-gray-500">Aucun document envoyé.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_default.php';
?>