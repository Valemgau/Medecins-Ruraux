<?php
require_once '../includes/config.php';

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$limitPerPage = $limitPerPage ?? 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limitPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterRole = isset($_GET['filterRole']) && in_array($_GET['filterRole'], ['admin', 'candidat', 'recruteur']) ? $_GET['filterRole'] : '';

// Gestion POST modification ou suppression (sans ajax)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_user_id'], $_POST['nom'], $_POST['prenom'], $_POST['fonction'])) {
        $id = (int)$_POST['edit_user_id'];
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $fonction = trim($_POST['fonction']);

        $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtRole->execute([$id]);
        $role = $stmtRole->fetchColumn();

        if ($role !== false && ($role === 'recruteur' || $role === 'candidat')) {
            if ($role === 'recruteur') {
                $stmtUpdate = $pdo->prepare("UPDATE recruteurs SET nom = ?, prenom = ?, fonction = ? WHERE id = ?");
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE candidats SET nom = ?, prenom = ?, fonction = ? WHERE id = ?");
            }
            $stmtUpdate->execute([$nom, $prenom, $fonction, $id]);
        }
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['search'=>$search, 'filterRole'=>$filterRole, 'page'=>$page]));
        exit;
    }

    if (isset($_POST['delete_user_id'])) {
        $deleteUserId = (int)$_POST['delete_user_id'];
        if ($deleteUserId !== $_SESSION['user_id']) {
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelete->execute([$deleteUserId]);
        }
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['search'=>$search, 'filterRole'=>$filterRole, 'page'=>$page]));
        exit;
    }
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
        r.nom AS reco_nom, r.prenom AS reco_prenom, r.fonction AS reco_fonction,
        c.nom AS cand_nom, c.prenom AS cand_prenom, c.fonction AS cand_fonction
    FROM users u
    LEFT JOIN recruteurs r ON u.id = r.id
    LEFT JOIN candidats c ON u.id = c.id
    $where
    GROUP BY u.id
    ORDER BY u.role, u.created_at DESC
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

$title = "Gestion des utilisateurs";
ob_start();
?>

<style>
.modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.modal {
    background: white;
    border-radius: 12px;
    padding: 28px 24px;
    max-width: 400px;
    width: 95%;
    box-shadow: 0 12px 28px rgba(0,0,0,0.28);
    position: relative;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.modal h2 {
    font-weight: 700;
    font-size: 1.5rem;
    margin-bottom: 20px;
    color: #111;
    text-align: center;
}
.modal label {
    display: block;
    margin-top: 16px;
    font-weight: 600;
    color: #333;
}
.modal input[type=text] {
    width: 100%;
    border-radius: 8px;
    border: 1.5px solid #ccc;
    padding: 10px 12px;
    font-size: 1rem;
    outline-offset: 2px;
    margin-top: 6px;
    transition: border-color 0.3s ease;
}
.modal input[type=text]:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 8px #2563eb;
}
.modal .btn-container {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
}
.modal button {
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    font-size: 1.05rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
    user-select: none;
}
.modal .btn-cancel {
    background: #e5e7eb;
    color: #4b5563;
}
.modal .btn-cancel:hover {
    background: #d1d5db;
}
.modal .btn-save {
    background: #2563eb;
    color: white;
}
.modal .btn-save:hover {
    background: #1d4ed8;
}
.table-btn {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    outline: none;
    user-select: none;
    transition: background-color 0.3s ease;
}
.btn-edit {
    background-color: #4f46e5;
    color: white;
}
.btn-edit:hover {
    background-color: #4338ca;
}
.btn-delete {
    background-color: #dc2626;
    color: white;
}
.btn-delete:hover {
    background-color: #b91c1c;
}
</style>

