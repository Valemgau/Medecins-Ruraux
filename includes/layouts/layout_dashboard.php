<?php

require_once './includes/config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role, is_active, email_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if (!$user['is_active']) {
        session_destroy();
        header('Location: login.php?message=compte_inactif');
        exit;
    }

    if ($user['role'] !== 'admin') {
        if (empty($user['email_verified']) || !$user['email_verified']) {
            header('Location: valid_email.php');
            exit;
        }
    }
}

?>

<?php
function breadcrumb()
{
    $dashboard_link = '/dashboard-candidat.php';
    $dashboard_label = 'Espace candidat';

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruteur') {
        $dashboard_link = '/dashboard-recruteur.php';
        $dashboard_label = 'Espace recruteur';
    }

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Si on est sur la page tableau de bord, ne pas afficher le breadcrumb
    if ($path === $dashboard_link) {
        return;
    }

    $segments = array_filter(explode('/', $path));

    // Mapping des noms de pages avec icônes
    $pages = [
        'abonnement_success.php' => ['label' => 'Abonnement confirmé', 'icon' => 'fa-check-circle'],
        'abonnement.php' => ['label' => 'Choisir un abonnement', 'icon' => 'fa-star'],
        'resilier.php' => ['label' => 'Résilier', 'icon' => 'fa-times-circle'],
        'tarifs.php' => ['label' => 'Tarifs', 'icon' => 'fa-tag'],
        'valid_email.php' => ['label' => 'Validation email', 'icon' => 'fa-envelope-circle-check'],
        'verify_email.php' => ['label' => 'Vérification email', 'icon' => 'fa-envelope'],
        'liste.php' => ['label' => 'Candidats', 'icon' => 'fa-users'],
        'supprimer_compte.php' => ['label' => 'Suppression compte', 'icon' => 'fa-trash-alt'],
        'mot-de-passe.php' => ['label' => 'Modifier mot de passe', 'icon' => 'fa-key'],
    ];

    echo '<nav class="bg-gradient-to-r from-green-50 to-white border-b border-green-100 py-3 px-4 rounded-lg shadow-sm mb-6" aria-label="Breadcrumb">';
    echo '<ol class="flex items-center flex-wrap gap-2 text-sm">';

    // Lien dashboard
    echo '<li class="flex items-center">';
    echo '<a href="' . htmlspecialchars($dashboard_link) . '" class="flex items-center gap-2 text-green-600 hover:text-green-700 font-medium transition-colors group">';
    echo '<i class="fas fa-home text-base group-hover:scale-110 transition-transform"></i>';
    echo '<span>' . htmlspecialchars($dashboard_label) . '</span>';
    echo '</a>';
    echo '</li>';

    $url_accumulate = '';
    $count = count($segments);
    $i = 0;

    foreach ($segments as $segment) {
        $i++;

        // Ignorer les segments de dashboard dans le fil d'ariane
        if (in_array($segment, ['dashboard', 'dashboard-recruteur', 'dashboard-candidat'])) {
            continue;
        }

        $url_accumulate .= '/' . $segment;

        // Récupérer les infos de la page
        $pageInfo = $pages[$segment] ?? null;
        $nom_affiche = $pageInfo['label'] ?? ucfirst(str_replace(['-', '_', '.php'], [' ', ' ', ''], $segment));
        $icon = $pageInfo['icon'] ?? 'fa-file';

        if ($segment !== '') {
            // Séparateur
            echo '<li class="flex items-center">';
            echo '<i class="fas fa-chevron-right text-green-400 text-xs"></i>';
            echo '</li>';

            // Lien ou texte
            echo '<li class="flex items-center">';
            if ($i < $count) {
                echo '<a href="' . htmlspecialchars($url_accumulate) . '" class="flex items-center gap-2 text-green-600 hover:text-green-700 font-medium transition-colors group">';
                echo '<i class="fas ' . htmlspecialchars($icon) . ' text-sm group-hover:scale-110 transition-transform"></i>';
                echo '<span>' . htmlspecialchars($nom_affiche) . '</span>';
                echo '</a>';
            } else {
                echo '<span class="flex items-center gap-2 text-gray-600 font-semibold">';
                echo '<i class="fas ' . htmlspecialchars($icon) . ' text-sm text-green-600"></i>';
                echo '<span>' . htmlspecialchars($nom_affiche) . '</span>';
                echo '</span>';
            }
            echo '</li>';
        }
    }

    echo '</ol>';
    echo '</nav>';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(($title ?? "")) ?><?= ($title ? " - " : "") ?>Médecins ruraux</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="/includes/styles/index.css" />

    <style>
        /* Animation au hover du breadcrumb */
        nav[aria-label="Breadcrumb"] a {
            position: relative;
            overflow: hidden;
        }

        nav[aria-label="Breadcrumb"] a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transition: width 0.3s ease;
        }

        nav[aria-label="Breadcrumb"] a:hover::after {
            width: 100%;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

    <?php include __DIR__ . '/../components/header.php'; ?>

    <main class="min-h-[80vh] max-w-4xl mx-auto">
        <div class="p-4 md:p-6">
            <?php breadcrumb(); ?>
        </div>

        <?= $pageContent ?? '' ?>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($customJS)): ?>
        <script>
            <?= $customJS ?>
        </script>
    <?php endif; ?>

</body>

</html>