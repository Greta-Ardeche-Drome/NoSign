<?php
session_start(); // Démarrer la session pour stocker les infos utilisateur

// Connexion à la base de données
$servername = "127.0.0.1";
$username = "root"; // Par défaut sur Wamp
$password = ""; // Pas de mot de passe par défaut
$dbname = "nosigndb";
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
    $action = $_POST['action'];

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

        $stmt = $conn->prepare("UPDATE users SET login = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nom, $email, $role, $id);
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
    <a href="logout.php">Déconnexion</a>
    
    <h3>Ajouter un utilisateur</h3>
<form method="POST">
    <input type="hidden" name="action" value="ajouter">
    <input type="text" name="nom" placeholder="Nom" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Mot de passe" required> <!-- Champ mot de passe -->
    <select name="role">
        <option value="eleve">Élève</option>
        <option value="professeur">Professeur</option>
        <option value="entreprise">Entreprise</option>
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
                        <input type="text" name="nom" value="<?= htmlspecialchars($user['login']) ?>" required>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        <select name="role">
                            <option value="eleve" <?= $user['role'] == 'eleve' ? 'selected' : '' ?>>Élève</option>
                            <option value="professeur" <?= $user['role'] == 'professeur' ? 'selected' : '' ?>>Professeur</option>
                            <option value="entreprise" <?= $user['role'] == 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        </select>
                        <button type="submit">Modifier</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button type="submit" onclick="return confirm('Confirmer la suppression ?');">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</body>
</html>
