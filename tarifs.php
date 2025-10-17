<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Subscription;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?role=recruteur');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($userRole !== 'recruteur') {
    die("Rôle utilisateur inconnu");
}

// Récupérer les infos utilisateur pour affichage
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
    // Pas d'abonnement actif => proposr de s'abonner via dashboard
    header("Location: dashboard-recruteur.php");
    exit;
}

// Récupérer tous les tarifs Stripe du produit
$productId = $productID;
$pricesList = \Stripe\Price::all([
    'product' => $productId,
    'active' => true,
    'limit' => 10,
]);

// Associer chaque formule au tarif Stripe par nickname
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

<div class="md:p-6 max-w-7xl mx-auto">
    <div class="bg-white shadow-lg overflow-hidden p-8 rounded-lg">
        <p class="text-center mb-6 text-gray-700 text-sm">
            Le site est totalement gratuit pour les candidats.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-center border-collapse border border-gray-300" style="background:white;">
                <thead>
                    <tr>
                        <th class="text-left p-3 font-bold border-b border-gray-200 bg-gray-50"></th>
                        <th class="p-3 font-bold border-b border-gray-200 bg-gray-50">Essentielle</th>
                        <th class="p-3 font-bold border-b border-gray-200 bg-gray-50">Standard</th>
                        <th class="p-3 font-bold border-b border-gray-200 bg-gray-50">Premium</th>
                        <th class="p-3 font-bold border-b border-gray-200 bg-gray-50">Entreprise</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left p-3 font-semibold border-b">Tarif mensuel</td>
                        <td class="p-3 border-b"><a
                                href="abonnement.php?price_id=<?= htmlspecialchars($formules['Essentielle']->id ?? '') ?>"
                                class="text-orange-600 hover:underline"><?= $formules['Essentielle'] ? number_format($formules['Essentielle']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></a>
                        </td>
                        <td class="p-3 border-b"><a
                                href="abonnement.php?price_id=<?= htmlspecialchars($formules['Standard']->id ?? '') ?>"
                                class="text-orange-600 hover:underline"><?= $formules['Standard'] ? number_format($formules['Standard']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></a>
                        </td>
                        <td class="p-3 border-b"><a
                                href="abonnement.php?price_id=<?= htmlspecialchars($formules['Premium']->id ?? '') ?>"
                                class="text-orange-600 hover:underline"><?= $formules['Premium'] ? number_format($formules['Premium']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></a>
                        </td>
                        <td class="p-3 border-b font-semibold text-gray-700">Sur devis (≥ 1000 €)</td>
                    </tr>
                    <tr>
                        <td class="text-left p-3 font-semibold border-b">Accès aux CV</td>
                        <td class="p-3 border-b">20 CV consultables / mois</td>
                        <td class="p-3 border-b">Accès illimité</td>
                        <td class="p-3 border-b">Accès illimité</td>
                        <td class="p-3 border-b">Accès illimité</td>
                    </tr>

                    <tr>
                        <td class="text-left p-3 font-semibold border-b">Filtres avancés<br>(pays, disponibilités)</td>
                        <td class="p-3 border-b">Basique</td>
                        <td class="p-3 border-b">✔ Standard</td>
                        <td class="p-3 border-b">✔ Avancé</td>
                        <td class="p-3 border-b">✔ Personnalisé</td>
                    </tr>
                    <tr>
                        <td class="text-left p-3 font-semibold border-none"></td>
                        <td class="p-3 border-none text-center" style="width: 20%;">
                            <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Essentielle']->id ?? '') ?>"
                                class="bg-orange-500 w-full block px-6 py-2 rounded text-white font-semibold hover:bg-orange-600 transition">
                                Voir
                            </a>
                        </td>
                        <td class="p-3 border-none text-center" style="width: 20%;">
                            <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Standard']->id ?? '') ?>"
                                class="bg-orange-500 w-full block px-6 py-2 rounded text-white font-semibold hover:bg-orange-600 transition">
                                Voir
                            </a>
                        </td>
                        <td class="p-3 border-none text-center" style="width: 20%;">
                            <a href="abonnement.php?price_id=<?= htmlspecialchars($formules['Premium']->id ?? '') ?>"
                                class="bg-orange-500 w-full block px-6 py-2 rounded text-white font-semibold hover:bg-orange-600 transition">
                                Voir
                            </a>
                        </td>
                        <td class="p-3 border-none text-center" style="width: 20%;">
                            <a href="contact.php"
                                class="bg-gray-700 w-full block px-6 py-2 rounded text-white font-semibold hover:bg-gray-900 transition">
                                Contact
                            </a>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>