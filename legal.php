<?php
require_once './includes/config.php';

$allowedPolicies = ['mentions', 'privacy_policy', 'cgv', 'cookies'];
$policyType = $_GET['policy'] ?? 'mentions';

// Sécurise la valeur passée
if (!in_array($policyType, $allowedPolicies)) {
    http_response_code(404);
    die('Page non trouvée.');
}

// Récupérer le contenu depuis la base
$stmt = $pdo->prepare("SELECT title, content FROM site_policies WHERE policy_type = ? LIMIT 1");
$stmt->execute([$policyType]);
$policy = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$policy) {
    http_response_code(404);
    die('Contenu introuvable.');
}

$title = htmlspecialchars($policy['title']);
$content = $policy['content'];

// Définir les noms des politiques
$policyNames = [
    'mentions' => 'Mentions légales',
    'privacy_policy' => 'Politique de confidentialité',
    'cgv' => 'Conditions d\'utilisation',
    'cookies' => 'Politique des cookies'
];

ob_start();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        
        
        * {
            
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Styles pour le contenu HTML */
        .legal-content h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .legal-content h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .legal-content h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .legal-content p {
            color: #4b5563;
            line-height: 1.75;
            margin-bottom: 1rem;
        }
        
        .legal-content ul, .legal-content ol {
            color: #4b5563;
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .legal-content li {
            margin-bottom: 0.5rem;
        }
        
        .legal-content a {
            color: #22c55e;
            text-decoration: underline;
            transition: color 0.2s;
        }
        
        .legal-content a:hover {
            color: #16a34a;
        }
        
        .legal-content strong {
            font-weight: 600;
            color: #1f2937;
        }
        
        .legal-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        
        .legal-content th,
        .legal-content td {
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            text-align: left;
        }
        
        .legal-content th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
    </style>
</head>

<body class="bg-gray-50">
    
    <div class="min-h-screen pt-5">
        
      

        <!-- Navigation des politiques -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center overflow-x-auto space-x-1 py-2 -mb-px">
                    <?php foreach ($allowedPolicies as $policy): ?>
                        <a href="?policy=<?= $policy ?>" 
                           class="whitespace-nowrap px-4 py-3 text-sm font-medium rounded-t-lg transition-all duration-200 <?= $policyType === $policy ? 'text-green-600 bg-gray-50 border-b-2 border-green-600' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' ?>">
                            <?= $policyNames[$policy] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            
            <!-- Card contenu -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
                
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-12 border-b border-gray-200">
                    <div class="flex items-start space-x-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0">
                            <i class="fas fa-file-contract text-white text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-2 tracking-tight">
                                <?= $title ?>
                            </h1>
                            <p class="text-gray-600 text-sm">
                                Dernière mise à jour : <?= date('d/m/Y') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contenu -->
                <div class="px-8 py-12">
                    <div class="legal-content prose max-w-none">
                        <?= html_entity_decode($content) ?>
                    </div>
                </div>

                <!-- Footer de la card -->
                <div class="bg-gray-50 px-8 py-6 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2 text-green-600"></i>
                            Des questions ? <a href="/contact.php" class="text-green-600 hover:text-green-700 font-medium">Contactez-nous</a>
                        </p>
                        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-print mr-2"></i>
                            Imprimer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Navigation rapide entre politiques -->
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($allowedPolicies as $policy): ?>
                    <?php if ($policy !== $policyType): ?>
                        <a href="?policy=<?= $policy ?>" 
                           class="block p-4 bg-white rounded-2xl border border-gray-200 hover:border-green-300 hover:shadow-sm transition-all duration-200 group">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center group-hover:bg-green-100 transition-colors">
                                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-green-600 transition-colors"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700 group-hover:text-green-600 transition-colors">
                                    <?= $policyNames[$policy] ?>
                                </span>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        </main>
    </div>

</body>
</html>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_home.php';
?>