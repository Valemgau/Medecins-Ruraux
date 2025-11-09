<?php
require_once '../includes/config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$limitPerPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limitPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterRole = isset($_GET['filterRole']) && in_array($_GET['filterRole'], ['candidat', 'recruteur']) ? $_GET['filterRole'] : '';

$whereParts = [];
$params = [];

// Exclure les admins
$whereParts[] = "u.role != 'admin' AND u2.role != 'admin'";

if ($search !== '') {
    $whereParts[] = "(
        u.email LIKE :search OR 
        r.nom LIKE :search OR r.prenom LIKE :search OR
        c.nom LIKE :search OR c.prenom LIKE :search OR
        r2.nom LIKE :search OR r2.prenom LIKE :search OR
        c2.nom LIKE :search OR c2.prenom LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if ($filterRole === 'candidat') {
    $whereParts[] = "c2.id IS NOT NULL";
} elseif ($filterRole === 'recruteur') {
    $whereParts[] = "r2.id IS NOT NULL";
}

$where = '';
if (!empty($whereParts)) {
    $where = "WHERE " . implode(' AND ', $whereParts);
}

// Compter le total
$stmtTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM consultations cons
    INNER JOIN users u ON cons.user_id = u.id
    LEFT JOIN recruteurs r ON u.id = r.id AND u.role = 'recruteur'
    LEFT JOIN candidats c ON u.id = c.id AND u.role = 'candidat'
    INNER JOIN users u2 ON cons.candidat_id = u2.id
    LEFT JOIN recruteurs r2 ON u2.id = r2.id AND u2.role = 'recruteur'
    LEFT JOIN candidats c2 ON u2.id = c2.id AND u2.role = 'candidat'
    $where
");
$stmtTotal->execute($params);
$totalConsultations = $stmtTotal->fetchColumn();
$totalPages = ceil($totalConsultations / $limitPerPage);

// Récupérer les consultations
$query = "
    SELECT 
        cons.id,
        cons.user_id,
        cons.candidat_id,
        cons.consulted_at,
        u.email as user_email,
        u.role as user_role,
        CASE 
            WHEN u.role = 'recruteur' THEN r.nom
            WHEN u.role = 'candidat' THEN c.nom
            ELSE NULL
        END as viewer_nom,
        CASE 
            WHEN u.role = 'recruteur' THEN r.prenom
            WHEN u.role = 'candidat' THEN c.prenom
            ELSE NULL
        END as viewer_prenom,
        u2.email as consulted_email,
        u2.role as consulted_role,
        CASE 
            WHEN u2.role = 'recruteur' THEN r2.nom
            WHEN u2.role = 'candidat' THEN c2.nom
            ELSE NULL
        END as consulted_nom,
        CASE 
            WHEN u2.role = 'recruteur' THEN r2.prenom
            WHEN u2.role = 'candidat' THEN c2.prenom
            ELSE NULL
        END as consulted_prenom
    FROM consultations cons
    INNER JOIN users u ON cons.user_id = u.id
    LEFT JOIN recruteurs r ON u.id = r.id AND u.role = 'recruteur'
    LEFT JOIN candidats c ON u.id = c.id AND u.role = 'candidat'
    INNER JOIN users u2 ON cons.candidat_id = u2.id
    LEFT JOIN recruteurs r2 ON u2.id = r2.id AND u2.role = 'recruteur'
    LEFT JOIN candidats c2 ON u2.id = c2.id AND u2.role = 'candidat'
    $where
    ORDER BY cons.consulted_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limitPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$consultations = $stmt->fetchAll();

// Statistiques
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT candidat_id) as unique_profiles,
        COUNT(DISTINCT DATE(consulted_at)) as days_active
    FROM consultations
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch();

$title = "Historique des consultations";
ob_start();
?>

