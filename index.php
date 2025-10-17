<?php
require_once './includes/config.php';

$filterTerm = trim($_GET['term'] ?? '');
$recruteurId = $_SESSION['user_id'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = $limitPerPage;
$offset = ($page - 1) * $perPage;
$isTestMode = false;

// Récupérer la liste des candidats et le total
if ($isTestMode) {
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
    $profils = array_slice($profilsSimules, $offset, $perPage);
    $totalPages = ceil($countResults / $perPage);

    $locations = [];
    foreach ($profilsSimules as $p) {
        $key = $p['ville'] . ',' . $p['pays'];
        if (!isset($locations[$key]))
            $locations[$key] = ['ville' => $p['ville'], 'pays' => $p['pays'], 'count' => 0];
        $locations[$key]['count']++;
    }
    $locations = array_values($locations);
} else {
    $cleanFilter = str_replace(' ', '', mb_strtolower($filterTerm));

    $paramsLoc = [];
    $sqlLoc = "SELECT ville, pays, COUNT(*) as count FROM candidats WHERE photo IS NOT NULL AND photo <> ''";
    if ($filterTerm !== '') {
        $sqlLoc .= " AND (REPLACE(LOWER(ville), ' ', '') LIKE ? OR REPLACE(LOWER(pays), ' ', '') LIKE ?)";
        $likeTerm = '%' . $cleanFilter . '%';
        $paramsLoc[] = $likeTerm;
        $paramsLoc[] = $likeTerm;
    }
    $sqlLoc .= " GROUP BY ville, pays ORDER BY count DESC";

    $stmtLoc = $pdo->prepare($sqlLoc);
    $stmtLoc->execute($paramsLoc);
    $locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

    $params = [$recruteurId];
    $sql = "SELECT c.*, u.created_at AS inscription, (SELECT 1 FROM consultations WHERE user_id = ? AND candidat_id = c.id LIMIT 1) AS consulted FROM candidats c LEFT JOIN users u ON c.id = u.id WHERE numero_reference IS NOT NULL AND numero_reference <> '' AND photo IS NOT NULL AND photo <> '' AND prenom IS NOT NULL AND prenom <> '' AND nom IS NOT NULL AND nom <> '' AND fonction IS NOT NULL AND fonction <> '' AND ville IS NOT NULL AND ville <> '' AND pays IS NOT NULL AND pays <> '' AND telephone IS NOT NULL AND telephone <> '' AND telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND delai_preavis IS NOT NULL AND delai_preavis <> '' AND cv IS NOT NULL AND cv <> '' AND diplome IS NOT NULL AND diplome <> '' AND diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND reconnaissance IS NOT NULL AND reconnaissance <> '' AND pays_recherche IS NOT NULL AND pays_recherche <> '' AND autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND motivations IS NOT NULL AND motivations <> ''";

    if ($filterTerm !== '') {
        $sql .= " AND (REPLACE(LOWER(c.ville), ' ', '') LIKE ? OR REPLACE(LOWER(c.pays), ' ', '') LIKE ?)";
        $params[] = $likeTerm;
        $params[] = $likeTerm;
    }

    $sql .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profils = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($filterTerm !== '') {
        $countSql = "SELECT COUNT(*) FROM candidats WHERE photo IS NOT NULL AND photo <> '' AND numero_reference IS NOT NULL AND numero_reference <> '' AND prenom IS NOT NULL AND prenom <> '' AND nom IS NOT NULL AND nom <> '' AND fonction IS NOT NULL AND fonction <> '' AND ville IS NOT NULL AND ville <> '' AND pays IS NOT NULL AND pays <> '' AND telephone IS NOT NULL AND telephone <> '' AND telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND delai_preavis IS NOT NULL AND delai_preavis <> '' AND cv IS NOT NULL AND cv <> '' AND diplome IS NOT NULL AND diplome <> '' AND diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND reconnaissance IS NOT NULL AND reconnaissance <> '' AND pays_recherche IS NOT NULL AND pays_recherche <> '' AND autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND motivations IS NOT NULL AND motivations <> '' AND (REPLACE(LOWER(ville), ' ', '') LIKE ? OR REPLACE(LOWER(pays), ' ', '') LIKE ?)";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$likeTerm, $likeTerm]);
    } else {
        $countSql = "SELECT COUNT(*) FROM candidats WHERE photo IS NOT NULL AND photo <> '' AND numero_reference IS NOT NULL AND numero_reference <> '' AND prenom IS NOT NULL AND prenom <> '' AND nom IS NOT NULL AND nom <> '' AND fonction IS NOT NULL AND fonction <> '' AND ville IS NOT NULL AND ville <> '' AND pays IS NOT NULL AND pays <> '' AND telephone IS NOT NULL AND telephone <> '' AND telephone_indicatif IS NOT NULL AND telephone_indicatif <> '' AND delai_preavis IS NOT NULL AND delai_preavis <> '' AND cv IS NOT NULL AND cv <> '' AND diplome IS NOT NULL AND diplome <> '' AND diplome_specialite IS NOT NULL AND diplome_specialite <> '' AND reconnaissance IS NOT NULL AND reconnaissance <> '' AND pays_recherche IS NOT NULL AND pays_recherche <> '' AND autorisations_travail IS NOT NULL AND autorisations_travail <> '' AND motivations IS NOT NULL AND motivations <> ''";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute();
    }
    $countResults = (int) $countStmt->fetchColumn();
    $totalPages = ceil($countResults / $perPage);
}

