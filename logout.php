<?php
session_start();

// Détruire toutes les données de session
$_SESSION = [];
session_unset();
session_destroy();

// Redirection vers la page d'accueil ou page de connexion
header('Location: index.php');
exit;
