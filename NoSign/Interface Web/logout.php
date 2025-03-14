<?php
session_start();
session_destroy(); // Détruit la session en cours
header("Location: login.html"); // Redirection vers la page de connexion
exit();
?>
