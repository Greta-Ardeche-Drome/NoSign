<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ./login.html"); // Si pas connecté, retour au login
    exit();
}

echo "Bienvenue " . $_SESSION["user"] . "! Voici la gestion des présences.";
?>
<br><a href='logout.php'>Déconnexion</a>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présence des élèves</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@500&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Jost', sans-serif;
            background: linear-gradient(to bottom, #0f0c29, #302b63, #24243e);
        }
        .main {
            width: 400px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 5px 20px 50px #000;
            text-align: center;
        }
        h2 {
            color: #573b8a;
        }
        .eleve {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #e0dede;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .presence {
            cursor: not-allowed;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            background: #ccc;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="main">
        <h2>Liste des élèves</h2>
        <div id="listeEleves">
            <div class="eleve">
                <span>Élève 1</span>
                <button class="presence" disabled>Absent</button>
            </div>
            <div class="eleve">
                <span>Élève 2</span>
                <button class="presence" disabled>Présent</button>
            </div>
            <div class="eleve">
                <span>Élève 3</span>
                <button class="presence" disabled>Absent</button>
            </div>
        </div>
    </div>
</body>
</html>
