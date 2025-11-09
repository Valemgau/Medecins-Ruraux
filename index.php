<?php
require_once './includes/config.php';

// Configuration
define('RESULTS_PER_PAGE', 10);
define('GEONAMES_USERNAME', 'sunderr');

// Récupération sécurisée des paramètres
$filterTerm = trim($_GET['term'] ?? '');
$filterPreavis = $_GET['preavis'] ?? '';
$filterAvecReconnaissance = isset($_GET['avec_reconnaissance']) && $_GET['avec_reconnaissance'] === '1';
$recruteurId = $_SESSION['user_id'] ?? null; // Null si utilisateur non connecté
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * RESULTS_PER_PAGE;
$isTestMode = false;

// Fonction de calcul de disponibilité
function calculateAvailability($inscription, $delaiPreavis) {
    if (empty($inscription) || $delaiPreavis === '' || $delaiPreavis === null) {
        return ['text' => '', 'available' => false, 'shortText' => ''];
    }
    
    try {
        $dateCreation = new DateTime($inscription);
        $dateDisponibilite = clone $dateCreation;
        $dateDisponibilite->modify('+' . (int)$delaiPreavis . ' months');
        $now = new DateTime();
        
        if ($dateDisponibilite <= $now) {
            return [
                'text' => 'Disponible immédiatement',
                'available' => true,
                'shortText' => 'Disponible'
            ];
        }
        
        $diff = $now->diff($dateDisponibilite);
        $moisRestants = ($diff->y * 12) + $diff->m;
        if ($diff->d > 0) $moisRestants++;
        
        if ($moisRestants === 0) {
            $jours = $diff->d;
            return [
                'text' => 'Disponible sous ' . $jours . ' jour' . ($jours > 1 ? 's' : ''),
                'available' => false,
                'shortText' => $jours . ' jour' . ($jours > 1 ? 's' : '')
            ];
        }
        
        return [
            'text' => 'Disponible dans ' . $moisRestants . ' mois',
            'available' => false,
            'shortText' => $moisRestants . ' mois'
        ];
    } catch (Exception $e) {
        return [
            'text' => 'Préavis de ' . htmlspecialchars($delaiPreavis) . ' mois',
            'available' => false,
            'shortText' => htmlspecialchars($delaiPreavis) . ' mois'
        ];
    }
}

// Fonction de formatage de date
function formatInscriptionDate($dateString) {
    if (empty($dateString) || !strtotime($dateString)) {
        return '';
    }
    
    try {
        $date = new DateTime($dateString);
        $formatter = new IntlDateFormatter(
            'fr_FR',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE
        );
        $formatter->setPattern('dd MMM yyyy');
        return $formatter->format($date);
    } catch (Exception $e) {
        return '';
    }
}

