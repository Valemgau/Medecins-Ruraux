<?php

require_once './includes/config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role, is_active, email_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Utilisateur inexistant : logout
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if (!$user['is_active']) {
        // Utilisateur non actif : logout
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
    // Détermine le lien du tableau de bord selon le rôle utilisateur en session
    $dashboard_link = '/dashboard-candidat.php';
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'recruteur') {
        $dashboard_link = '/dashboard-recruteur.php';
    }

    // Récupère le chemin URL sans le domaine
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Si on est sur la page tableau de bord, afficher juste "Tableau de bord" sans lien
    if ($path === $dashboard_link) {
        echo '<nav class="text-sm py-2" aria-label="Breadcrumb">';
        echo '<ol class="list-reset flex text-gray-700">';
        echo '<li class="text-gray-500">Tableau de bord</li>';
        echo '</ol></nav>';
        return;
    }

    $segments = array_filter(explode('/', $path)); // segments non vides

    // Mapping des noms de pages en français simples
    $names = [
        'abonnement_success.php' => 'Succès d\'abonnement',
        'abonnement.php' => 'Souscrire à un abonnement',
        'resilier.php' => 'Résilier mon abonnement',
        'tarifs.php' => 'Voir les tarifs',
        'valid_email.php' => 'Valider l\'adresse e-mail',
        'verify_email.php' => 'Vérifier l\'adresse e-mail',
        'liste.php' => 'Candidats disponibles',
        'supprimer_compte.php' => 'Supprimer mon compte'
    ];

    echo '<nav class="text-sm py-2" aria-label="Breadcrumb">';
    echo '<ol class="list-reset flex text-gray-700">';

    // Premier lien personnalisé : Tableau de bord selon rôle
    echo '<li><a href="' . $dashboard_link . '" class="text-blue-600 hover:underline">Tableau de bord</a></li>';

    $url_accumulate = '';
    $count = count($segments);
    $i = 0;

    foreach ($segments as $segment) {
        $i++;
        $url_accumulate .= '/' . $segment;
        $nom_affiche = $names[$segment] ?? ucfirst(str_replace(['-', '_'], ' ', $segment));

        // Éviter de répéter 'dashboard' ou 'dashboard-recruteur' dans la suite du breadcrumb
        if ($segment === 'dashboard' || $segment === 'dashboard-recruteur') {
            continue;
        }

        if ($i <= $count && $segment !== '') {
            echo '<li><span class="mx-2">/</span></li>';
            if ($i < $count) {
                echo '<li><a href="' . $url_accumulate . '" class="text-blue-600 hover:underline">' . htmlspecialchars($nom_affiche) . '</a></li>';
            } else {
                echo '<li class="text-gray-500">' . htmlspecialchars($nom_affiche) . '</li>';
            }
        }
    }
    echo '</ol></nav>';
}

?>



<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(($title ?? "")) ?><?= ($title ? " - " : "") ?>Médecins ruraux</title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="includes/styles/index.css" />
</head>

<body class="bg-gray-100 text-gray-800">

    <?php include __DIR__ . '/../components/header.php'; ?>

    <main class="min-h-[80vh] max-w-4xl mx-auto">
        <!-- <main class="max-w-md mx-auto"> -->
        <div class="p-4 md:p-6 text-lg">
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

    <!-- <?php if (!empty($success)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: <?= json_encode($success) ?>,
                    confirmButtonText: 'OK'
                });
            });
        </script>
    <?php endif; ?> -->


</body>

</html>