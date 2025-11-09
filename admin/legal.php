<?php
require_once '../includes/config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Gestion POST - Mise à jour d'une politique
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_policy'])) {
    $policyType = trim($_POST['policy_type']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (!empty($policyType) && !empty($title) && !empty($content)) {
        // Vérifier si la politique existe
        $stmtCheck = $pdo->prepare("SELECT id FROM site_policies WHERE policy_type = ?");
        $stmtCheck->execute([$policyType]);
        
        if ($existing = $stmtCheck->fetch()) {
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE site_policies SET title = ?, content = ?, updated_at = NOW() WHERE policy_type = ?");
            $stmt->execute([$title, $content, $policyType]);
        } else {
            // Création
            $stmt = $pdo->prepare("INSERT INTO site_policies (policy_type, title, content, updated_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$policyType, $title, $content]);
        }
        
        $_SESSION['success'] = "Politique mise à jour avec succès.";
    } else {
        $_SESSION['error'] = "Le titre et le contenu sont obligatoires.";
    }
    
    header("Location: /admin/legal.php?tab=$policyType");
    exit;
}

// Récupération de toutes les politiques indexées par type
$stmt = $pdo->query("SELECT * FROM site_policies");
$allPolicies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$policies = [];
foreach ($allPolicies as $policy) {
    $policies[$policy['policy_type']] = $policy;
}

// Tab active
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'mentions';

$title = "Gestion des politiques légales";
ob_start();

$tabs = [
    'mentions' => [
        'label' => 'Mentions légales',
        'icon' => 'fa-gavel',
        'color' => 'green'
    ],
    'privacy_policy' => [
        'label' => 'Politique de confidentialité',
        'icon' => 'fa-shield-alt',
        'color' => 'purple'
    ],
    'cgv' => [
        'label' => 'Conditions générales de vente',
        'icon' => 'fa-file-contract',
        'color' => 'orange'
    ],
    'cookies' => [
        'label' => 'Politique de cookies',
        'icon' => 'fa-cookie-bite',
        'color' => 'pink'
    ]
];
?>

<div class="space-y-6">

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <?php foreach ($tabs as $tabKey => $tabInfo): 
                    $isActive = $activeTab === $tabKey;
                    $colorClasses = [
                        'green' => 'border-green-500 text-green-600',
                        'purple' => 'border-purple-500 text-purple-600',
                        'orange' => 'border-orange-500 text-orange-600',
                        'pink' => 'border-pink-500 text-pink-600'
                    ];
                    $activeClass = $isActive ? ($colorClasses[$tabInfo['color']] ?? 'border-green-500 text-green-600') : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
                ?>
                <a href="?tab=<?= $tabKey ?>" 
                   class="group inline-flex items-center py-4 px-6 border-b-2 font-medium text-sm transition-colors <?= $activeClass ?>">
                    <i class="fas <?= $tabInfo['icon'] ?> mr-2"></i>
                    <?= $tabInfo['label'] ?>
                    <?php if (isset($policies[$tabKey])): ?>
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?= $isActive ? 'bg-'.$tabInfo['color'].'-100 text-'.$tabInfo['color'].'-800' : 'bg-gray-100 text-gray-600' ?>">
                            ✓
                        </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <?php 
            $currentPolicy = $policies[$activeTab] ?? null;
            $tabInfo = $tabs[$activeTab];
            ?>
            
            <form method="post" class="space-y-6">
                <input type="hidden" name="policy_type" value="<?= $activeTab ?>">
                
                <!-- Titre -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-heading mr-2 text-<?= $tabInfo['color'] ?>-600"></i>
                        Titre du document *
                    </label>
                    <input type="text" 
                           name="title" 
                           required 
                           maxlength="255"
                           value="<?= htmlspecialchars($currentPolicy['title'] ?? '') ?>"
                           placeholder="Ex: <?= $tabInfo['label'] ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?= $tabInfo['color'] ?>-500 focus:border-transparent text-lg">
                </div>

                <!-- Contenu -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-align-left mr-2 text-<?= $tabInfo['color'] ?>-600"></i>
                            Contenu du document *
                        </label>
                        <?php if ($currentPolicy): ?>
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                Dernière modification : <?= date('d/m/Y à H:i', strtotime($currentPolicy['updated_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <textarea name="content" 
                              required 
                              rows="20"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-<?= $tabInfo['color'] ?>-500 focus:border-transparent font-mono text-sm"
                              placeholder="Saisissez le contenu ici..."><?= htmlspecialchars($currentPolicy['content'] ?? '') ?></textarea>
                    
                    <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800 flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                            <span>
                                <strong>HTML supporté :</strong> Vous pouvez utiliser les balises suivantes : 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;h2&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;h3&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;p&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;ul&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;li&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;strong&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;em&gt;</code>, 
                                <code class="bg-white px-1 py-0.5 rounded">&lt;a href=""&gt;</code>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Aperçu -->
                <?php if ($currentPolicy && !empty($currentPolicy['content'])): ?>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-eye mr-2 text-<?= $tabInfo['color'] ?>-600"></i>
                            Aperçu du rendu
                        </label>
                        <button type="button" 
                                onclick="togglePreview()"
                                class="text-xs text-<?= $tabInfo['color'] ?>-600 hover:text-<?= $tabInfo['color'] ?>-800">
                            <i class="fas fa-chevron-down mr-1"></i>
                            <span id="togglePreviewText">Afficher</span>
                        </button>
                    </div>
                    
                    <div id="previewContent" class="hidden p-6 border border-gray-300 rounded-lg bg-gray-50 prose max-w-none">
                        <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($currentPolicy['title']) ?></h1>
                        <?= $currentPolicy['content'] ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Boutons d'action -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        <?php if ($currentPolicy): ?>
                            <i class="fas fa-check-circle text-green-600 mr-1"></i>
                            Ce document existe et sera mis à jour
                        <?php else: ?>
                            <i class="fas fa-plus-circle text-blue-600 mr-1"></i>
                            Ce document sera créé lors de l'enregistrement
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" 
                            name="update_policy"
                            class="px-6 py-3 bg-<?= $tabInfo['color'] ?>-600 text-white rounded-lg hover:bg-<?= $tabInfo['color'] ?>-700 transition-colors flex items-center font-medium">
                        <i class="fas fa-save mr-2"></i>
                        <?= $currentPolicy ? 'Enregistrer les modifications' : 'Créer le document' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePreview() {
    const preview = document.getElementById('previewContent');
    const text = document.getElementById('togglePreviewText');
    
    if (preview.classList.contains('hidden')) {
        preview.classList.remove('hidden');
        text.textContent = 'Masquer';
    } else {
        preview.classList.add('hidden');
        text.textContent = 'Afficher';
    }
}
</script>

<?php
$pageContent = ob_get_clean();
require_once '../includes/layouts/layout_admin.php';
?>