<?php
session_start(); // Démarrer la session pour accéder à la session actuelle

// Détruire toutes les variables de session
session_unset();

// Détruire la session
session_destroy();

// Rediriger vers la page de login
header("Location: login.html");
exit();
?>
