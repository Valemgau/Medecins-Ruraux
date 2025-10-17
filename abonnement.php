<?php
require_once './includes/config.php';
require 'vendor/autoload.php';

use \Stripe\Checkout\Session;

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($userRole !== 'recruteur') {
    die("Rôle utilisateur inconnu");
}

// Récupérer les infos utilisateur pour affichage
$stmt = $pdo->prepare("SELECT u.email, u.created_at, r.prenom, r.nom, r.photo, u.stripe_customer_id FROM users u JOIN recruteurs r ON u.id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable");
}

// Récupérer les tarifs Stripe du produit (remplace par ton vrai ID produit)
$productId = $productID;
$pricesList = \Stripe\Price::all([
    'product' => $productId,
    'active' => true,
    'limit' => 10,
]);

// Associer chaque formule à son price Stripe (via nickname)
$formules = [
    'Essentielle' => null,
    'Standard' => null,
    'Premium' => null,
];
foreach ($pricesList->data as $price) {
    if ($price->nickname === 'Essentielle') $formules['Essentielle'] = $price;
    if ($price->nickname === 'Standard')    $formules['Standard'] = $price;
    if ($price->nickname === 'Premium')     $formules['Premium'] = $price;
}

// Gestion messages après retour Stripe
$alert = null;
if (isset($_GET['success'])) {
    $alert = ['type' => 'success', 'text' => 'Abonnement activé avec succès ! Merci pour votre souscription.'];
} elseif (isset($_GET['canceled'])) {
    $alert = ['type' => 'error', 'text' => 'Abonnement annulé ou non finalisé. Vous pouvez retenter à tout moment.'];
}

// Création session Stripe au clic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $priceId = $_POST['price_id'] ?? null;
    if (!$priceId) {
        die("Tarif non sélectionné.");
    }

    // Créer ou récupérer client Stripe
    $stripeCustomerId = $user['stripe_customer_id'];
    if (!$stripeCustomerId) {
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'metadata' => ['user_id' => $userId]
        ]);
        $stripeCustomerId = $customer->id;
        $pdo->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?")->execute([$stripeCustomerId, $userId]);
    }

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $session = Session::create([
        'customer' => $stripeCustomerId,
        'payment_method_types' => ['card'],
        'mode' => 'subscription',
        'line_items' => [
            [
                'price' => $priceId,
                'quantity' => 1,
            ],
        ],
        'success_url' => $baseUrl . '/abonnement_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . '/abonnement.php?canceled=1',
        'locale' => 'fr',
    ]);

    header("Location: " . $session->url);
    exit;
}

$title = "Abonnement recruteur";
ob_start();
?>

<div class="md:p-6">
    <?php if ($alert): ?>
        <div class="max-w-7xl mx-auto my-4 px-6">
            <div class="<?= $alert['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> p-4 rounded">
                <?= htmlspecialchars($alert['text']) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto bg-white shadow-lg overflow-hidden">

        <div class="md:flex p-8 pb-5 space-x-8">
            <div class="flex-shrink-0 relative">
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Avatar" style="position: relative; z-index: 1000;" class="h-40 w-40 border-4 border-white object-cover" />
                <?php else: ?>
                    <div class="h-40 w-40 border-4 border-white flex items-center justify-center bg-gray-200">
                        <svg class="h-24 w-24 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="7" r="5" />
                            <path d="M12 14c-5 0-7.5 2.5-7.5 5v2h15v-2c0-2.5-2.5-5-7.5-5" />
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-grow pt-2">
                <h1 class="text-3xl font-bold text-gray-900">
                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                </h1>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-gray-500 mt-2 text-sm">Inscrit depuis le <?= date("d/m/Y", strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        <div class="border-t border-gray-200 px-8 pb-8">
            <h2 class="text-xl font-semibold my-4">Choisissez votre formule d'abonnement</h2>
            
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
                            <td class="p-3 border-b"><?= $formules['Essentielle'] ? number_format($formules['Essentielle']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></td>
                            <td class="p-3 border-b"><?= $formules['Standard'] ? number_format($formules['Standard']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></td>
                            <td class="p-3 border-b"><?= $formules['Premium'] ? number_format($formules['Premium']->unit_amount / 100, 2, ',', ' ') . ' € / mois' : '-' ?></td>
                            <td class="p-3 border-b font-semibold">Sur devis (≥ 1000 €)</td>
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
                            <td class="p-3 border-none">
                                <?php if ($formules['Essentielle']): ?>
                                <form method="post">
                                    <input type="hidden" name="price_id" value="<?= htmlspecialchars($formules['Essentielle']->id) ?>">
                                    <button type="submit" name="subscribe"
                                            class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded font-semibold transition">Souscrire</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 border-none">
                                <?php if ($formules['Standard']): ?>
                                <form method="post">
                                    <input type="hidden" name="price_id" value="<?= htmlspecialchars($formules['Standard']->id) ?>">
                                    <button type="submit" name="subscribe"
                                            class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded font-semibold transition">Souscrire</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 border-none">
                                <?php if ($formules['Premium']): ?>
                                <form method="post">
                                    <input type="hidden" name="price_id" value="<?= htmlspecialchars($formules['Premium']->id) ?>">
                                    <button type="submit" name="subscribe"
                                            class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded font-semibold transition">Souscrire</button>
                                </form>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 border-none">
                                <a href="contact.php" class="bg-gray-700 hover:bg-gray-900 text-white px-7 py-2 rounded font-semibold transition block">Contacter</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-gray-600 text-sm text-center">
                Après avoir choisi une formule, vous serez redirigé vers Stripe pour finaliser l'abonnement. Offre "Entreprise" sur mesure sur simple contact.
            </p>
        </div>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>