<div class="space-y-6">
    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total consultations</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['total']) ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-eye text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Utilisateurs actifs</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['unique_users']) ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-users text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Profils consultés</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['unique_profiles']) ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-id-card text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Jours d'activité</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($stats['days_active']) ?></p>
                </div>
                <div class="p-3 bg-orange-100 rounded-lg">
                    <i class="fas fa-calendar-check text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Nom, prénom, email..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Profil consulté</label>
                <select name="filterRole" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">Tous les profils</option>
                    <option value="candidat" <?= $filterRole === 'candidat' ? 'selected' : '' ?>>Candidats</option>
                    <option value="recruteur" <?= $filterRole === 'recruteur' ? 'selected' : '' ?>>Recruteurs</option>
                </select>
            </div>

            <div class="flex items-end space-x-3">
                <a href="?" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-center">
                    <i class="fas fa-redo mr-2"></i>Réinitialiser
                </a>
                <button type="submit" class="flex-1 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consultation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($consultations) === 0): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-3"></i>
                                <p class="text-gray-500">Aucune consultation trouvée</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($consultations as $consultation): 
                            // Celui qui consulte
                            $viewer_prenom = $consultation['viewer_prenom'] ?: '-';
                            $viewer_nom = $consultation['viewer_nom'] ?: '-';
                            $viewer_role = $consultation['user_role'];
                            $viewer_id = $consultation['user_id'];
                            
                            // Celui qui est consulté
                            $consulted_prenom = $consultation['consulted_prenom'] ?: '-';
                            $consulted_nom = $consultation['consulted_nom'] ?: '-';
                            $consulted_role = $consultation['consulted_role'];
                            $consulted_id = $consultation['candidat_id'];
                            
                            $viewer_url = "/admin/user_detail.php?id={$viewer_id}";
                            $consulted_url = "/admin/user_detail.php?id={$consulted_id}";
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?= $consultation['id'] ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center text-sm">
                                    <!-- Utilisateur qui consulte -->
                                    <a href="<?= $viewer_url ?>" class="flex items-center group">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                                <?= strtoupper(substr($viewer_prenom, 0, 1)) ?><?= strtoupper(substr($viewer_nom, 0, 1)) ?>
                                            </div>
                                        </div>
                                        <span class="ml-2 font-medium text-gray-900 group-hover:text-green-600 transition-colors">
                                            <?= htmlspecialchars($viewer_prenom) ?> <?= htmlspecialchars($viewer_nom) ?>
                                        </span>
                                    </a>
                                    
                                    <span class="mx-3 text-gray-400">
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                    
                                    <span class="text-gray-600 mr-3">a consulté le profil de</span>
                                    
                                    <!-- Profil consulté -->
                                    <a href="<?= $consulted_url ?>" class="flex items-center group">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white text-xs font-semibold">
                                                <?= strtoupper(substr($consulted_prenom, 0, 1)) ?><?= strtoupper(substr($consulted_nom, 0, 1)) ?>
                                            </div>
                                        </div>
                                        <span class="ml-2 font-medium text-gray-900 group-hover:text-green-600 transition-colors">
                                            <?= htmlspecialchars($consulted_prenom) ?> <?= htmlspecialchars($consulted_nom) ?>
                                        </span>
                                    </a>
                                </div>
                                
                                <!-- Badges de rôle -->
                                <div class="flex items-center space-x-2 mt-2">
                                    <?php
                                    $viewerRoleColors = [
                                        'admin' => 'bg-red-100 text-red-800',
                                        'candidat' => 'bg-blue-100 text-blue-800',
                                        'recruteur' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $consultedRoleColors = [
                                        'admin' => 'bg-red-100 text-red-800',
                                        'candidat' => 'bg-blue-100 text-blue-800',
                                        'recruteur' => 'bg-purple-100 text-purple-800'
                                    ];
                                    ?>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $viewerRoleColors[$viewer_role] ?? 'bg-gray-100 text-gray-800' ?>">
                                        <?= htmlspecialchars(ucfirst($viewer_role)) ?>
                                    </span>
                                    <i class="fas fa-arrow-right text-xs text-gray-400"></i>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $consultedRoleColors[$consulted_role] ?? 'bg-gray-100 text-gray-800' ?>">
                                        <?= htmlspecialchars(ucfirst($consulted_role)) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900">
                                        <?= date('d/m/Y', strtotime($consultation['consulted_at'])) ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?= date('H:i', strtotime($consultation['consulted_at'])) ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between bg-white px-6 py-4 rounded-lg shadow-sm border border-gray-200">
            <div class="text-sm text-gray-700">
                Page <span class="font-medium"><?= $page ?></span> sur <span class="font-medium"><?= $totalPages ?></span>
                <span class="ml-2 text-gray-500">(<?= number_format($totalConsultations) ?> consultations)</span>
            </div>
            
            <div class="flex space-x-2">
                <?php
                $queryStringBase = http_build_query([
                    'search' => $search, 
                    'filterRole' => $filterRole
                ]);
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="?<?= $queryStringBase ?>&page=<?= $page - 1 ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-left mr-1"></i> Précédent
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                ?>

                <?php if ($start > 1): ?>
                    <a href="?<?= $queryStringBase ?>&page=1" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">1</a>
                    <?php if ($start > 2): ?>
                        <span class="px-3 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="?<?= $queryStringBase ?>&page=<?= $p ?>" 
                       class="px-4 py-2 rounded-lg transition-colors <?= $p === $page ? 'bg-green-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <span class="px-3 py-2 text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?<?= $queryStringBase ?>&page=<?= $totalPages ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= $queryStringBase ?>&page=<?= $page + 1 ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Suivant <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/layouts/layout_admin.php';
?>