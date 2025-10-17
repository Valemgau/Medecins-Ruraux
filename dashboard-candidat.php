<?php
require_once './includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($userRole !== 'candidat') {
    die("Rôle utilisateur inconnu");
}

// Récupération des infos utilisateur + candidat
$stmt = $pdo->prepare("SELECT u.email, u.created_at, c.numero_reference, c.telephone_indicatif, c.telephone, c.ville, c.pays, c.prenom, c.nom, c.photo,
    c.cv, c.diplome, c.diplome_specialite, c.reconnaissance,
    c.pays_recherche, c.autorisations_travail, c.motivations
    FROM users u JOIN candidats c ON u.id=c.id WHERE u.id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$alert = null;

// Champs obligatoires à vérifier
$requiredFields = [
    'prenom' => 'Prénom',
    'nom' => 'Nom',
    'telephone_indicatif' => 'Indicatif téléphonique',
    'telephone' => 'Téléphone',
    'ville' => 'Ville',
    'pays' => 'Pays',
    'numero_reference' => 'Numéro de référence',
    'photo' => 'Photo',
    'cv' => 'CV',
    'diplome' => 'Diplôme',
    'diplome_specialite' => 'Spécialité du diplôme',
    'reconnaissance' => 'Reconnaissance',
    'pays_recherche' => 'Pays de recherche d\'emploi',
    'autorisations_travail' => 'Autorisations de travail',
    'motivations' => 'Motivations',
];

// Vérifier les champs manquants
$missingFields = [];
foreach ($requiredFields as $field => $label) {
    if ($field === 'pays_recherche' || $field === 'autorisations_travail') {
        // Champs JSON, vérifier qu'ils contiennent au moins un élément
        if (empty($user[$field]) || (is_string($user[$field]) && count(json_decode($user[$field], true) ?: []) === 0)) {
            $missingFields[] = $label;
        }
    } else {
        if (empty($user[$field]) || (is_string($user[$field]) && trim($user[$field]) === '')) {
            $missingFields[] = $label;
        }
    }
}

// Gestion upload fichiers
$fieldNames = ['cv', 'diplome', 'diplome_specialite', 'reconnaissance', 'photo'];
foreach ($fieldNames as $f) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'])) {
            $errors[] = "Le fichier $f doit être au format PDF, DOC, DOCX, JPG, PNG ou WEBP.";
        } else {
            $filename = "{$f}_{$userId}_" . time() . ".$ext";
            move_uploaded_file($_FILES[$f]['tmp_name'], __DIR__ . "/uploads/$filename");
            $pdo->prepare("UPDATE candidats SET $f=? WHERE id=?")->execute([$filename, $userId]);
            header("Location: dashboard-candidat.php?message=update_$f");
            exit;
        }
    }
}

// Traitement post formulaire principal sans fichiers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES)) {
    $fields = [
        'prenom' => trim($_POST['prenom'] ?? ''),
        'nom' => trim($_POST['nom'] ?? ''),
        'telephone_indicatif' => $_POST['telephone_indicatif'] ?? '',
        'telephone' => trim($_POST['telephone'] ?? ''),
        'ville' => trim($_POST['ville'] ?? ''),
        'pays' => trim($_POST['pays'] ?? ''),
        'pays_recherche' => isset($_POST['pays_recherche']) ? json_encode(array_slice($_POST['pays_recherche'], 0, 10)) : null,
        'autorisations_travail' => isset($_POST['autorisations_travail']) ? json_encode(array_slice($_POST['autorisations_travail'], 0, 10)) : null,
        'motivations' => trim($_POST['motivations'] ?? ''),
    ];

    if (!$fields['prenom'] || !$fields['nom']) {
        $errors[] = "Le prénom et le nom sont obligatoires.";
    }
    if (!$fields['autorisations_travail']) {
        $errors[] = "Veuillez indiquer les pays pour lesquels vous détenez des autorisations de travail.";
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
        header("Location: dashboard-candidat.php?message=update_success");
        exit;
    }
}

$messages = [
    'register_success' => 'Compte créé avec succès !',
    'update_success' => 'Profil mis à jour.',
    'update_cv' => 'CV mis à jour.',
    'update_diplome' => 'Diplôme mis à jour.',
    'update_diplome_specialite' => 'Spécialité du diplôme mise à jour.',
    'update_reconnaissance' => 'Reconnaissance mise à jour.',
    'update_photo' => 'Photo mise à jour.'
];
if (!empty($_GET['message']) && isset($messages[$_GET['message']])) {
    $alert = $messages[$_GET['message']];
}