<div class="max-w-7xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Gestion des utilisateurs</h1>

    <form method="get" class="mb-6 flex space-x-2">
        <input 
            type="text" 
            name="search" 
            placeholder="Rechercher nom, prénom, email"
            value="<?= htmlspecialchars($search) ?>"
            class="border border-gray-300 rounded px-3 py-2 flex-grow focus:border-blue-500"
            autocomplete="off"
        >
        <select name="filterRole" class="border border-gray-300 rounded px-3 py-2 focus:border-blue-500">
            <option value="" <?= $filterRole === '' ? 'selected' : '' ?>>Tous les rôles</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="candidat" <?= $filterRole === 'candidat' ? 'selected' : '' ?>>Candidat</option>
            <option value="recruteur" <?= $filterRole === 'recruteur' ? 'selected' : '' ?>>Recruteur</option>
        </select>
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 font-semibold transition">Filtrer</button>
    </form>

    <table class="w-full text-sm border border-gray-300 rounded overflow-hidden shadow-md">
        <thead class="bg-gray-100 text-left">
            <tr>
                <th class="p-3 border-b border-gray-300">ID</th>
                <th class="p-3 border-b border-gray-300">Nom (Prénom)</th>
                <th class="p-3 border-b border-gray-300">Email</th>
                <th class="p-3 border-b border-gray-300">Rôle</th>
                <th class="p-3 border-b border-gray-300">Fonction</th>
                <th class="p-3 border-b border-gray-300">Inscription</th>
                <th class="p-3 border-b border-gray-300">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($users) === 0): ?>
            <tr><td colspan="7" class="p-4 text-center text-gray-500">Aucun utilisateur trouvé.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $user): 
                $nom = '-'; $prenom = '-'; $fonction = '-';
                if ($user['user_role'] === 'admin') {
                    $nom = $prenom = $fonction = '—';
                } elseif ($user['user_role'] === 'recruteur') {
                    $nom = $user['reco_nom'] ?: '-';
                    $prenom = $user['reco_prenom'] ?: '-';
                    $fonction = $user['reco_fonction'] ?: '-';
                } elseif ($user['user_role'] === 'candidat') {
                    $nom = $user['cand_nom'] ?: '-';
                    $prenom = $user['cand_prenom'] ?: '-';
                    $fonction = $user['cand_fonction'] ?: '-';
                }
            ?>
            <tr>
                <td class="p-3 border-b border-gray-300"><?= htmlspecialchars($user['id']) ?></td>
                <td class="p-3 border-b border-gray-300"><?= htmlspecialchars("$nom ($prenom)") ?></td>
                <td class="p-3 border-b border-gray-300"><?= htmlspecialchars($user['email']) ?></td>
                <td class="p-3 border-b border-gray-300"><?= htmlspecialchars(ucfirst($user['user_role'])) ?></td>
                <td class="p-3 border-b border-gray-300"><?= htmlspecialchars($fonction) ?></td>
                <td class="p-3 border-b border-gray-300"><?= date("d/m/Y", strtotime($user['created_at'])) ?></td>
                <td class="p-3 border-b border-gray-300 flex space-x-2">
                    <?php if ($user['user_role'] !== 'admin'): ?>
                        <button 
                            class="table-btn btn-edit"
                            data-userid="<?= $user['id'] ?>"
                            data-role="<?= htmlspecialchars($user['user_role']) ?>"
                            data-nom="<?= htmlspecialchars($nom) ?>"
                            data-prenom="<?= htmlspecialchars($prenom) ?>"
                            data-fonction="<?= htmlspecialchars($fonction) ?>"
                        >Modifier</button>
                    <?php else: ?>
                        <span class="text-gray-400 italic select-none">-</span>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Confirmez la suppression ?');" class="inline">
                        <input type="hidden" name="delete_user_id" value="<?= htmlspecialchars($user['id']) ?>">
                        <button type="submit" name="delete_user" class="table-btn btn-delete">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-6 flex justify-center space-x-2 select-none">
        <?php
        $queryStringBase = http_build_query(['search' => $search, 'filterRole' => $filterRole]);
        ?>
        <?php if ($page > 1): ?>
            <a href="?<?= $queryStringBase ?>&page=<?= $page - 1 ?>" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition">Précédent</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?<?= $queryStringBase ?>&page=<?= $p ?>" class="px-4 py-2 rounded <?= $p === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> transition">
                <?= $p ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= $queryStringBase ?>&page=<?= $page + 1 ?>" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition">Suivant</a>
        <?php endif; ?>
    </div>
</div>

<div class="modal-bg" id="editModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" tabindex="-1">
    <div class="modal">
        <button class="modal-close-btn" id="closeModal" aria-label="Fermer">&times;</button>
        <h2 id="modalTitle">Modifier utilisateur</h2>
        <form method="post" id="editForm">
            <input type="hidden" name="edit_user_id" id="editUserId" required>
            <label for="nomInput">Nom</label>
            <input type="text" id="nomInput" name="nom" required>
            <label for="prenomInput">Prénom</label>
            <input type="text" id="prenomInput" name="prenom" required>
            <label for="fonctionInput">Fonction</label>
            <input type="text" id="fonctionInput" name="fonction" required>
            <div class="btn-container">
                <button type="button" class="btn-cancel" id="cancelBtn">Annuler</button>
                <button type="submit" class="btn-save">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modalBg = document.getElementById('editModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const editForm = document.getElementById('editForm');
    const editUserId = document.getElementById('editUserId');
    const nomInput = document.getElementById('nomInput');
    const prenomInput = document.getElementById('prenomInput');
    const fonctionInput = document.getElementById('fonctionInput');

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            editUserId.value = btn.getAttribute('data-userid');
            nomInput.value = btn.getAttribute('data-nom');
            prenomInput.value = btn.getAttribute('data-prenom');
            fonctionInput.value = btn.getAttribute('data-fonction');
            modalBg.style.display = 'flex';
            nomInput.focus();
        });
    });

    function closeModal() {
        modalBg.style.display = 'none';
    }

    closeModalBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modalBg.addEventListener('click', (e) => {
        if (e.target === modalBg) {
            closeModal();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalBg.style.display === 'flex') {
            closeModal();
        }
    });
</script>

<?php
$pageContent = ob_get_clean();
include '../includes/layouts/layout_admin.php';
?>
