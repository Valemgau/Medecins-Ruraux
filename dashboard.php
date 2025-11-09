<?php $role = $_SESSION['role'];

// Redirection selon rôle demandé (admin sans vérification supplémentaire)
if ($role === 'admin') {
    header('Location: /admin');
    exit;
} elseif ($role === 'recruteur') {
    header('Location: dashboard-recruteur.php');
    exit;
} else {
    header('Location: dashboard-candidat.php');
    exit;
}