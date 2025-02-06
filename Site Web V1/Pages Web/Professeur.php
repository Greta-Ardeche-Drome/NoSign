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
            cursor: pointer;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .present {
            background: green;
            color: white;
        }
        .absent {
            background: red;
            color: white;
        }
    </style>
</head>
<body>
    <div class="main">
        <h2>Liste des élèves</h2>
        <div id="listeEleves">
            <div class="eleve">
                <span>Élève 1</span>
                <button class="presence absent" onclick="togglePresence(this)">Absent</button>
            </div>
            <div class="eleve">
                <span>Élève 2</span>
                <button class="presence present" onclick="togglePresence(this)">Présent</button>
            </div>
            <div class="eleve">
                <span>Élève 3</span>
                <button class="presence absent" onclick="togglePresence(this)">Absent</button>
            </div>
        </div>
    </div>

    <script>
        function togglePresence(button) {
            if (button.classList.contains("present")) {
                button.classList.remove("present");
                button.classList.add("absent");
                button.textContent = "Absent";
            } else {
                button.classList.remove("absent");
                button.classList.add("present");
                button.textContent = "Présent";
            }
        }
    </script>
</body>
</html>
