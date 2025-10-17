<?php

$accountDeleted = isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1';

$title = "Compte supprimé";
ob_start();
?>

<div class="md:p-6 max-w-7xl mx-auto text-center">
    <?php if ($accountDeleted): ?>
        <h1 class="text-4xl font-bold mb-6 text-green-700">Votre compte a bien été supprimé</h1>
        <p class="mb-4 text-lg">Merci d’avoir utilisé notre plateforme. Nous espérons vous revoir bientôt.</p>
        <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded">
            Retour à l'accueil
        </a>
    <?php else: ?>
        <h1 class="text-4xl font-bold mb-6 text-red-700">Action non autorisée</h1>
        <p class="mb-4 text-lg">Vous ne pouvez pas accéder directement à cette page.</p>
        <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded">
            Retour à l'accueil
        </a>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_default.php'; // À adapter selon votre layout simple
?>
