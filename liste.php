<?php
require_once './includes/config.php';

$filterTerm = trim($_GET['term'] ?? '');
$recruteurId = $_SESSION['user_id'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = $limitPerPage;
$offset = ($page - 1) * $perPage;
$isTestMode = false;

if ($isTestMode) {
    // Données simulées
    $profilsSimules = [];
    for ($i = 1; $i <= 35; $i++) {
        $profilsSimules[] = [
            'id' => $i,
            'numero_reference' => 'CAND-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'prenom' => 'Prenom' . $i,
            'nom' => 'Nom' . $i,
            'photo' => null,
            'fonction' => 'Fonction ' . $i,
            'inscription' => '2023-01-01',
            'delai_preavis' => ($i % 4 === 0) ? 0 : 3,
            'pays_recherche' => 'France',
            'consulted' => ($i % 5 === 0),
            'ville' => 'Ville' . $i,
            'pays' => 'Pays' . $i,
            'telephone' => '060000000' . $i,
        ];
    }
    $countResults = count($profilsSimules);
    $chunkProfils = array_slice($profilsSimules, 0, $offset + $perPage);
    $profils = array_slice($chunkProfils, $offset, $perPage);
    $totalPages = ceil($countResults / $perPage);
} else {
    $cleanFilter = str_replace(' ', '', mb_strtolower($filterTerm));

    $sql = "
        SELECT c.*, u.created_at AS inscription,
               (SELECT 1 FROM consultations WHERE user_id = ? AND candidat_id = c.id LIMIT 1) AS consulted
        FROM candidats c
        LEFT JOIN users u ON c.id = u.id
        WHERE 
          numero_reference IS NOT NULL AND numero_reference <> '' AND
          photo IS NOT NULL AND photo <> '' AND
          prenom IS NOT NULL AND prenom <> '' AND
          nom IS NOT NULL AND nom <> '' AND
          fonction IS NOT NULL AND fonction <> '' AND
          ville IS NOT NULL AND ville <> '' AND
          pays IS NOT NULL AND pays <> '' AND
          telephone IS NOT NULL AND telephone <> '' AND
          telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND
          delai_preavis IS NOT NULL AND delai_preavis <> '' AND
          cv IS NOT NULL AND cv <> '' AND
          diplome IS NOT NULL AND diplome <> '' AND
          diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND
          reconnaissance IS NOT NULL AND reconnaissance <> '' AND
          pays_recherche IS NOT NULL AND pays_recherche <> '' AND
          autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND
          motivations IS NOT NULL AND motivations <> ''";

    $params = [$recruteurId];

    if ($filterTerm !== '') {
        $sql .= " AND (REPLACE(LOWER(c.ville), ' ', '') LIKE ? OR REPLACE(LOWER(c.pays), ' ', '') LIKE ?)";
        $likeTerm = '%' . $cleanFilter . '%';
        $params[] = $likeTerm;
        $params[] = $likeTerm;
    }

    $sql .= " LIMIT $perPage OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profils = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($filterTerm !== '') {
        $countSql = "SELECT COUNT(*) FROM candidats WHERE 
            numero_reference IS NOT NULL AND numero_reference <> '' AND
            photo IS NOT NULL AND photo <> '' AND
            prenom IS NOT NULL AND prenom <> '' AND
            nom IS NOT NULL AND nom <> '' AND
            fonction IS NOT NULL AND fonction <> '' AND
            ville IS NOT NULL AND ville <> '' AND
            pays IS NOT NULL AND pays <> '' AND
            telephone IS NOT NULL AND telephone <> '' AND
            telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND
            delai_preavis IS NOT NULL AND delai_preavis <> '' AND
            cv IS NOT NULL AND cv <> '' AND
            diplome IS NOT NULL AND diplome <> '' AND
            diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND
            reconnaissance IS NOT NULL AND reconnaissance <> '' AND
            pays_recherche IS NOT NULL AND pays_recherche <> '' AND
            autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND
            motivations IS NOT NULL AND motivations <> '' AND
            views IS NOT NULL AND views <> '' AND
            (REPLACE(LOWER(ville), ' ', '') LIKE ? OR REPLACE(LOWER(pays), ' ', '') LIKE ?)";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$likeTerm, $likeTerm]);
    } else {
        $countSql = "SELECT COUNT(*) FROM candidats WHERE 
            numero_reference IS NOT NULL AND numero_reference <> '' AND
            photo IS NOT NULL AND photo <> '' AND
            prenom IS NOT NULL AND prenom <> '' AND
            nom IS NOT NULL AND nom <> '' AND
            fonction IS NOT NULL AND fonction <> '' AND
            ville IS NOT NULL AND ville <> '' AND
            pays IS NOT NULL AND pays <> '' AND
            telephone IS NOT NULL AND telephone <> '' AND
            telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND
            delai_preavis IS NOT NULL AND delai_preavis <> '' AND
            cv IS NOT NULL AND cv <> '' AND
            diplome IS NOT NULL AND diplome <> '' AND
            diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND
            reconnaissance IS NOT NULL AND reconnaissance <> '' AND
            pays_recherche IS NOT NULL AND pays_recherche <> '' AND
            autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND
            motivations IS NOT NULL AND motivations <> ''";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute();
    }

    $countResults = (int) $countStmt->fetchColumn();
    $totalPages = ceil($countResults / $perPage);
}


$title = "Liste des candidats";

ob_start();
?>

