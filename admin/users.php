<?php
require_once '../includes/config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$limitPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limitPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterRole = isset($_GET['filterRole']) && in_array($_GET['filterRole'], ['admin', 'candidat', 'recruteur']) ? $_GET['filterRole'] : '';
$filterStatus = isset($_GET['filterStatus']) ? $_GET['filterStatus'] : '';

// Gestion POST - Toggle is_active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $userId = (int) $_POST['user_id'];
    $currentStatus = (int) $_POST['current_status'];
    $newStatus = $currentStatus ? 0 : 1;

    if ($userId !== $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['success'] = $newStatus ? "Utilisateur activé avec succès." : "Utilisateur désactivé avec succès.";
    }

    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query([
        'search' => $search,
        'filterRole' => $filterRole,
        'filterStatus' => $filterStatus,
        'page' => $page
    ]));
    exit;
}

$whereParts = [];
$params = [];

if ($search !== '') {
    $whereParts[] = "(
        u.email LIKE :search OR 
        r.nom LIKE :search OR r.prenom LIKE :search OR
        c.nom LIKE :search OR c.prenom LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if ($filterRole !== '') {
    if ($filterRole === 'admin') {
        $whereParts[] = "u.role = 'admin'";
    } elseif ($filterRole === 'recruteur') {
        $whereParts[] = "r.id IS NOT NULL";
    } elseif ($filterRole === 'candidat') {
        $whereParts[] = "c.id IS NOT NULL";
    }
}

if ($filterStatus === 'active') {
    $whereParts[] = "u.is_active = 1";
} elseif ($filterStatus === 'inactive') {
    $whereParts[] = "u.is_active = 0";
}

$where = '';
if (!empty($whereParts)) {
    $where = "WHERE " . implode(' AND ', $whereParts);
}

$stmtTotal = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN recruteurs r ON u.id = r.id
    LEFT JOIN candidats c ON u.id = c.id
    $where
");
$stmtTotal->execute($params);
$totalUsers = $stmtTotal->fetchColumn();
$totalPages = ceil($totalUsers / $limitPerPage);

$query = "
    SELECT 
        u.id, 
        u.email, 
        u.role AS user_role, 
        u.created_at,
        u.is_active,
        u.email_verified,
        r.nom AS reco_nom, 
        r.prenom AS reco_prenom,
        c.nom AS cand_nom, 
        c.prenom AS cand_prenom
    FROM users u
    LEFT JOIN recruteurs r ON u.id = r.id
    LEFT JOIN candidats c ON u.id = c.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limitPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Statistiques
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'candidat' THEN 1 ELSE 0 END) as candidats,
        SUM(CASE WHEN role = 'recruteur' THEN 1 ELSE 0 END) as recruteurs,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as actifs,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verifies
    FROM users
";
$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch();

$title = "Gestion des utilisateurs";
ob_start();
?>

<div class="space-y-6">
    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total utilisateurs</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= $stats['total'] ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Candidats</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= $stats['candidats'] ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-user-md text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Recruteurs</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= $stats['recruteurs'] ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-briefcase text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Actifs</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1"><?= $stats['actifs'] ?></p>
                </div>
                <div class="p-3 bg-emerald-100 rounded-lg">
                    <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
                <input type="text" name="search" placeholder="Nom, prénom, email..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                <select name="filterRole"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">Tous les rôles</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="candidat" <?= $filterRole === 'candidat' ? 'selected' : '' ?>>Candidat</option>
                    <option value="recruteur" <?= $filterRole === 'recruteur' ? 'selected' : '' ?>>Recruteur</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                <select name="filterStatus"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <option value="">Tous</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Actifs</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                </select>
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3">
                <a href="?"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-redo mr-2"></i>Réinitialiser
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Utilisateur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Statuts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Inscription</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($users) === 0): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-3"></i>
                                <p class="text-gray-500">Aucun utilisateur trouvé</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $rowNumber = $offset + 1;
                        foreach ($users as $user):
                            $nom = $prenom = '-';
                            if ($user['user_role'] === 'recruteur') {
                                $nom = $user['reco_nom'] ?: '-';
                                $prenom = $user['reco_prenom'] ?: '-';
                            } elseif ($user['user_role'] === 'candidat') {
                                $nom = $user['cand_nom'] ?: '-';
                                $prenom = $user['cand_prenom'] ?: '-';
                            } elseif ($user['user_role'] === 'admin') {
                                $nom = 'Admin';
                                $prenom = '';
                            }

                            $detailUrl = $user['user_role'] === 'candidat' ? "/admin/user_detail.php?id={$user['id']}" :
                                ($user['user_role'] === 'recruteur' ? "/admin/user_detail.php?id={$user['id']}" : '#');
                            ?>

                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= $rowNumber++ ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="<?= $detailUrl ?>" class="flex items-center group">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div
                                                class="h-10 w-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($prenom, 0, 1)) ?>        <?= strtoupper(substr($nom, 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div
                                                class="text-sm font-medium text-gray-900 group-hover:text-green-600 transition-colors">
                                                <?= htmlspecialchars($prenom) ?>         <?= htmlspecialchars($nom) ?>
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $roleColors = [
                                        'admin' => 'bg-red-100 text-red-800',
                                        'candidat' => 'bg-blue-100 text-blue-800',
                                        'recruteur' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $roleColor = $roleColors[$user['user_role']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span
                                        class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $roleColor ?>">
                                        <?= htmlspecialchars(ucfirst($user['user_role'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <?php if ($user['is_active']): ?>
                                            <span class="inline-flex items-center text-xs text-green-700">
                                                <i class="fas fa-check-circle mr-1"></i>Actif
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-xs text-red-700">
                                                <i class="fas fa-ban mr-1"></i>Inactif
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($user['email_verified']): ?>
                                            <span class="inline-flex items-center text-xs text-blue-700">
                                                <i class="fas fa-envelope-circle-check mr-1"></i>Vérifié
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-xs text-orange-700">
                                                <i class="fas fa-envelope mr-1"></i>Non vérifié
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div><?= date("d/m/Y", strtotime($user['created_at'])) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <a href="<?= $detailUrl ?>"
                                            class="text-green-600 hover:text-green-900 transition-colors" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <form method="post" class="inline"
                                                onsubmit="return confirm('Êtes-vous sûr de vouloir <?= $user['is_active'] ? 'désactiver' : 'activer' ?> cet utilisateur ?');">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $user['is_active'] ?>">
                                                <button type="submit" name="toggle_active"
                                                    class="<?= $user['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' ?> transition-colors"
                                                    title="<?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                                    <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-300" title="Vous ne pouvez pas vous désactiver vous-même">
                                                <i class="fas fa-ban"></i>
                                            </span>
                                        <?php endif; ?>
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
                <span class="ml-2 text-gray-500">(<?= $totalUsers ?> résultats)</span>
            </div>

            <div class="flex space-x-2">
                <?php
                $queryStringBase = http_build_query([
                    'search' => $search,
                    'filterRole' => $filterRole,
                    'filterStatus' => $filterStatus
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
                    <a href="?<?= $queryStringBase ?>&page=1"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">1</a>
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
                    <a href="?<?= $queryStringBase ?>&page=<?= $totalPages ?>"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"><?= $totalPages ?></a>
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