$title = "Dashboard candidat";
ob_start();
?>


<div class="md:p-6">
    <?php if ($alert): ?>
        <div class="max-w-7xl mx-auto my-4 px-6">
            <div class="bg-green-100 text-green-700 p-4 rounded"><?= htmlspecialchars($alert) ?></div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto bg-white shadow-lg overflow-hidden">

        <div id="map" class="h-40 w-full"></div>
        <div class="md:flex p-8 pb-5 space-x-8">
            <div class="flex-shrink-0 -mt-20 relative">
                <?php if (!empty($user['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Avatar"
                        style="position:relative;z-index:1000;" class="h-40 w-40 border-4 border-white object-cover" />
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

                <!-- Indicateur de statut complétude -->
                <?php
                $isComplete = empty($missingFields);
                ?>
                <div style="position:absolute; top: 10px; right: 10px; z-index: 1100;"
                    class="rounded-full w-5 h-5 border-2 <?= $isComplete ? 'border-green-600 bg-green-400' : 'border-red-600 bg-red-400' ?>"
                    title="<?= $isComplete ? 'Compte complet' : 'Compte incomplet' ?>">
                </div>

                <form method="post" enctype="multipart/form-data"
                    class="absolute bottom-10 left-1/2 transform -translate-x-1/2" style="z-index:1000;">
                    <label class="px-3 py-1 bg-orange-500 text-white text-xs cursor-pointer hover:bg-orange-600">
                        Changer
                        <input type="file" name="photo" accept="image/*" class="hidden" onchange="this.form.submit()" />
                    </label>
                </form>
            </div>


            <div class="flex-grow pt-2">
                <h1 class="text-3xl font-bold text-gray-900">
                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                    (<?= $user['numero_reference'] ?? 'N° non attribué' ?>)
                </h1>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-gray-500 mt-2 text-sm">Inscrit depuis le
                    <?= date("d/m/Y", strtotime($user['created_at'])) ?>
                </p>
            </div>
        </div>

        <div class="border-t border-gray-200 px-8 pb-8">
            <div class="text-sm flex flex-col md:flex-row border-t border-gray-200 px-2 pt-6 pb-8 space-x-4">
                <a href="compte-supprimer.php"
                    class="transition hover:underline text-white bg-red-500 py-1 px-2">Supprimer mon compte</a>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="mb-4 bg-red-100 text-red-700 p-3 rounded">
                    <?= implode('<br>', $errors) ?>
                </div>
            <?php endif; ?>
            <?php
// Vérifie s'il y a au moins un champ manquant
$hasMissing = false;
foreach ($requiredFields as $field => $label) {
    if ($field === 'pays_recherche' || $field === 'autorisations_travail') {
        $filled = !empty($user[$field]) && (count(json_decode($user[$field], true) ?: []) > 0);
    } else {
        $filled = !empty($user[$field]) && (is_string($user[$field]) ? trim($user[$field]) !== '' : true);
    }
    if (!$filled) {
        $hasMissing = true;
        break;
    }
}

if ($hasMissing): ?>
<ul class="flex flex-wrap p-2 gap-3 text-sm">
    <?php foreach ($requiredFields as $field => $label):
        if ($field === 'pays_recherche' || $field === 'autorisations_travail') {
            $filled = !empty($user[$field]) && (count(json_decode($user[$field], true) ?: []) > 0);
        } else {
            $filled = !empty($user[$field]) && (is_string($user[$field]) ? trim($user[$field]) !== '' : true);
        }
        ?>
        <li class="flex items-center space-x-2">
            <span class="w-3 h-3 rounded-full flex-shrink-0 <?= $filled ? 'bg-green-500' : 'bg-red-500' ?>"></span>
            <span class="<?= $filled ? 'text-green-700' : 'text-red-700 font-semibold' ?>">
                <?= htmlspecialchars($label) ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>


            <form method="post" class="space-y-6 max-w-xl mt-10">

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block font-semibold mb-1">Prénom</label>
                        <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Nom</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none" />
                    </div>
                </div>
                <div>
                    <label class="block font-semibold mb-1">Email</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                        class="w-full border border-gray-300 px-3 py-2 bg-gray-100 cursor-not-allowed" />
                </div>
                <div class="grid grid-cols-3 gap-4 items-end">
                    <div class="col-span-1">
                        <label class="block font-semibold mb-1">Indicatif *</label>
                        <select id="telephone_indicatif" name="telephone_indicatif"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none"></select>
                    </div>
                    <div class="col-span-2">
                        <label class="block font-semibold mb-1">Téléphone</label>
                        <input type="tel" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"
                            class="w-full border border-gray-300 px-3 py-2 focus:outline-none" />
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
                    </div>
                </div>

                <!-- Bloc Pays recherche emploi -->
                <div class="mb-6">
                    <label class="block font-semibold mb-1">
                        Dans quels pays recherchez-vous un emploi ?
                        <span class="text-gray-400 text-xs">(Max 10 pays)</span>
                    </label>
                    <select name="pays_recherche[]" id="pays_recherche" multiple size="10"
                        class="w-full border border-gray-300 px-3 py-2 focus:outline-none" style="height:200px;">
                    </select>
                    <div class="text-gray-500 text-xs mt-1">Maintenir Ctrl (Windows) ou Cmd (Mac) pour sélectionner
                        plusieurs pays (max 10).</div>
                </div>

                <!-- Bloc autorisations travail -->
                <div class="mb-6">
                    <label class="block font-semibold mb-1">Pour quel pays détenez-vous les autorisations de travail ?
                        <span class="text-orange-500 font-bold">*</span></label>
                    <select name="autorisations_travail[]" id="autorisations_travail" multiple size="10" required
                        class="w-full border border-gray-300 px-3 py-2 focus:outline-none" style="height:200px;">
                    </select>
                    <div class="text-gray-500 text-xs mt-1">Maintenir Ctrl (Windows) ou Cmd (Mac) pour sélectionner
                        plusieurs pays.</div>

                </div>

                <!-- Bloc motivations -->
                <div class="mb-6">
                    <label class="block font-semibold mb-1">Motivations</label>
                    <textarea name="motivations" id="motivations" rows="6"
                        class="w-full border border-gray-300 px-3 py-2 focus:outline-none"
                        placeholder="Exprimez librement vos motivations..."><?= htmlspecialchars($user['motivations'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="bg-orange-500 hover:bg-orange-600 transition text-white px-6 py-3">Mettre à
                    jour</button>
            </form>

            <!-- Uploads fichiers -->
            <div class="mt-10 space-y-6 max-w-xl">
                <form method="post" enctype="multipart/form-data">
                    <label class="block font-semibold mt-2">CV (PDF/DOC/Image) :</label>
                    <?php if (!empty($user['cv'])): ?>
                        <a href="uploads/<?= htmlspecialchars($user['cv']) ?>" target="_blank"
                            class="text-blue-600 underline">Voir</a>
                    <?php endif; ?>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="block mt-2"
                        onchange="this.form.submit()" />
                </form>
                <form method="post" enctype="multipart/form-data">
                    <label class="block font-semibold mt-2">Diplôme (PDF/Image):</label>
                    <?php if (!empty($user['diplome'])): ?>
                        <a href="uploads/<?= htmlspecialchars($user['diplome']) ?>" target="_blank"
                            class="text-blue-600 underline">Voir</a>
                    <?php endif; ?>
                    <input type="file" name="diplome" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="block mt-2"
                        onchange="this.form.submit()" />
                </form>
                <form method="post" enctype="multipart/form-data">
                    <label class="block font-semibold mt-2">Spécialité du diplôme (PDF/Image):</label>
                    <?php if (!empty($user['diplome_specialite'])): ?>
                        <a href="uploads/<?= htmlspecialchars($user['diplome_specialite']) ?>" target="_blank"
                            class="text-blue-600 underline">Voir</a>
                    <?php endif; ?>
                    <input type="file" name="diplome_specialite" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                        class="block mt-2" onchange="this.form.submit()" />
                </form>
                <form method="post" enctype="multipart/form-data">
                    <label class="block font-semibold mt-2">Reconnaissance (PDF/Image):</label>
                    <?php if (!empty($user['reconnaissance'])): ?>
                        <a href="uploads/<?= htmlspecialchars($user['reconnaissance']) ?>" target="_blank"
                            class="text-blue-600 underline">Voir</a>
                    <?php endif; ?>
                    <input type="file" name="reconnaissance" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                        class="block mt-2" onchange="this.form.submit()" />
                </form>
            </div>
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
        const currentIndicatif = '<?= addslashes($user['telephone_indicatif'] ?? '') ?>';
        if (currentIndicatif) { select.value = currentIndicatif; }
    }
    async function initMap() {
        const ville = '<?= addslashes($user['ville'] ?? '') ?>';
        const pays = '<?= addslashes($user['pays'] ?? '') ?>';
        const adresse = `${ville}, ${pays}`;
        if (!ville && !pays) return;
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(adresse)}`;
            const response = await fetch(url);
            const data = await response.json();
            if (!data.length) return;
            const lat = data[0].lat;
            const lon = data[0].lon;
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
        const selectPays = document.getElementById('pays');
        selectPays.innerHTML = '<option value="" disabled selected>Chargement en cours...</option>';
        const response = await fetch('https://restcountries.com/v3.1/all?fields=name');
        const countries = await response.json();
        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));
        let options = '<option value="" disabled>Choisir un pays</option>';
        countries.forEach(country => {
            const countryName = country.name.common || '';
            const selected = (countryName === '<?= addslashes($user['pays'] ?? '') ?>') ? 'selected' : '';
            options += `<option value="${countryName}" ${selected}>${countryName}</option>`;
        });
        selectPays.innerHTML = options;
        selectPays.disabled = false;
        if (selectPays.value) {
            updateCities('pays', 'ville');
        }
    }

    async function loadAutorisationsTravail() {
        const select = document.getElementById('autorisations_travail');
        if (!select) return;
        select.innerHTML = '';
        const response = await fetch('https://restcountries.com/v3.1/all?fields=name');
        const countries = await response.json();
        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));
        let selected = <?= json_encode(array_map('trim', json_decode($user['autorisations_travail'] ?? '[]', true) ?: [])) ?>;
        countries.forEach(country => {
            const name = country.name.common;
            const isSel = selected.includes(name) ? 'selected' : '';
            select.insertAdjacentHTML('beforeend', `<option value="${name}" ${isSel}>${name}</option>`);
        });
    }


    // Multi-select pays_recherche
    async function loadPaysRecherche() {
        const select = document.getElementById('pays_recherche');
        if (!select) return;
        select.innerHTML = '';
        const response = await fetch('https://restcountries.com/v3.1/all?fields=name');
        const countries = await response.json();
        countries.sort((a, b) => a.name.common.localeCompare(b.name.common));
        let selected = <?= json_encode(array_map('trim', json_decode($user['pays_recherche'] ?? '[]', true) ?: [])) ?>;
        countries.forEach(country => {
            const name = country.name.common;
            const isSel = selected.includes(name) ? 'selected' : '';
            select.insertAdjacentHTML('beforeend', `<option value="${name}" ${isSel}>${name}</option>`);
        });
        select.addEventListener('change', function () {
            if ([...this.selectedOptions].length > 10) {
                this.selectedOptions[10].selected = false;
                alert("Vous pouvez sélectionner jusqu'à 10 pays maximum.");
            }
        });
    }
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
        try {
            const responseCode = await fetch(`https://restcountries.com/v3.1/name/${encodeURIComponent(countryName)}?fields=cca2`);
            const dataCode = await responseCode.json();
            if (!Array.isArray(dataCode) || !dataCode.length || !dataCode[0].cca2) {
                villeSelect.innerHTML = '<option value="" disabled>Code pays introuvable</option>';
                return;
            }
            const countryCode = dataCode[0].cca2;
            const username = 'sunderr';
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
                const selected = city.name === '<?= addslashes($user['ville'] ?? '') ?>' ? 'selected' : '';
                villeSelect.insertAdjacentHTML('beforeend', `<option value="${city.name}" ${selected}>${city.name}</option>`);
            });
            villeSelect.disabled = false;
        } catch (e) {
            villeSelect.innerHTML = '<option value="" disabled>Erreur de chargement</option>';
            console.error(e);
        }
    }
    document.getElementById('pays').addEventListener('change', () => {
        updateCities('pays', 'ville');
    });
    document.addEventListener('DOMContentLoaded', () => {
        loadIndicatifs();
        loadCountries();
        loadPaysRecherche();
        loadAutorisationsTravail();
        initMap();
    });
</script>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_dashboard.php';
?>