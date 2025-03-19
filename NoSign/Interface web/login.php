<?php
session_start(); // Démarrer la session pour stocker les infos utilisateur

// Connexion à la base de données
$servername = "127.0.0.1";
$username = "root"; // Par défaut sur Wamp
$password = ""; // Pas de mot de passe par défaut
$dbname = "nosign";
$port = 3307; // Port spécifique pour MariaDB

// Connexion à MariaDB (port 3307)
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Récupérer les données du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Vérifier si l'utilisateur existe
    $sql = "SELECT login, password_hash, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Vérifier le mot de passe haché
        if (hash("sha256", $password) === $user["password_hash"]) {
            $_SESSION["user"] = $user["login"];
            $_SESSION["role"] = $user["role"];
            
            // Redirection en fonction du rôle
            switch ($user["role"]) {
                case "professeur":
                    header("Location: Professeur.php");
                    break;
                case "greta":
                    header("Location: greta.php");
                    break;
                case "administrateur":
                    header("Location: Administrateur.php");
                    break;
                default:
                    echo "Rôle non reconnu.";
                    exit();
            }
            exit(); // Toujours appeler exit() après une redirection
        } else {
            echo "Mot de passe incorrect.";
        }
    } else {
        echo "Utilisateur non trouvé.";
    }

    // Fermer la requête préparée
    $stmt->close();
}

// Fermer la connexion à la base de données
$conn->close();
?>