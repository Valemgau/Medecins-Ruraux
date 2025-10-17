<?php
require_once './includes/config.php';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($userRole === 'recruteur') {
    $stmt = $pdo->prepare("SELECT u.*, r.* FROM users u JOIN recruteurs r ON u.id=r.id WHERE u.id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} else {
    die("Rôle utilisateur inconnu");
}

$errors = [];
$success = '';

$alertMessages = [
    'register_success' => ['type' => 'success', 'text' => 'Compte créé avec succès !'],
    'update_success' => ['type' => 'success', 'text' => 'Mise à jour effectuée avec succès !'],
    'photo_success' => ['type' => 'success', 'text' => 'Photo mise à jour avec succès !'],
    'update_error' => ['type' => 'error', 'text' => 'Une erreur est survenue lors de la mise à jour.'],
];

// Récupération du message GET sécurisé
$alertKey = $_GET['message'] ?? '';
$alert = $alertMessages[$alertKey] ?? null;

// Upload photo dédié
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $errors[] = "La photo doit être un fichier PNG, JPG, JPEG ou WEBP.";
        } else {
            $filename = "avatar_{$userId}_" . time() . ".$ext";
            move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . "/uploads/$filename");
            $pdo->prepare("UPDATE recruteurs SET photo=? WHERE id=?")->execute([$filename, $userId]);
            header("Location: dashboard-recruteur.php?message=photo_success");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['photo'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $telephoneIndicatif = $_POST['telephone_indicatif'] ?? '';
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

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE recruteurs SET prenom=?, nom=?, telephone_indicatif=?, telephone=?, etablissement=?, fonction=?, ville=?, pays=?, ville_etablissement=?, pays_etablissement=? WHERE id=?")
                ->execute([$prenom, $nom, $telephoneIndicatif, $telephoneNum, $etablissement, $fonction, $ville, $pays, $ville_etablissement, $pays_etablissement, $userId]);

            $pdo->commit();
            header("Location: dashboard-recruteur.php?message=update_success");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: dashboard-recruteur.php?message=update_error");
            exit;
        }
    }
}
?>
<?php
$title = "Tableau de bord";
ob_start();
?>
<div class="md:p-6">
    <?php if ($alert): ?>
        <div class="max-w-7xl mx-auto my-4 px-6">
            <div class="<?=
                $alert['type'] === 'success' ? 'bg-green-100 text-green-700' :
                ($alert['type'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')
                ?> p-4 rounded">
                <?= htmlspecialchars($alert['text']) ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="max-w-7xl mx-auto bg-white shadow-lg overflow-hidden">

        <div id="map" class="h-40 w-full"></div>
        <div class="md:flex p-8 pb-5 space-x-8">
            <div class="flex-shrink-0 -mt-20 relative">
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Avatar"
                        style="position: relative; z-index: 1000;" class="h-40 w-40 border-4 border-white object-cover" />
                <?php else: ?>
                    <div style="position:relative;z-index:1000;"
                        class="h-40 w-40 border-4 border-white flex items-center justify-center bg-gray-200">
                        <svg class="h-24 w-24 text-gray-400" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="7" r="5" />
                            <path d="M12 14c-5 0-7.5 2.5-7.5 5v2h15v-2c0-2.5-2.5-5-7.5-5" />
                        </svg>
                    </div>
                <?php endif; ?>
                <form style="z-index: 1000;" method="post" enctype="multipart/form-data"
                    class="absolute bottom-10 left-1/2 transform -translate-x-1/2">
                    <label class="px-3 py-1 bg-orange-500 text-white text-xs cursor-pointer hover:bg-orange-600">
                        Changer
                        <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()" />
                    </label>
                </form>
            </div>
            <div class="flex-grow pt-2">
                <h1 class="text-3xl font-bold text-gray-900">
                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                    (<?= htmlspecialchars($user['numero_reference']) ?>)
                </h1>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-gray-500 mt-2 text-sm">Inscrit depuis le
                    <?= date("d/m/Y", strtotime($user['created_at'])) ?>
                </p>
            </div>
        </div>
        <div class="px-8 pb-8">
            <?php
            $stripeSubscriptionId = $user['stripe_subscription_id'] ?? null;
            $isStripeSubscriber = false;
            $stripeStatus = null;

            if ($stripeSubscriptionId) {
                try {
                    $subscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    $stripeStatus = $subscription->status; // statut Stripe
                    // Statuts Stripe valides : 'active', 'trialing', 'past_due', 'canceled', etc.
                    $isStripeSubscriber = in_array($stripeStatus, ['active', 'trialing']);
                } catch (Exception $e) {
                    // Erreur Stripe (mauvais ID ou autre)
                    $isStripeSubscriber = false;
                }
            }

            $currentPriceName = "";
            $currentPriceAmount = 0;
            $currentPriceCurrency = "";
            $currentPriceInterval = "";

            if ($isStripeSubscriber && !empty($subscription->items->data)) {
                $item = $subscription->items->data[0];
                $priceNickname = $item->price->nickname;
                $priceUnitAmount = $item->price->unit_amount;
                $priceCurrency = $item->price->currency;
                $interval = $item->price->recurring->interval ?? '';

                $currentPriceName = $priceNickname ?: "Abonnement";
                $currentPriceAmount = $priceUnitAmount / 100;
                $currentPriceCurrency = strtoupper($priceCurrency);
                $currentPriceInterval = $interval;
            }

            // Affichage nom formule + crédits CV si formule Essentielle
            $currentPriceNameDisplay = htmlspecialchars($currentPriceName);

            if ($isStripeSubscriber && $currentPriceName === 'Essentielle') {
                $stmtCredits = $pdo->prepare("SELECT cv_credits_remaining FROM user_cv_credits WHERE user_id = ?");
                $stmtCredits->execute([$userId]);
                $creditsRestants = $stmtCredits->fetchColumn();

                $creditsRestants = $creditsRestants !== false ? (int) $creditsRestants : 0;
                if ($creditsRestants === 1) {
    $currentPriceNameDisplay .= " (1 CV consultable restant)";
} else {
    $currentPriceNameDisplay .= " ({$creditsRestants} CV consultables restants)";
}

            }
            ?>

            <?php if ($isStripeSubscriber): ?>
                <p>
                    Formule :
                    <a href="tarifs.php" class="text-blue-600 hover:underline font-semibold">
                        <?= $currentPriceNameDisplay ?>
                    </a>

                </p>


            <?php else: ?>
                <p>Aucun abonnement actif.</p>
            <?php endif; ?>

            <div
                class="text-sm flex flex-col space-y-2 md:flex-row md:space-y-0 md:space-x-4 border-t border-gray-200 px-2 pt-6 pb-8">
                <a href="liste.php"
                    class="flex-1 w-full md:w-auto text-center transition bg-orange-500 text-white py-2 px-4 rounded hover:underline">
                    Liste des candidats
                </a>

                <?php if (!$isStripeSubscriber): ?>
                    <a href="abonnement.php"
                        class="flex-1 w-full md:w-auto text-center transition bg-orange-500 text-white py-2 px-4 rounded hover:underline">
                        Souscrire à un abonnement
                    </a>
                <?php else: ?>
                    <a href="resilier.php"
                        class="flex-1 w-full md:w-auto text-center transition bg-orange-500 text-white py-2 px-4 rounded hover:underline">
                        Résilier mon abonnement
                    </a>
                <?php endif; ?>

                <a href="supprimer_compte.php"
                    class="flex-1 w-full md:w-auto text-center transition bg-red-500 text-white py-2 px-4 rounded hover:underline">
                    Supprimer mon compte
                </a>
            </div>


            <form method="post" enctype="multipart/form-data" class="space-y-6 max-w-xl mt-10">
                <div>
                    <label for="email" class="block font-semibold mb-1">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                        class="w-full border border-gray-300 px-3 py-2 bg-gray-100 cursor-not-allowed">
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="prenom" class="block font-semibold mb-1">Prénom</label>
                        <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>"
                            required class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                    </div>
                    <div>
                        <label for="nom" class="block font-semibold mb-1">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="ville" class="block font-semibold mb-1">Ville</label>
                        <select id="ville" name="ville" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                            <option value="" disabled selected>Choisir une ville</option>
                            <?php if (!empty($user['ville'])): ?>
                                <option value="<?= htmlspecialchars($user['ville']) ?>" selected>
                                    <?= htmlspecialchars($user['ville']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label for="pays" class="block font-semibold mb-1">Pays</label>
                        <select id="pays" name="pays" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none"></select>
                        <input type="hidden" id="pays_nom" name="pays_nom"
                            value="<?= htmlspecialchars($user['pays'] ?? '') ?>" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 items-end">
                    <div class="col-span-1">
                        <label for="telephone_indicatif" class="block font-semibold mb-1">Indicatif *</label>
                        <select id="telephone_indicatif" name="telephone_indicatif"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                            <!-- Options injectées par JS -->
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label for="telephone" class="block font-semibold mb-1">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                            value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="etablissement" class="block font-semibold mb-1">Etablissement</label>
                        <input type="text" id="etablissement" name="etablissement"
                            value="<?= htmlspecialchars($user['etablissement']) ?>"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                    </div>
                    <div>
                        <label for="fonction" class="block font-semibold mb-1">Fonction</label>
                        <input type="text" id="fonction" name="fonction"
                            value="<?= htmlspecialchars($user['fonction']) ?>"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="ville_etablissement" class="block font-semibold mb-1">Ville établissement</label>
                        <select id="ville_etablissement" name="ville_etablissement" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none">
                            <option value="" disabled selected>Choisir une ville</option>
                            <?php if (!empty($user['ville_etablissement'])): ?>
                                <option value="<?= htmlspecialchars($user['ville_etablissement']) ?>" selected>
                                    <?= htmlspecialchars($user['ville_etablissement']) ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label for="pays_etablissement" class="block font-semibold mb-1">Pays établissement</label>
                        <select id="pays_etablissement" name="pays_etablissement" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none"></select>
                        <input type="hidden" id="pays_etablissement_nom" name="pays_etablissement_nom"
                            value="<?= htmlspecialchars($user['pays_etablissement'] ?? '') ?>" />
                    </div>
                </div>

                <button type="submit" class="bg-orange-500 hover:bg-orange-600 transition text-white px-6 py-3">Mettre à
                    jour</button>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    async function loadIndicatifs() {
        const select = document.getElementById('telephone_indicatif');
        if (!select) return;

        select.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';

        const response = await fetch('https://restcountries.com/v3.1/all?fields=idd,name');
        const countries = await response.json();

        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));

        let options = '<option value="" disabled selected>Choisir un indicatif</option>';
        countries.forEach(country => {
            if (country.idd?.root) {
                const callingCode = country.idd.root + (country.idd.suffixes ? country.idd.suffixes[0] : '');
                options += `<option value="${callingCode}" data-nom="${country.name.common}">${country.name.common} (${callingCode})</option>`;
            }
        });
        select.innerHTML = options;

        // Préselection indicatif
        const currentIndicatif = '<?= addslashes($user['telephone_indicatif'] ?? '') ?>';
        if (currentIndicatif) {
            select.value = currentIndicatif;
        }
    }

    async function initMap() {
        const ville = '<?= addslashes($user['ville'] ?? '') ?>';
        const pays = '<?= addslashes($user['pays'] ?? '') ?>';
        const adresse = `${ville}, ${pays}`;

        if (!ville && !pays) return;

        try {
            // Appel API Nominatim pour géocoding libre OSM
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(adresse)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!data.length) return;

            const lat = data[0].lat;
            const lon = data[0].lon;

            // Création carte Leaflet
            const map = L.map('map').setView([lat, lon], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lon]).addTo(map)
                .bindPopup(adresse)
                .openPopup();
        } catch (err) {
            console.warn('Erreur chargement carte :', err);
        }
    }

    async function loadCountries() {
        // Sélecteurs pays à gérer
        const paysSelects = [document.getElementById('pays'), document.getElementById('pays_etablissement')].filter(x => x);
        for (const selectPays of paysSelects) {
            selectPays.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';
        }

        const response = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2');
        const countries = await response.json();
        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));

        let options = '<option value="" disabled>Choisir un pays</option>';
        countries.forEach(country => {
            options += `<option value="${country.name.common}" data-nom="${country.name.common}" data-iso2="${country.cca2}">${country.name.common}</option>`;
        });

        for (const selectPays of paysSelects) {
            selectPays.innerHTML = options;
            selectPays.disabled = false;
        }
    }

    async function updateCities(selectPaysId, selectVilleId) {
        const selectPays = document.getElementById(selectPaysId);
        const villeSelect = document.getElementById(selectVilleId);
        const selectedOption = selectPays.selectedOptions[0];
        const countryCode = selectedOption ? selectedOption.dataset.iso2 : '';

        villeSelect.innerHTML = '<option value="" disabled selected>Chargement des villes...</option>';
        villeSelect.disabled = true;

        if (!countryCode) {
            villeSelect.innerHTML = '<option value="" disabled>Choisir un pays d\'abord</option>';
            return;
        }

        try {
            const username = 'sunderr'; // ton username GeoNames
            const url = `https://secure.geonames.org/searchJSON?country=${countryCode}&featureClass=P&maxRows=100&username=${username}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error('Erreur GeoNames: ' + response.status);
            const data = await response.json();

            if (!data.geonames || data.geonames.length === 0) {
                villeSelect.innerHTML = '<option value="" disabled>Aucune ville trouvée</option>';
                return;
            }

            villeSelect.innerHTML = '<option value="" disabled>Choisir une ville</option>';
            data.geonames.forEach(city => {
                villeSelect.insertAdjacentHTML('beforeend', `<option value="${city.name}">${city.name}</option>`);
            });

            villeSelect.disabled = false;
        } catch (e) {
            villeSelect.innerHTML = '<option value="" disabled>Erreur de chargement</option>';
            console.error(e);
        }
    }

    function updatePaysNom(selectId, inputId) {
        const selectPays = document.getElementById(selectId);
        const inputPaysNom = document.getElementById(inputId);
        const option = selectPays.selectedOptions[0];
        inputPaysNom.value = option ? option.dataset.nom : '';
    }

    // Ajout listeners changement pays pour chaque couple
    document.addEventListener('DOMContentLoaded', () => {
        loadIndicatifs();
        initMap();
        loadCountries();

        const paysVillePairs = [
            { pays: 'pays', ville: 'ville', paysNom: 'pays_nom' },
            { pays: 'pays_etablissement', ville: 'ville_etablissement', paysNom: 'pays_etablissement_nom' }
        ];

        paysVillePairs.forEach(({ pays, ville, paysNom }) => {
            const selectPays = document.getElementById(pays);
            if (selectPays) {
                selectPays.addEventListener('change', () => {
                    updatePaysNom(pays, paysNom);
                    updateCities(pays, ville);
                });
            }
        });
    });
</script>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>