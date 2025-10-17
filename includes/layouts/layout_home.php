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


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(($title ?? "")) ?><?= ($title ? " - " : "") ?>Médecins ruraux</title>

    <!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="includes/styles/index.css" />
</head>

<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen">

    <?php include __DIR__ . '/../components/header.php'; ?>

    <main>
        <?= $pageHero ?? '' ?>
        <div class="min-h-[80vh] flex flex-col md:flex-rowmx-auto">
            <!-- Contenu principal -->
            <section class="flex-1 p-6 order-1 md:order-none">
                <?= $pageContent ?? '' ?>
            </section>


        </div>
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