<main class="grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-4xl font-extrabold mb-10 text-gray-900">
        <?= $countResults === 0 ? 'Aucun candidat disponible.' : $countResults . ' candidat(s) au total' ?>
    </h1>

    <!-- Formulaire simple sans suggestions -->
    <form method="get" class="mb-6 max-w-md">
        <label for="term_search" class="block font-semibold mb-2">Filtrer par ville ou pays</label>
        <input type="text" id="term_search" name="term" placeholder="Rechercher une ville ou un pays"
            value="<?= htmlspecialchars($filterTerm) ?>" class="w-full border bg-white px-4 py-2 outline-none"
            autocomplete="off" />
        <button type="submit"
            class="mt-3 bg-orange-600 text-white px-5 py-2 rounded-md hover:bg-orange-700 font-semibold">
            Rechercher
        </button>
        <?php if ($filterTerm !== ''): ?>
            <button type="button" onclick="window.location.href='<?= strtok($_SERVER['REQUEST_URI'], '?') ?>';"
                class="ml-3 mt-3 text-sm text-orange-600 hover:text-orange-800 font-semibold">
                Afficher tous les résultats
            </button>
        <?php endif; ?>
    </form>

    <?php if (empty($profils)): ?>
        <p class="text-center mt-16 text-gray-500 text-lg">Aucun candidat trouvé.</p>
    <?php else: ?>
        <div id="profils-container" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($profils as $p):
                $isClickable = !$isTestMode;
                $consulted = !empty($p['consulted']);

                // Préparer l'affichage des pays recherchés avant la concaténation
                $paysAfficher = 'Non renseigné';
                if (!empty($p['pays_recherche'])) {
                    $paysArray = json_decode($p['pays_recherche'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($paysArray)) {
                        $paysAfficher = implode(', ', $paysArray);
                    } else {
                        $paysAfficher = htmlspecialchars($p['pays_recherche']);
                    }
                }

                $cardContent = '
    <div class="relative bg-white rounded shadow-md border border-gray-200 p-6 min-h-[180px] flex flex-col transition hover:shadow-lg ' . ($consulted ? 'filter grayscale contrast-75' : '') . '">
        <div class="flex items-center space-x-7">
            <div class="flex-shrink-0 rounded-full overflow-hidden w-24 h-24 bg-gray-100 flex items-center justify-center">' .
                    (!empty($p['photo'])
                        ? '<img src="uploads/' . htmlspecialchars($p['photo']) . '" alt="Photo de ' . htmlspecialchars(trim(($p['prenom'] ?? '') . " " . ($p['nom'] ?? ''))) . '" class="w-full h-full object-cover" loading="lazy" />'
                        : '<img src="/assets/img/user.png" alt="Profil par défaut" class="h-14 w-14 object-cover rounded-full" />') .
                    '</div>
            <div class="flex flex-col flex-1 min-w-0">
                <h2 class="text-2xl font-semibold text-orange-600 group-hover:text-orange-700 leading-tight whitespace-normal break-words">' . htmlspecialchars($p['fonction'] ?? '') . '</h2>
                <p class="text-gray-500 text-base mt-1 whitespace-normal"><span class="font-mono text-gray-700 break-all">' . htmlspecialchars($p['numero_reference'] ?? '') . '</span></p>
            </div>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-x-8 gap-y-4 text-gray-700 text-base leading-snug">
            <div>
                <p class="font-semibold text-xs uppercase text-gray-400 tracking-widest">Inscription</p>
                <p class="whitespace-normal break-words">' . (function ($dateStr) {
                        if (!empty($dateStr) && strtotime($dateStr)) {
                            $date = new DateTime($dateStr);
                            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
                            $formatter->setPattern('eee dd MMM');
                            return mb_strtolower($formatter->format($date));
                        }
                        return 'Non renseigné';
                    })($p['inscription'] ?? '') . '</p>
            </div>
            <div>
                <p class="font-semibold text-xs uppercase text-gray-400 tracking-widest">Préavis</p>
                <p class="whitespace-normal break-words">' . ((!isset($p['delai_preavis']) || $p['delai_preavis'] === '') ? 'Non renseigné' : ((int) $p['delai_preavis'] === 0 ? 'Disponible immédiatement' : htmlspecialchars($p['delai_preavis']) . ' mois')) . '</p>
            </div>

             <div class="col-span-2">
                <p class="font-semibold text-xs uppercase text-gray-400 tracking-widest">Ville, pays</p>
                <p class="whitespace-normal break-words">' . htmlspecialchars($p['ville']) . ', ' . htmlspecialchars($p['pays']) . '</p>
            </div>
            
            <div class="col-span-2">
                <p class="font-semibold text-xs uppercase text-gray-400 tracking-widest">Pays recherché</p>
                <p class="whitespace-normal break-words">' . htmlspecialchars($paysAfficher) . '</p>
            </div>

           
        </div>' .
                    ($consulted ? '<div class="absolute right-0 top-0 z-10"><span class="inline-block bg-purple-600 text-white text-xs font-bold px-4 py-1 rounded-bl-xl shadow uppercase tracking-wider" style="border-top-right-radius:0.6rem;">Déjà consulté</span></div>' : '') .
                    '</div>';


                if ($isClickable): ?>
                    <a href="public_profil.php?ref=<?= urlencode($p['numero_reference']) ?>" class="block">
                        <?= $cardContent ?>
                    </a>
                <?php else: ?>
                    <?= $cardContent ?>
                <?php endif; ?>
            <?php endforeach; ?>

        </div>

        <?php if ($offset + $perPage < $countResults): ?>
            <div class="text-center mt-8">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                    class="inline-block bg-orange-600 text-white px-6 py-3 rounded-md font-semibold hover:bg-orange-700">
                    Voir plus
                </a>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="mt-4 text-center text-gray-600 text-sm">
                Page <span class="font-semibold"><?= $page ?></span> sur <span class="font-semibold"><?= $totalPages ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_dashboard.php';
?>