// Récupération des données
if ($isTestMode) {
    // Mode test (simulation)
    $profilsSimules = [];
    for ($i = 1; $i <= 35; $i++) {
        $profilsSimules[] = [
            'id' => $i,
            'numero_reference' => 'CAND-' . str_pad($i, 4, '0', STR_PAD_LEFT),
            'prenom' => 'Prenom' . $i,
            'nom' => 'Nom' . $i,
            'photo' => null,
            'photo_blurred' => null,
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
    $profils = array_slice($profilsSimules, $offset, RESULTS_PER_PAGE);
    $totalPages = ceil($countResults / RESULTS_PER_PAGE);

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
    $likeTerm = '%' . $cleanFilter . '%';

    // Requête pour les locations (avec filtres appliqués)
    $paramsLoc = [];
    $sqlLoc = "SELECT ville, pays, COUNT(*) as count 
               FROM candidats c
               LEFT JOIN users u ON c.id = u.id
               WHERE c.photo IS NOT NULL AND c.photo <> ''
               AND c.numero_reference IS NOT NULL AND c.numero_reference <> ''";
    
    if ($filterTerm !== '') {
        $sqlLoc .= " AND (REPLACE(LOWER(c.ville), ' ', '') LIKE ? OR REPLACE(LOWER(c.pays), ' ', '') LIKE ?)";
        $paramsLoc[] = $likeTerm;
        $paramsLoc[] = $likeTerm;
    }

    if ($filterPreavis !== '' && is_numeric($filterPreavis)) {
        $sqlLoc .= " AND DATE_ADD(u.created_at, INTERVAL c.delai_preavis MONTH) <= DATE_ADD(NOW(), INTERVAL ? MONTH)";
        $paramsLoc[] = (int)$filterPreavis;
    }

    if ($filterAvecReconnaissance) {
        $sqlLoc .= " AND c.reconnaissance IS NOT NULL AND c.reconnaissance <> ''";
    }
    
    $sqlLoc .= " GROUP BY c.ville, c.pays ORDER BY count DESC";

    $stmtLoc = $pdo->prepare($sqlLoc);
    $stmtLoc->execute($paramsLoc);
    $locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

    // Construction de la requête principale
    $params = [];
    $sql = "SELECT c.*, 
                   u.created_at AS inscription";
    
    // Ajouter la colonne 'consulted' seulement si l'utilisateur est connecté
    if ($recruteurId !== null) {
        $sql .= ", (SELECT 1 FROM consultations WHERE user_id = ? AND candidat_id = c.id LIMIT 1) AS consulted";
        $params[] = $recruteurId;
    } else {
        $sql .= ", 0 AS consulted"; // Pas consulté si non connecté
    }
    
    $sql .= " FROM candidats c 
            LEFT JOIN users u ON c.id = u.id 
            WHERE c.numero_reference IS NOT NULL AND c.numero_reference <> '' 
            AND c.photo IS NOT NULL AND c.photo <> '' 
            AND c.prenom IS NOT NULL AND c.prenom <> '' 
            AND c.nom IS NOT NULL AND c.nom <> '' 
            AND c.fonction IS NOT NULL AND c.fonction <> '' 
            AND c.ville IS NOT NULL AND c.ville <> '' 
            AND c.pays IS NOT NULL AND c.pays <> '' 
            AND c.telephone IS NOT NULL AND c.telephone <> '' 
            AND c.telephone_indicatif IS NOT NULL AND c.telephone_indicatif <> '' 
            AND c.delai_preavis IS NOT NULL AND c.delai_preavis <> '' 
            AND c.cv IS NOT NULL AND c.cv <> '' 
            AND c.diplome IS NOT NULL AND c.diplome <> '' 
            AND c.diplome_specialite IS NOT NULL AND c.diplome_specialite <> '' 
            AND c.motivations IS NOT NULL AND c.motivations <> ''";

    // Filtre localisation
    if ($filterTerm !== '') {
        $sql .= " AND (REPLACE(LOWER(c.ville), ' ', '') LIKE ? OR REPLACE(LOWER(c.pays), ' ', '') LIKE ?)";
        $params[] = $likeTerm;
        $params[] = $likeTerm;
    }

    // Filtre durée de préavis
    if ($filterPreavis !== '' && is_numeric($filterPreavis)) {
        $sql .= " AND DATE_ADD(u.created_at, INTERVAL c.delai_preavis MONTH) <= DATE_ADD(NOW(), INTERVAL ? MONTH)";
        $params[] = (int)$filterPreavis;
    }

    // Filtre avec reconnaissance
    if ($filterAvecReconnaissance) {
        $sql .= " AND c.reconnaissance IS NOT NULL AND c.reconnaissance <> ''";
    }

    // Pagination sécurisée (LIMIT et OFFSET doivent être des entiers, pas des paramètres bindés)
    $sql .= " LIMIT " . (int)RESULTS_PER_PAGE . " OFFSET " . (int)$offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profils = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Requête count (mêmes filtres, mêmes paramètres)
    $countParams = [];
    $countSql = "SELECT COUNT(*) 
                 FROM candidats c 
                 LEFT JOIN users u ON c.id = u.id 
                 WHERE c.numero_reference IS NOT NULL AND c.numero_reference <> '' 
                 AND c.photo IS NOT NULL AND c.photo <> '' 
                 AND c.prenom IS NOT NULL AND c.prenom <> '' 
                 AND c.nom IS NOT NULL AND c.nom <> '' 
                 AND c.fonction IS NOT NULL AND c.fonction <> '' 
                 AND c.ville IS NOT NULL AND c.ville <> '' 
                 AND c.pays IS NOT NULL AND c.pays <> '' 
                 AND c.telephone IS NOT NULL AND c.telephone <> '' 
                 AND c.telephone_indicatif IS NOT NULL AND c.telephone_indicatif <> '' 
                 AND c.delai_preavis IS NOT NULL AND c.delai_preavis <> '' 
                 AND c.cv IS NOT NULL AND c.cv <> '' 
                 AND c.diplome IS NOT NULL AND c.diplome <> '' 
                 AND c.diplome_specialite IS NOT NULL AND c.diplome_specialite <> '' 
                 AND c.motivations IS NOT NULL AND c.motivations <> ''";
    
    if ($filterTerm !== '') {
        $countSql .= " AND (REPLACE(LOWER(c.ville), ' ', '') LIKE ? OR REPLACE(LOWER(c.pays), ' ', '') LIKE ?)";
        $countParams[] = $likeTerm;
        $countParams[] = $likeTerm;
    }

    if ($filterPreavis !== '' && is_numeric($filterPreavis)) {
        $countSql .= " AND DATE_ADD(u.created_at, INTERVAL c.delai_preavis MONTH) <= DATE_ADD(NOW(), INTERVAL ? MONTH)";
        $countParams[] = (int)$filterPreavis;
    }

    if ($filterAvecReconnaissance) {
        $countSql .= " AND c.reconnaissance IS NOT NULL AND c.reconnaissance <> ''";
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $countResults = (int) $countStmt->fetchColumn();
    $totalPages = ceil($countResults / RESULTS_PER_PAGE);
}

$title = "Liste des candidats";

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    
    <style>
        #map {
            height: 500px;
        }
        
        #mapMobile {
            height: 100%;
        }
        
        .custom-marker {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            transition: transform 0.2s;
        }
        
        .custom-marker:hover {
            transform: scale(1.1);
        }
        
        .profile-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e5e7eb;
        }
        
        .profile-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            border-color: #10b981;
        }
        
        .filter-chip {
            transition: all 0.2s;
        }
        
        .filter-chip:hover {
            transform: translateY(-1px);
        }
        
        .modal-backdrop {
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease-out;
        }
        
        .slide-up {
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        @media (max-width: 1024px) {
            .desktop-layout {
                display: none;
            }
        }
        
        @media (min-width: 1025px) {
            .mobile-buttons {
                display: none;
            }
        }

        .leaflet-popup-content-wrapper {
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .leaflet-popup-tip {
            background: white;
        }
    </style>
</head>

<body class="bg-gray-50">
    
    <!-- Mobile: Boutons flottants -->
    <div class="mobile-buttons fixed bottom-6 right-6 flex flex-col gap-3 z-40">
        <button onclick="openMapModal()" class="w-14 h-14 flex items-center justify-center bg-gradient-to-br from-green-500 to-green-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all hover:scale-105">
            <i class="fas fa-map text-lg"></i>
        </button>
        <button onclick="openFiltersModal()" class="w-14 h-14 flex items-center justify-center bg-white text-gray-700 rounded-full shadow-lg hover:shadow-xl transition-all hover:scale-105">
            <i class="fas fa-sliders-h text-lg"></i>
        </button>
    </div>

    <!-- Desktop Layout -->
    <div class="desktop-layout flex min-h-screen">
        
        <!-- Sidebar Filtres -->
        <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Filtres</h2>
                <p class="text-sm text-gray-500 mt-1">Affinez votre recherche</p>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6">
                <form method="get" class="space-y-6">
                    
                    <!-- Recherche localisation -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                            Localisation
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="term" 
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                placeholder="Ville ou pays" 
                                value="<?= htmlspecialchars($filterTerm, ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="off"
                            />
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Filtre Durée de préavis -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-clock text-green-600 mr-2"></i>
                            Disponible sous (maximum)
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                name="preavis" 
                                min="1" 
                                max="60" 
                                class="w-full pl-4 pr-16 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all"
                                placeholder="Ex: 3"
                                value="<?= htmlspecialchars($filterPreavis, ENT_QUOTES, 'UTF-8') ?>"
                            />
                            <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-sm text-gray-500 font-medium">mois</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Afficher les candidats disponibles sous X mois</p>
                    </div>

                    <!-- Switch Avec reconnaissance -->
                    <div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-certificate text-green-600"></i>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Avec reconnaissance</div>
                                    <div class="text-xs text-gray-500">Uniquement les candidats avec reconnaissance</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    name="avec_reconnaissance" 
                                    value="1" 
                                    <?= $filterAvecReconnaissance ? 'checked' : '' ?>
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-200"></div>

                    <!-- Actions -->
                    <div class="space-y-3">
                        <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold rounded-xl transition-all hover:shadow-lg">
                            <i class="fas fa-search mr-2"></i>
                            Appliquer les filtres
                        </button>

                        <?php if ($filterTerm !== '' || $filterPreavis !== '' || $filterAvecReconnaissance): ?>
                            <button 
                                type="button" 
                                onclick="window.location.href='<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>';"
                                class="w-full px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-colors"
                            >
                                <i class="fas fa-redo mr-2"></i>
                                Réinitialiser
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Stats en bas -->
            <div class="p-6 border-t border-gray-200 bg-gradient-to-br from-green-50 to-emerald-50">
                <div class="stat-card rounded-xl p-4">
                    <div class="text-3xl font-bold gradient-text mb-1"><?= $countResults ?></div>
                    <div class="text-sm text-gray-600">Profil<?= $countResults > 1 ? 's' : '' ?> disponible<?= $countResults > 1 ? 's' : '' ?></div>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="flex-1 flex flex-col">
            
            <!-- Carte -->
            <div id="map-section" class="bg-white border-b border-gray-200 transition-all duration-300">
                <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">
                        <i class="fas fa-map-marked-alt text-green-600 mr-2"></i>
                        Carte des candidats
                    </h3>
                    <button 
                        onclick="toggleMap()" 
                        id="map-toggle-btn"
                        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 hover:text-green-600 hover:bg-green-50 rounded-lg transition-all"
                    >
                        <span id="map-toggle-text">Masquer</span>
                        <i id="map-toggle-icon" class="fas fa-chevron-up transition-transform duration-300"></i>
                    </button>
                </div>
                <div id="map-container" class="transition-all duration-300 overflow-hidden" style="max-height: 500px;">
                    <div id="map" class="w-full"></div>
                </div>
            </div>

            <!-- Header résultats -->
            <div class="bg-white border-b border-gray-200 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 mb-1">Candidats disponibles</h1>
                        <p class="text-sm text-gray-500 flex flex-wrap items-center gap-2">
                            <?= $countResults ?> profil<?= $countResults > 1 ? 's' : '' ?> trouvé<?= $countResults > 1 ? 's' : '' ?>
                            <?php 
                            $activeFilters = [];
                            if ($filterTerm) $activeFilters[] = '<span class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium"><i class="fas fa-map-marker-alt mr-1.5"></i>' . htmlspecialchars($filterTerm, ENT_QUOTES, 'UTF-8') . '</span>';
                            if ($filterPreavis !== '') $activeFilters[] = '<span class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium"><i class="fas fa-clock mr-1.5"></i>≤ ' . htmlspecialchars($filterPreavis, ENT_QUOTES, 'UTF-8') . ' mois</span>';
                            if ($filterAvecReconnaissance) $activeFilters[] = '<span class="inline-flex items-center px-3 py-1 bg-pink-100 text-pink-700 rounded-full text-xs font-medium"><i class="fas fa-certificate mr-1.5"></i>Avec reconnaissance</span>';
                            
                            if (!empty($activeFilters)):
                            ?>
                                <span class="mx-2">•</span>
                                <?= implode(' ', $activeFilters) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- Toggle vue -->
                    <div class="flex items-center gap-2 bg-gray-100 rounded-xl p-1">
                        <button 
                            id="gridViewBtn"
                            onclick="switchView('grid')"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all bg-white text-gray-900 shadow-sm"
                        >
                            <i class="fas fa-th mr-2"></i>Grille
                        </button>
                        <button 
                            id="listViewBtn"
                            onclick="switchView('list')"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all text-gray-600 hover:text-gray-900"
                        >
                            <i class="fas fa-list mr-2"></i>Liste
                        </button>
                    </div>
                </div>
            </div>

            <!-- Résultats -->
            <div class="flex-1 bg-gray-50 p-8">
                <?php if (empty($profils)): ?>
                    <div class="max-w-2xl mx-auto text-center py-20">
                        <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-users text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Aucun candidat trouvé</h3>
                        <p class="text-gray-600 mb-6">Essayez d'ajuster vos critères de recherche pour obtenir plus de résultats</p>
                        <?php if ($filterTerm !== '' || $filterPreavis !== '' || $filterAvecReconnaissance): ?>
                            <button 
                                onclick="window.location.href='<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>';"
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold rounded-xl transition-all"
                            >
                                <i class="fas fa-redo mr-2"></i>
                                Réinitialiser les filtres
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    
                    <!-- Vue Grille -->
                    <div id="gridView" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
                        <?php foreach ($profils as $p):
                            $isClickable = !$isTestMode;
                            $consulted = !empty($p['consulted']);
                            $dateInscription = formatInscriptionDate($p['inscription'] ?? '');
                            $availability = calculateAvailability($p['inscription'] ?? '', $p['delai_preavis'] ?? '');
                            
                            $cardContent = '
                            <div class="profile-card bg-white rounded-2xl overflow-hidden h-full relative group ' . ($consulted ? 'opacity-60' : '') . '">
                                
                                ' . ($consulted ? '
                                <div class="absolute top-4 right-4 z-10">
                                    <div class="flex items-center gap-2 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                        <i class="fas fa-check"></i>
                                        Consulté
                                    </div>
                                </div>
                                ' : '') . '

                                <div class="aspect-[4/3] overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 relative">' .
                                    (!empty($p['photo_blurred'])
                                        ? '<img src="uploads/' . htmlspecialchars($p['photo_blurred'], ENT_QUOTES, 'UTF-8') . '" alt="Photo" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />'
                                        : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-gray-300 text-5xl"></i></div>') .
                                '</div>

                                <div class="p-6">
                                    <div class="mb-4">
                                        <div class="text-xs font-mono text-gray-400 mb-2">' . htmlspecialchars($p['numero_reference'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2 leading-tight">' . htmlspecialchars($p['fonction'] ?? '', ENT_QUOTES, 'UTF-8') . '</h3>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <i class="fas fa-map-marker-alt text-green-600 w-4"></i>
                                            <span class="font-medium">' . htmlspecialchars($p['ville'], ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($p['pays'], ENT_QUOTES, 'UTF-8') . '</span>
                                        </div>
                                        
                                        ' . ($dateInscription ? '
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <i class="fas fa-calendar text-green-600 w-4"></i>
                                            <span>' . $dateInscription . '</span>
                                        </div>
                                        ' : '') . '
                                        
                                        ' . ($availability['text'] ? '
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-green-600 w-4 text-sm"></i>
                                            ' . ($availability['available'] ? 
                                                '<span class="flex-1 px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-sm font-semibold text-center">' . $availability['text'] . '</span>' 
                                                : '<span class="text-sm text-gray-600">' . $availability['text'] . '</span>') . '
                                        </div>
                                        ' : '') . '
                                    </div>

                                    ' . ($isClickable ? '
                                    <div class="mt-6 pt-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between text-sm font-semibold text-green-600 group-hover:text-green-700">
                                            <span>Voir le profil</span>
                                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                                        </div>
                                    </div>
                                    ' : '') . '
                                </div>
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
                    <div id="listView" class="hidden grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <?php foreach ($profils as $p):
                            $isClickable = !$isTestMode;
                            $consulted = !empty($p['consulted']);
                            $availability = calculateAvailability($p['inscription'] ?? '', $p['delai_preavis'] ?? '');
                            
                            $listContent = '
                            <div class="profile-card bg-white rounded-2xl p-6 ' . ($consulted ? 'opacity-60' : '') . '">
                                <div class="flex items-center gap-6">
                                    <div class="flex-shrink-0">
                                        <div class="w-20 h-20 rounded-xl overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">' .
                                            (!empty($p['photo_blurred'])
                                                ? '<img src="uploads/' . htmlspecialchars($p['photo_blurred'], ENT_QUOTES, 'UTF-8') . '" alt="Photo" class="w-full h-full object-cover" loading="lazy" />'
                                                : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-gray-300 text-2xl"></i></div>') .
                                        '</div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0 grid grid-cols-4 gap-6 items-center">
                                        <div class="col-span-2">
                                            <div class="text-xs font-mono text-gray-400 mb-1">' . htmlspecialchars($p['numero_reference'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                                            <h3 class="text-base font-bold text-gray-900 truncate mb-1">' . htmlspecialchars($p['fonction'] ?? '', ENT_QUOTES, 'UTF-8') . '</h3>
                                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                                <i class="fas fa-map-marker-alt text-green-600 text-xs"></i>
                                                <span>' . htmlspecialchars($p['ville'], ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($p['pays'], ENT_QUOTES, 'UTF-8') . '</span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-3">
                                            ' . ($availability['available'] ? 
                                                '<span class="px-4 py-2 bg-green-100 text-green-700 rounded-lg text-sm font-semibold">' . $availability['shortText'] . '</span>' 
                                                : '<span class="text-sm text-gray-600"><i class="fas fa-clock text-green-600 mr-2"></i>' . $availability['shortText'] . '</span>') . '
                                        </div>

                                        <div class="flex items-center justify-end gap-3">
                                            ' . ($consulted ? '
                                            <div class="flex items-center gap-2 px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg text-xs font-semibold">
                                                <i class="fas fa-check"></i>
                                                Consulté
                                            </div>
                                            ' : '') . '
                                            ' . ($isClickable ? '
                                            <div class="flex items-center gap-2 text-sm font-semibold text-green-600">
                                                <span>Voir</span>
                                                <i class="fas fa-arrow-right"></i>
                                            </div>
                                            ' : '') . '
                                        </div>
                                    </div>
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
                        <div class="mt-12 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-600">
                                Page <span class="font-semibold text-gray-900"><?= $page ?></span> sur <span class="font-semibold text-gray-900"><?= $totalPages ?></span>
                                <span class="mx-2">•</span>
                                <span class="font-semibold text-gray-900"><?= $countResults ?></span> résultat<?= $countResults > 1 ? 's' : '' ?> au total
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg border border-gray-200 transition-colors">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg border border-gray-200 transition-colors">
                                        <i class="fas fa-chevron-left mr-2"></i>Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="px-4 py-2 <?= $i === $page ? 'bg-gradient-to-r from-green-500 to-green-600 text-white' : 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-200' ?> font-medium rounded-lg transition-all">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-medium rounded-lg transition-all">
                                        Suivant<i class="fas fa-chevron-right ml-2"></i>
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" class="px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg border border-gray-200 transition-colors">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mobile: Vue simple -->
    <div class="lg:hidden px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Candidats</h1>
            <p class="text-sm text-gray-600"><?= $countResults ?> profil<?= $countResults > 1 ? 's' : '' ?> disponible<?= $countResults > 1 ? 's' : '' ?></p>
        </div>

        <?php if (!empty($profils)): ?>
            <div class="space-y-4 mb-20">
                <?php foreach ($profils as $p):
                    $isClickable = !$isTestMode;
                    $consulted = !empty($p['consulted']);
                    $availability = calculateAvailability($p['inscription'] ?? '', $p['delai_preavis'] ?? '');
                    
                    $mobileCard = '
                    <div class="profile-card bg-white rounded-2xl overflow-hidden ' . ($consulted ? 'opacity-60' : '') . '">
                        <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200">' .
                            (!empty($p['photo_blurred'])
                                ? '<img src="uploads/' . htmlspecialchars($p['photo_blurred'], ENT_QUOTES, 'UTF-8') . '" alt="Photo" class="w-full h-full object-cover" loading="lazy" />'
                                : '<div class="w-full h-full flex items-center justify-center"><i class="fas fa-user text-gray-300 text-4xl"></i></div>') .
                        '</div>
                        <div class="p-5">
                            <div class="text-xs font-mono text-gray-400 mb-1">' . htmlspecialchars($p['numero_reference'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                            <h3 class="text-base font-bold text-gray-900 mb-3 line-clamp-2">' . htmlspecialchars($p['fonction'] ?? '', ENT_QUOTES, 'UTF-8') . '</h3>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <i class="fas fa-map-marker-alt text-green-600"></i>
                                    <span>' . htmlspecialchars($p['ville'], ENT_QUOTES, 'UTF-8') . '</span>
                                </div>
                                ' . ($availability['available'] ? 
                                    '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">' . $availability['shortText'] . '</span>' 
                                    : '<span class="text-xs text-gray-600">' . $availability['shortText'] . '</span>') . '
                            </div>
                        </div>
                    </div>';

                    if ($isClickable): ?>
                        <a href="public_profil.php?ref=<?= urlencode($p['numero_reference']) ?>">
                            <?= $mobileCard ?>
                        </a>
                    <?php else: ?>
                        <?= $mobileCard ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Mobile -->
            <?php if ($totalPages > 1): ?>
                <div class="flex flex-col items-center gap-4 mb-6">
                    <div class="text-sm text-gray-600 text-center">
                        Page <?= $page ?> sur <?= $totalPages ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-4 py-2 bg-white text-gray-700 font-medium rounded-lg border border-gray-200">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <span class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-lg">
                            <?= $page ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-4 py-2 bg-white text-gray-700 font-medium rounded-lg border border-gray-200">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Filtres (Mobile) -->
    <div id="filtersModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/40" onclick="closeFiltersModal()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl slide-up max-h-[85vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                <h2 class="text-xl font-bold text-gray-900">Filtres</h2>
                <button onclick="closeFiltersModal()" class="w-10 h-10 flex items-center justify-center bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form method="get" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                            Localisation
                        </label>
                        <input 
                            type="text" 
                            name="term" 
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Ville ou pays" 
                            value="<?= htmlspecialchars($filterTerm, ENT_QUOTES, 'UTF-8') ?>"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">
                            <i class="fas fa-clock text-green-600 mr-2"></i>
                            Disponible sous (maximum)
                        </label>
                        <div class="relative">
                            <input 
                                type="number" 
                                name="preavis" 
                                min="1" 
                                max="60" 
                                class="w-full pl-4 pr-16 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500"
                                placeholder="Ex: 3"
                                value="<?= htmlspecialchars($filterPreavis, ENT_QUOTES, 'UTF-8') ?>"
                            />
                            <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-sm text-gray-500 font-medium">mois</span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-certificate text-green-600"></i>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Avec reconnaissance</div>
                                    <div class="text-xs text-gray-500">Uniquement avec reconnaissance</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    name="avec_reconnaissance" 
                                    value="1" 
                                    <?= $filterAvecReconnaissance ? 'checked' : '' ?>
                                    class="sr-only peer"
                                />
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 pt-4">
                        <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-xl">
                            <i class="fas fa-search mr-2"></i>
                            Appliquer les filtres
                        </button>

                        <?php if ($filterTerm !== '' || $filterPreavis !== '' || $filterAvecReconnaissance): ?>
                            <button 
                                type="button" 
                                onclick="window.location.href='<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8') ?>';"
                                class="w-full px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl"
                            >
                                <i class="fas fa-redo mr-2"></i>
                                Réinitialiser
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Carte (Mobile) -->
    <div id="mapModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 bg-white flex flex-col">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-white">
                <h2 class="text-lg font-bold text-gray-900">Carte des candidats</h2>
                <button onclick="closeMapModal()" class="w-10 h-10 flex items-center justify-center bg-red-100 hover:bg-red-200 text-red-600 rounded-full transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1">
                <div id="mapMobile"></div>
            </div>
            <div class="p-4 bg-white border-t border-gray-200">
                <button onclick="closeMapModal()" class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-semibold rounded-xl">
                    <i class="fas fa-times-circle mr-2"></i>
                    Fermer la carte
                </button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        let mobileMap = null;
        let desktopMapHidden = false;
        
        function toggleMap() {
            const mapContainer = document.getElementById('map-container');
            const toggleText = document.getElementById('map-toggle-text');
            const toggleIcon = document.getElementById('map-toggle-icon');
            
            if (desktopMapHidden) {
                mapContainer.style.maxHeight = '500px';
                toggleText.textContent = 'Masquer';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
                desktopMapHidden = false;
            } else {
                mapContainer.style.maxHeight = '0px';
                toggleText.textContent = 'Afficher';
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
                desktopMapHidden = true;
            }
        }
        
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

        // Échappement sécurisé des données pour JavaScript
        const locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const username = '<?= htmlspecialchars(GEONAMES_USERNAME, ENT_QUOTES, 'UTF-8') ?>';

        // Mapping des codes pays (ISO 3166-1 alpha-2)
        const countryCodeMap = {
            'France': 'FR',
            'Allemagne': 'DE',
            'Espagne': 'ES',
            'Italie': 'IT',
            'Royaume-Uni': 'GB',
            'Belgique': 'BE',
            'Suisse': 'CH',
            'Portugal': 'PT',
            'Pays-Bas': 'NL',
            'Luxembourg': 'LU'
            // Ajouter d'autres mappings au besoin
        };

        if (window.innerWidth > 1024) {
            const map = L.map('map', { zoomControl: false }).setView([48.8566, 2.3522], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 18
            }).addTo(map);

            L.control.zoom({ position: 'bottomright' }).addTo(map);

            const markers = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">' + count + '</div>',
                        className: '',
                        iconSize: [40, 40]
                    });
                }
            });

            initMarkers(map, markers);
        }

        function initMobileMap() {
            mobileMap = L.map('mapMobile', { zoomControl: false }).setView([48.8566, 2.3522], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
                maxZoom: 18
            }).addTo(mobileMap);

            L.control.zoom({ position: 'bottomright' }).addTo(mobileMap);

            const markers = L.markerClusterGroup({
                maxClusterRadius: 50,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-center; color: white; font-weight: 700; font-size: 14px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">' + count + '</div>',
                        className: '',
                        iconSize: [40, 40]
                    });
                }
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
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        })
                    });
                    
                    const villeEscaped = document.createElement('div');
                    villeEscaped.textContent = loc.ville;
                    const paysEscaped = document.createElement('div');
                    paysEscaped.textContent = loc.pays;
                    
                    marker.bindPopup(`
                        <div style="padding: 12px; text-align: center; min-width: 150px;">
                            <div style="font-weight: 700; color: #111827; font-size: 15px; margin-bottom: 6px;">${villeEscaped.innerHTML}</div>
                            <div style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">${paysEscaped.innerHTML}</div>
                            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px;">
                                <svg style="width: 14px; height: 14px; fill: #059669;" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                </svg>
                                <span style="font-weight: 700; color: #059669; font-size: 13px;">${loc.count} candidat${loc.count > 1 ? 's' : ''}</span>
                            </div>
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
            // Utiliser le mapping pour obtenir le code pays correct
            const countryCode = countryCodeMap[pays] || pays.slice(0, 2).toUpperCase();
            const query = encodeURIComponent(ville);
            const url = `https://secure.geonames.org/searchJSON?q=${query}&country=${countryCode}&featureClass=P&maxRows=1&username=${username}`;
            
            try {
                const resp = await fetch(url);
                const data = await resp.json();
                if (data.geonames && data.geonames.length > 0) {
                    return [parseFloat(data.geonames[0].lat), parseFloat(data.geonames[0].lng)];
                }
            } catch (e) {
                console.error('Erreur geocoding:', e);
            }
            return null;
        }
    </script>
</body>

</html>

<?php
$pageContent = ob_get_clean();
$customJS = '';

try {
    include './includes/layouts/layout_home.php';
} catch (Exception $e) {
    echo $pageContent;
}
?>