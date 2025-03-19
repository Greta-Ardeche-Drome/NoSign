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

// Récupérer les utilisateurs depuis la base de données
$query = "SELECT * FROM users";
$result = $conn->query($query);

$users = [];
if ($result->num_rows > 0) {
    // Mettre les résultats dans un tableau
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    // Ajouter un utilisateur
    if ($action == 'ajouter') {
        $nom = $_POST['nom'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $password = $_POST['password']; // Récupérer le mot de passe saisi
        $password_hash = hash('sha256', $password); // Générer un hash du mot de passe avec SHA-256

        // Insérer l'utilisateur avec son mot de passe hashé
        $stmt = $conn->prepare("INSERT INTO users (login, password_hash, email, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nom, $password_hash, $email, $role);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']); // Rediriger pour actualiser la page
    }

    // Modifier un utilisateur
    if ($action == 'modifier') {
        $id = $_POST['id'];
        $nom = $_POST['nom'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $new_password = $_POST['new_password'] ?? null; // Nouveau mot de passe (optionnel)

        // Si un nouveau mot de passe est fourni, le hashé
        if ($new_password) {
            $password_hash = hash('sha256', $new_password);
            $stmt = $conn->prepare("UPDATE users SET login = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nom, $email, $role, $password_hash, $id);
        } else {
            // Si aucun mot de passe n'est fourni, on ne le met pas à jour
            $stmt = $conn->prepare("UPDATE users SET login = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nom, $email, $role, $id);
        }

        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']); // Rediriger pour actualiser la page
    }

    // Supprimer un utilisateur
    if ($action == 'supprimer') {
        $id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']); // Rediriger pour actualiser la page
    }
}

// Suppression d'une présence
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $delete_sql = "DELETE FROM presences WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
    $delete_stmt->close();
}

// Récupérer les dates disponibles
$dates_sql = "SELECT DISTINCT date_scan FROM presences ORDER BY date_scan DESC";
$dates_result = $conn->query($dates_sql);

$selected_date = date("Y-m-d");
if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];
}

// Récupérer les présences pour la date sélectionnée
$presences_sql = "SELECT p.id, e.uid, e.nom, e.prenom, p.heure_scan FROM presences p JOIN etudiants e ON p.uid = e.uid WHERE p.date_scan = ?";
$presences_stmt = $conn->prepare($presences_sql);
$presences_stmt->bind_param("s", $selected_date);
$presences_stmt->execute();
$presences_result = $presences_stmt->get_result();
$presences_stmt->close();

// Récupérer la liste des étudiants
$students_sql = "SELECT uid, nom, prenom FROM etudiants ORDER BY nom";
$students_result = $conn->query($students_sql);
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

// Gestion de l'import CSV
if (isset($_POST['import']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $csvData = file_get_contents($file['tmp_name']);
        $lines = explode(PHP_EOL, $csvData);
        
        // Supprimer la première ligne (l'en-tête)
        array_shift($lines);

        // Préparer une requête pour l'insertion
        $query = "INSERT INTO etudiants (nom, prenom, uid) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        foreach ($lines as $line) {
            // S'assurer que la ligne n'est pas vide
            if (!empty($line)) {
                $fields = str_getcsv($line, ";");  // Assurez-vous d'utiliser le bon délimiteur
                if (count($fields) == 3) {  // Assurez-vous qu'il y a bien 3 champs : nom, prénom, uid
                    $nom = trim($fields[0]);
                    $prenom = trim($fields[1]);
                    $uid = trim($fields[2]);

                    // Vérifier si l'UID existe déjà dans la base de données
                    $checkQuery = "SELECT id FROM etudiants WHERE uid = ?";
                    $checkStmt = $conn->prepare($checkQuery);
                    $checkStmt->bind_param("s", $uid);
                    $checkStmt->execute();
                    $checkStmt->store_result();

                    if ($checkStmt->num_rows == 0) { // Si l'UID n'existe pas encore
                        $stmt->bind_param("sss", $nom, $prenom, $uid);
                        $stmt->execute();
                    }
                }
            }
        }
        echo "<p>Importation terminée avec succès !</p>";
    } else {
        echo "<p>Erreur lors du téléchargement du fichier.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="stylesheet" href="styleAdmin.css">
</head>
<body>
    <h2>Gestion des utilisateurs</h2>
    
    <form action="logout.php" method="POST" style="position: absolute; top: 10px; right: 10px;">
        <button type="submit">Se déconnecter</button>
    </form>

    <h3>Ajouter un utilisateur</h3>
    <form method="POST">
        <input type="hidden" name="action" value="ajouter">
        <input type="text" name="nom" placeholder="Nom" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <select name="role">
            <option value="eleve">Élève</option>
            <option value="professeur">Professeur</option>
            <option value="greta">Greta</option>
            <option value="admin">Administrateur</option>
        </select>
        <button type="submit">Ajouter</button>
    </form>
    
    <h3>Liste des utilisateurs</h3>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $user) { ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['login']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button type="submit">Modifier</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button type="submit" onclick="return confirm('Confirmer la suppression de cet utilisateur ?');">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
    
    <h3>Importation des étudiants depuis un fichier CSV</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" required>
        <button type="submit" name="import">Importer</button>
    </form>
    
    <h3>Présences pour la date sélectionnée</h3>
    <form method="GET">
        <label for="date">Sélectionner une date :</label>
        <input type="date" name="date" value="<?= $selected_date ?>" required>
        <button type="submit">Voir</button>
    </form>

    <table border="1">
        <tr>
            <th>ID</th>
            <th>UID</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Heure</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $presences_result->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['uid']) ?></td>
                <td><?= htmlspecialchars($row['nom']) ?></td>
                <td><?= htmlspecialchars($row['prenom']) ?></td>
                <td><?= htmlspecialchars($row['heure_scan']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                        <button type="submit" onclick="return confirm('Confirmer la suppression de cette présence ?');">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>

<?php
// Fermer la connexion à la base de données
$conn->close();
?>
