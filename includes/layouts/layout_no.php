<?php

require_once './includes/config.php';
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

<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen">

   

    <main class="min-h-[80vh] pt-10 flex flex-col md:flex-row">
        <!-- Contenu principal -->
        <section class="flex-1 p-6 order-1 md:order-none">
            <?= $pageContent ?? '' ?>
        </section>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($customJS)): ?>
        <script>
            <?= $customJS ?>
        </script>
    <?php endif; ?>


</body>

</html>