$title = "Liste des candidats";

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        #map {
            height: 450px;
        }
        
        .custom-marker {
            background: #007AFF;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .profile-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .profile-card:hover {
            transform: translateY(-2px);
        }
        
        .modal-backdrop {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .slide-up {
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        input:focus {
            outline: none;
        }
        
        .floating-button {
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }
        
        @media (max-width: 1024px) {
            .desktop-filters {
                display: none;
            }
            .desktop-map {
                display: none;
            }
        }
        
        @media (min-width: 1025px) {
            .mobile-buttons {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-white">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-10">
        
        <!-- En-tête avec actions mobiles -->
        <div class="flex items-center justify-between mb-6 lg:mb-8">
            <div>
                <h1 class="text-2xl lg:text-3xl font-semibold text-gray-900 tracking-tight">Candidats</h1>
                <p class="text-sm text-gray-500 mt-1"><?= $countResults ?> profil<?= $countResults > 1 ? 's' : '' ?> disponible<?= $countResults > 1 ? 's' : '' ?></p>
            </div>
            
            <!-- Boutons mobiles -->
            <div class="mobile-buttons flex items-center space-x-2">
                <button onclick="openFiltersModal()" class="w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                    <i class="fas fa-sliders-h text-gray-700 text-sm"></i>
                </button>
                <button onclick="openMapModal()" class="w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                    <i class="fas fa-map text-gray-700 text-sm"></i>
                </button>
            </div>
            
            <!-- Toggle vue desktop -->
            <div class="hidden lg:flex items-center space-x-2 bg-gray-100 rounded-full p-1">
                <button 
                    id="gridViewBtn"
                    onclick="switchView('grid')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-all bg-white text-gray-900 shadow-sm"
                >
                    <i class="fas fa-th mr-1.5"></i>Grille
                </button>
                <button 
                    id="listViewBtn"
                    onclick="switchView('list')"
                    class="px-4 py-2 rounded-full text-sm font-medium transition-all text-gray-600 hover:text-gray-900"
                >
                    <i class="fas fa-list mr-1.5"></i>Liste
                </button>
            </div>
        </div>

        <!-- Desktop: Filtres + Carte -->
        <div class="desktop-filters grid grid-cols-4 gap-6 mb-8">
            <!-- Filtres Desktop -->
            <div class="col-span-1">
                <div class="bg-gray-50 rounded-3xl p-6 sticky top-4">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4 tracking-wide uppercase">Filtres</h2>
                    
                    <form method="get" class="space-y-4">
                        <div>
                            <label for="term_search" class="block text-xs font-medium text-gray-600 mb-2 uppercase tracking-wider">
                                Localisation
                            </label>
                            <input 
                                type="text" 
                                id="term_search" 
                                name="term" 
                                class="w-full px-4 py-3 bg-white border-0 rounded-full text-sm focus:ring-2 focus:ring-blue-500 transition-shadow"
                                placeholder="Ville ou pays" 
                                value="<?= htmlspecialchars($filterTerm) ?>"
                                autocomplete="off"
                            />
                        </div>

                        <button type="submit" class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-full transition-colors">
                            Rechercher
                        </button>

                        <?php if ($filterTerm !== ''): ?>
                            <button 
                                type="button" 
                                onclick="window.location.href='<?= strtok($_SERVER['REQUEST_URI'], '?') ?>';"
                                class="w-full px-4 py-3 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-full transition-colors"
                            >
                                Réinitialiser
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Carte Desktop -->
            <div class="col-span-3">
                <div class="bg-gray-50 rounded-3xl overflow-hidden">
                    <div id="map"></div>
                </div>
            </div>
        </div>

        <!-- Résultats -->
        <?php if (empty($profils)): ?>
            <div class="bg-gray-50 rounded-3xl p-16 text-center">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-gray-400 text-xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucun résultat</h3>
                <p class="text-sm text-gray-500">Modifiez vos critères de recherche</p>
            </div>
        <?php else: ?>
            
            <!-- Vue Grille -->
            <div id="gridView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <?php foreach ($profils as $p):
                    $isClickable = !$isTestMode;
                    $consulted = !empty($p['consulted']);
                    
                    $paysAfficher = 'Non renseigné';
                    if (!empty($p['pays_recherche'])) {
                        $paysArray = json_decode($p['pays_recherche'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($paysArray)) {
                            $paysAfficher = implode(', ', $paysArray);
                        } else {
                            $paysAfficher = htmlspecialchars($p['pays_recherche']);
                        }
                    }
                    
                    $dateInscription = '';
                    if (!empty($p['inscription']) && strtotime($p['inscription'])) {
                        $date = new DateTime($p['inscription']);
                        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
                        $formatter->setPattern('dd MMM yyyy');
                        $dateInscription = $formatter->format($date);
                    }
                    
                    $delaiPreavis = '';
                    $isAvailable = false;
                    if (isset($p['delai_preavis']) && $p['delai_preavis'] !== '') {
                        if ((int)$p['delai_preavis'] === 0) {
                            $delaiPreavis = 'Disponible';
                            $isAvailable = true;
                        } else {
                            $delaiPreavis = htmlspecialchars($p['delai_preavis']) . ' mois';
                        }
                    }
                    
                    $cardContent = '
                    <div class="profile-card bg-white border border-gray-200 rounded-3xl p-5 lg:p-6 h-full relative ' . ($consulted ? 'opacity-50' : '') . '">
                        
                        <div class="flex items-start space-x-4 mb-5">
                            <div class="flex-shrink-0">
                                <div class="w-14 h-14 lg:w-16 lg:h-16 rounded-full overflow-hidden bg-gray-100">' .
                                    (!empty($p['photo'])
                                        ? '<img src="uploads/' . htmlspecialchars($p['photo']) . '" alt="Photo" class="w-full h-full object-cover" loading="lazy" />'
                                        : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-gray-300 text-lg"></i></div>') .
                                '</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-1 line-clamp-2">' . htmlspecialchars($p['fonction'] ?? '') . '</h3>
                                <p class="text-xs text-gray-400 font-mono">' . htmlspecialchars($p['numero_reference'] ?? '') . '</p>
                            </div>
                        </div>

                        <div class="space-y-3 mb-5">
                            ' . ($dateInscription ? '
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Inscrit</span>
                                <span class="text-gray-900 font-medium">' . $dateInscription . '</span>
                            </div>
                            ' : '') . '
                            
                            ' . ($delaiPreavis ? '
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Préavis</span>
                                ' . ($isAvailable ? 
                                    '<span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full font-medium">' . $delaiPreavis . '</span>' 
                                    : '<span class="text-gray-900 font-medium">' . $delaiPreavis . '</span>') . '
                            </div>
                            ' : '') . '
                            
                            <div class="flex items-start justify-between text-xs pt-2 border-t border-gray-100">
                                <span class="text-gray-500">Localisation</span>
                                <span class="text-gray-900 font-medium text-right">' . htmlspecialchars($p['ville']) . '</span>
                            </div>
                        </div>

                        ' . ($consulted ? '
                        <div class="absolute top-5 right-5">
                            <div class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-check text-purple-600 text-xs"></i>
                            </div>
                        </div>
                        ' : '') . '
                    </div>';

                    if ($isClickable): ?>
                        <a href="public_profil.php?ref=<?= urlencode($p['numero_reference']) ?>" class="block h-full">
                            <?= $cardContent ?>
                        </a>
                    <?php else: ?>
                        <?= $cardContent ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Vue Liste -->
            <div id="listView" class="hidden space-y-3">
                <?php foreach ($profils as $p):
                    $isClickable = !$isTestMode;
                    $consulted = !empty($p['consulted']);
                    
                    $paysAfficher = 'Non renseigné';
                    if (!empty($p['pays_recherche'])) {
                        $paysArray = json_decode($p['pays_recherche'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($paysArray)) {
                            $paysAfficher = implode(', ', $paysArray);
                        } else {
                            $paysAfficher = htmlspecialchars($p['pays_recherche']);
                        }
                    }
                    
                    $delaiPreavis = '';
                    $isAvailable = false;
                    if (isset($p['delai_preavis']) && $p['delai_preavis'] !== '') {
                        if ((int)$p['delai_preavis'] === 0) {
                            $delaiPreavis = 'Disponible';
                            $isAvailable = true;
                        } else {
                            $delaiPreavis = htmlspecialchars($p['delai_preavis']) . ' mois';
                        }
                    }
                    
                    $listContent = '
                    <div class="profile-card bg-white border border-gray-200 rounded-3xl p-5 ' . ($consulted ? 'opacity-50' : '') . '">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-100">' .
                                    (!empty($p['photo'])
                                        ? '<img src="uploads/' . htmlspecialchars($p['photo']) . '" alt="Photo" class="w-full h-full object-cover" loading="lazy" />'
                                        : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-gray-300"></i></div>') .
                                '</div>
                            </div>
                            
                            <div class="flex-1 min-w-0 grid grid-cols-1 lg:grid-cols-3 gap-3 items-center">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 truncate">' . htmlspecialchars($p['fonction'] ?? '') . '</h3>
                                    <p class="text-xs text-gray-400 font-mono">' . htmlspecialchars($p['numero_reference'] ?? '') . '</p>
                                </div>
                                
                                <div class="text-xs text-gray-500">
                                    ' . htmlspecialchars($p['ville']) . '
                                </div>
                                
                                <div class="flex items-center justify-end">
                                    ' . ($isAvailable ? 
                                        '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">' . $delaiPreavis . '</span>' 
                                        : '<span class="text-xs text-gray-500">' . $delaiPreavis . '</span>') . '
                                </div>
                            </div>
                            
                            ' . ($consulted ? '
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check text-purple-600 text-xs"></i>
                                </div>
                            </div>
                            ' : '') . '
                        </div>
                    </div>';

                    if ($isClickable): ?>
                        <a href="public_profil.php?ref=<?= urlencode($p['numero_reference']) ?>" class="block">
                            <?= $listContent ?>
                        </a>
                    <?php else: ?>
                        <?= $listContent ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Page <?= $page ?> sur <?= $totalPages ?>
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                                <i class="fas fa-chevron-left text-gray-700 text-sm"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="w-10 h-10 flex items-center justify-center bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">
                                <i class="fas fa-chevron-right text-gray-700 text-sm"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Filtres (Mobile) -->
    <div id="filtersModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-30" onclick="closeFiltersModal()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl slide-up max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900">Filtres</h2>
                    <button onclick="closeFiltersModal()" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>
                
                <form method="get" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-2 uppercase tracking-wider">
                            Localisation
                        </label>
                        <input 
                            type="text" 
                            name="term" 
                            class="w-full px-4 py-3 bg-gray-50 border-0 rounded-full text-sm focus:ring-2 focus:ring-blue-500"
                            placeholder="Ville ou pays" 
                            value="<?= htmlspecialchars($filterTerm) ?>"
                        />
                    </div>

                    <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white text-sm font-medium rounded-full">
                        Appliquer
                    </button>

                    <?php if ($filterTerm !== ''): ?>
                        <button 
                            type="button" 
                            onclick="window.location.href='<?= strtok($_SERVER['REQUEST_URI'], '?') ?>';"
                            class="w-full px-4 py-3 bg-gray-100 text-gray-700 text-sm font-medium rounded-full"
                        >
                            Réinitialiser
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Carte (Mobile) -->
    <div id="mapModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black bg-opacity-30" onclick="closeMapModal()"></div>
        <div class="absolute inset-4 bg-white rounded-3xl slide-up overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Carte des candidats</h2>
                <button onclick="closeMapModal()" class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded-full">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
            <div class="flex-1">
                <div id="mapMobile" style="height: 100%;"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        let mobileMap = null;
        
        // Modals
        function openFiltersModal() {
            document.getElementById('filtersModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeFiltersModal() {
            document.getElementById('filtersModal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                if (!mobileMap) {
                    initMobileMap();
                } else {
                    mobileMap.invalidateSize();
                }
            }, 100);
        }
        
        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Toggle vue
        function switchView(view) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');
            
            if (view === 'grid') {
                gridView.classList.remove('hidden');
                listView.classList.add('hidden');
                gridBtn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                gridBtn.classList.remove('text-gray-600');
                listBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
                listBtn.classList.add('text-gray-600');
            } else {
                gridView.classList.add('hidden');
                listView.classList.remove('hidden');
                listBtn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                listBtn.classList.remove('text-gray-600');
                gridBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
                gridBtn.classList.add('text-gray-600');
            }
        }

        // Carte Desktop
        const locations = <?= json_encode($locations) ?>;
        const username = 'sunderr';

        if (window.innerWidth > 1024) {
            const map = L.map('map', { zoomControl: false }).setView([48.8566, 2.3522], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 18
            }).addTo(map);

            L.control.zoom({ position: 'topright' }).addTo(map);

            const markers = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false
            });

            initMarkers(map, markers);
        }

        function initMobileMap() {
            mobileMap = L.map('mapMobile', { zoomControl: false }).setView([48.8566, 2.3522], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 18
            }).addTo(mobileMap);

            L.control.zoom({ position: 'topright' }).addTo(mobileMap);

            const markers = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false
            });

            initMarkers(mobileMap, markers);
        }

        async function initMarkers(mapInstance, markersGroup) {
            for (const loc of locations) {
                const coords = await geocode(loc.ville, loc.pays);
                if (coords) {
                    const marker = L.marker(coords, {
                        icon: L.divIcon({
                            className: 'custom-marker',
                            html: '<div class="custom-marker"></div>',
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        })
                    });
                    
                    marker.bindPopup(`
                        <div style="padding: 8px; text-align: center;">
                            <div style="font-weight: 600; color: #111827; margin-bottom: 4px;">${loc.ville}, ${loc.pays}</div>
                            <div style="font-size: 13px; color: #6b7280;">${loc.count} candidat${loc.count > 1 ? 's' : ''}</div>
                        </div>
                    `);
                    
                    markersGroup.addLayer(marker);
                }
            }
            
            mapInstance.addLayer(markersGroup);
            
            if (locations.length > 0) {
                mapInstance.fitBounds(markersGroup.getBounds().pad(0.2));
            }
        }

        async function geocode(ville, pays) {
            const countryCode = pays.slice(0, 2).toUpperCase();
            const query = encodeURIComponent(ville);
            const url = `https://secure.geonames.org/searchJSON?q=${query}&country=${countryCode}&featureClass=P&maxRows=1&username=${username}`;
            try {
                const resp = await fetch(url);
                const data = await resp.json();
                if (data.geonames && data.geonames.length > 0) {
                    return [data.geonames[0].lat, data.geonames[0].lng];
                }
            } catch (e) {
                console.error('Geocoding error', e);
            }
            return null;
        }
    </script>
</body>

</html>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_home.php';
?>