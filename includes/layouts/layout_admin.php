<?php
// admin_layout.php
require_once '../includes/config.php';

// Vérifier que l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Administration') ?> - Médecins Ruraux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/includes/styles/index.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50">
        <!-- Logo -->
        <div class="flex items-center justify-center h-20 border-b border-gray-200">
            <img src="/assets/img/logo.jpg" alt="Médecins Ruraux" class="h-12">
        </div>

        <!-- Navigation -->
       <nav class="mt-8 px-4">
    <div class="space-y-2">
        <?php
        $menuItems = [
            [
                'page' => 'users',
                'url' => '/admin/users.php',
                'icon' => 'fa-users',
                'label' => 'Utilisateurs'
            ],
            [
                'page' => 'consultations',
                'url' => '/admin/consultations.php',
                'icon' => 'fa-eye',
                'label' => 'Consultations'
            ],
            [
                'page' => 'legal',
                'url' => '/admin/legal.php',
                'icon' => 'fa-gavel',
                'label' => 'Légal'
            ],
            [
                'page' => 'parametres',
                'url' => '/admin/parametres.php',
                'icon' => 'fa-cog',
                'label' => 'Paramètres'
            ]
        ];
        
        foreach ($menuItems as $item):
            $isActive = $currentPage === $item['page'];
            $activeClass = $isActive ? 'bg-green-50 text-green-600' : 'text-gray-700 hover:bg-gray-50';
        ?>
            <a href="<?= $item['url'] ?>" 
               class="flex items-center px-4 py-3 rounded-lg transition-colors <?= $activeClass ?>">
                <i class="fas <?= $item['icon'] ?> w-5"></i>
                <span class="ml-3 font-medium"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Divider -->
    <div class="my-6 border-t border-gray-200"></div>


    <!-- Déconnexion -->
    <a href="/logout.php" 
       class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
        <i class="fas fa-sign-out-alt w-5"></i>
        <span class="ml-3 font-medium">Déconnexion</span>
    </a>
</nav>

        <!-- User info (bottom) -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 bg-gray-50">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white font-semibold">
                    <?= strtoupper(substr($_SESSION['prenom'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($_SESSION['prenom'] ?? '') ?> <?= htmlspecialchars($_SESSION['nom'] ?? '') ?>
                    </p>
                    <p class="text-xs text-gray-500">Administrateur</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="ml-64 min-h-screen">
        <!-- Top bar -->
        <div class="bg-white border-b border-gray-200 h-20 flex items-center justify-between px-8">
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($title ?? 'Administration') ?></h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-500">
                    <?= date('d/m/Y H:i') ?>
                </span>
            </div>
        </div>

        <!-- Page content -->
        <div class="p-8">
            <?php
            // Afficher les messages flash s'il y en a
            if (isset($_SESSION['success'])) {
                echo '<div class="alert-flash mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <span>' . htmlspecialchars($_SESSION['success']) . '</span>
      </div>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <span>' . htmlspecialchars($_SESSION['error']) . '</span>
                      </div>';
                unset($_SESSION['error']);
            }
            ?>

            <?php
            // Le contenu de la page sera inséré ici via ob_get_clean()
            if (isset($pageContent)) {
                echo $pageContent;
            }
            ?>
        </div>
    </div>

   <script>
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-flash');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

</body>
</html>