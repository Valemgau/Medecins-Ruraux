<?php

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    exit('Accès interdit');
}
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(($title ?? "")) ?><?= ($title ? " - " : "") ?>Médecins ruraux</title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/includes/styles/index.css" />
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

</head>

<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">

    <?php include __DIR__ . '/../components/header.php'; ?>

    <main class="">
        <?= $pageContent ?? '' ?>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($customJS)): ?>
        <script>
            <?= $customJS ?>
        </script>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
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
    <?php endif; ?>


</body>

</html>