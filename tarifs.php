<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Subscription;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?role=recruteur');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if ($userRole !== 'recruteur') {
    die("Rôle utilisateur inconnu");
}

$stmt = $pdo->prepare("SELECT u.email, u.created_at, u.stripe_subscription_id, r.prenom, r.nom, r.photo FROM users u JOIN recruteurs r ON u.id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable");
}

$subscription = null;
try {
    if (!empty($user['stripe_subscription_id'])) {
        $subscription = Subscription::retrieve($user['stripe_subscription_id']);
    }
} catch (Exception $e) {
    // ignorer erreur
}

if (!$subscription || !in_array($subscription->status, ['active', 'trialing', 'past_due'])) {
    header("Location: dashboard-recruteur.php");
    exit;
}

$productId = $productID;
$pricesList = \Stripe\Price::all([
    'product' => $productId,
    'active' => true,
    'limit' => 10,
]);

$formules = [
    'Essentielle' => null,
    'Standard' => null,
    'Premium' => null,
];
foreach ($pricesList->data as $price) {
    if ($price->nickname === 'Essentielle')
        $formules['Essentielle'] = $price;
    if ($price->nickname === 'Standard')
        $formules['Standard'] = $price;
    if ($price->nickname === 'Premium')
        $formules['Premium'] = $price;
}

$title = "Tarifs abonnements";
ob_start();
?>

<style>
.pricing-card {
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
}

.pricing-card .card-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.pricing-card .features-list {
    flex: 1;
}

.check-icon {
    color: #22c55e;
}
</style>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8 px-4 sm:px-6 lg:px-8">
    
    <div class="max-w-7xl mx-auto">

        <!-- Titre section -->
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-900 mb-3 flex items-center justify-center gap-3">
                <i class="fas fa-crown text-green-600"></i>
                Nos formules d'abonnement
            </h2>
            <p class="text-gray-600 text-lg">Le site est totalement gratuit pour les candidats</p>
        </div>

        <!-- Grille des formules -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Formule Essentielle -->
            <div class="pricing-card bg-white rounded-2xl shadow-lg overflow-hidden border-2 border-gray-200 hover:border-green-400">
                <div class="bg-gradient-to-br from-gray-50 to-white p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Essentielle</h3>
                    <div class="text-3xl font-bold text-green-600 mb-1">
                        <?= $formules['Essentielle'] ? number_format($formules['Essentielle']->unit_amount / 100, 0, ',', ' ') : '-' ?>€
                    </div>
                    <p class="text-gray-500 text-sm">par mois</p>
                </div>
                <div class="card-content p-6">
                    <ul class="features-list space-y-3 mb-6">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm"><strong>20 CV</strong> consultables / mois</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Filtres basiques</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Support email</span>
                        </li>
                    </ul>
                    <?php if ($formules['Essentielle']): ?>
                    <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Essentielle']->id) ?>"
                       class="mt-auto block w-full text-center bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-eye mr-2"></i>Voir
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formule Standard -->
            <div class="pricing-card bg-white rounded-2xl shadow-lg overflow-hidden border-2 border-green-400 relative">
                <div class="absolute top-4 right-4 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                    Populaire
                </div>
                <div class="bg-gradient-to-br from-green-50 to-white p-6 border-b border-green-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Standard</h3>
                    <div class="text-3xl font-bold text-green-600 mb-1">
                        <?= $formules['Standard'] ? number_format($formules['Standard']->unit_amount / 100, 0, ',', ' ') : '-' ?>€
                    </div>
                    <p class="text-gray-500 text-sm">par mois</p>
                </div>
                <div class="card-content p-6">
                    <ul class="features-list space-y-3 mb-6">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm"><strong>CV illimités</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Filtres standard</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Support prioritaire</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Alertes candidats</span>
                        </li>
                    </ul>
                    <?php if ($formules['Standard']): ?>
                    <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Standard']->id) ?>"
                       class="mt-auto block w-full text-center bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-eye mr-2"></i>Voir
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formule Premium -->
            <div class="pricing-card bg-white rounded-2xl shadow-lg overflow-hidden border-2 border-gray-200 hover:border-green-400">
                <div class="bg-gradient-to-br from-amber-50 to-white p-6 border-b border-amber-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Premium</h3>
                    <div class="text-3xl font-bold text-green-600 mb-1">
                        <?= $formules['Premium'] ? number_format($formules['Premium']->unit_amount / 100, 0, ',', ' ') : '-' ?>€
                    </div>
                    <p class="text-gray-500 text-sm">par mois</p>
                </div>
                <div class="card-content p-6">
                    <ul class="features-list space-y-3 mb-6">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm"><strong>CV illimités</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Filtres avancés</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Support dédié 24/7</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Statistiques détaillées</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">API accès</span>
                        </li>
                    </ul>
                    <?php if ($formules['Premium']): ?>
                    <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Premium']->id) ?>"
                       class="mt-auto block w-full text-center bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-eye mr-2"></i>Voir
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formule Entreprise -->
            <div class="pricing-card bg-white rounded-2xl shadow-lg overflow-hidden border-2 border-gray-200 hover:border-green-400">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 border-b border-gray-700">
                    <h3 class="text-xl font-bold text-white mb-2">Entreprise</h3>
                    <div class="text-3xl font-bold text-green-400 mb-1">
                        Sur devis
                    </div>
                    <p class="text-gray-300 text-sm">≥ 1000€ / mois</p>
                </div>
                <div class="card-content p-6">
                    <ul class="features-list space-y-3 mb-6">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm"><strong>Tout illimité</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Filtres personnalisés</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Compte manager dédié</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Multi-comptes</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check check-icon mt-1"></i>
                            <span class="text-gray-700 text-sm">Intégration sur mesure</span>
                        </li>
                    </ul>
                    <a href="contact.php" 
                       class="mt-auto block w-full text-center bg-gradient-to-r from-gray-700 to-gray-900 hover:from-gray-800 hover:to-black text-white py-3 rounded-xl font-semibold transition-all shadow-md hover:shadow-lg">
                        <i class="fas fa-envelope mr-2"></i>Nous contacter
                    </a>
                </div>
            </div>

        </div>

        <!-- Note de bas de page -->
        <div class="bg-white rounded-xl p-6 shadow-md border border-gray-200">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-info-circle text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="flex-grow">
                    <h3 class="font-semibold text-gray-900 mb-2">Informations importantes</h3>
                    <ul class="text-gray-600 text-sm space-y-1">
                        <li>• Vous pouvez modifier votre abonnement à tout moment</li>
                        <li>• Tous les paiements sont sécurisés via Stripe</li>
                        <li>• Annulation possible depuis votre tableau de bord</li>
                        <li>• Pour l'offre Entreprise, contactez-nous pour un devis personnalisé</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>
