<?php
session_start();
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "nosign";
$port = 3307;

$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Récupérer la liste des étudiants et leur présence
if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];

    $query = "SELECT e.uid, e.nom, e.prenom, p.heure_scan 
              FROM etudiants e 
              LEFT JOIN presences p ON e.uid = p.uid AND p.date_scan = ?
              ORDER BY e.nom, e.prenom";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result_presences = $stmt->get_result();

    // Initialiser un tableau pour stocker les présences
    $presences_par_etudiant = [];

    while ($row = $result_presences->fetch_assoc()) {
        $uid = $row['uid'];
        
        // Initialisation pour chaque étudiant
        if (!isset($presences_par_etudiant[$uid])) {
            $presences_par_etudiant[$uid] = [
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'matin' => false,
                'apres_midi' => false
            ];
        }

        // Vérifier les horaires de scan
        if (!is_null($row['heure_scan'])) {
            if ($row['heure_scan'] < '12:00:00') {
                $presences_par_etudiant[$uid]['matin'] = true;
            } else {
                $presences_par_etudiant[$uid]['apres_midi'] = true;
            }
        }
    }

    // Vérifier si un étudiant n'a aucun enregistrement et le marquer absent
    $query_all_students = "SELECT uid, nom, prenom FROM etudiants";
    $result_all_students = $conn->query($query_all_students);
    
    while ($row = $result_all_students->fetch_assoc()) {
        $uid = $row['uid'];
        if (!isset($presences_par_etudiant[$uid])) {
            $presences_par_etudiant[$uid] = [
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'matin' => false,
                'apres_midi' => false
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation des présences</title>
    <link rel="stylesheet" href="styleProf.css">
</head>
<body>
<form action="logout.php" method="POST" style="position: absolute; top: 10px; right: 10px;">
    <button type="submit">Se déconnecter</button>
</form>
<h2>Consultation des présences</h2>
<p>Les entreprises peuvent consulter les présences des étudiants pour un jour donné.</p>

<h2>Consulter les présences d'un autre jour</h2>
<form method="GET">
    <label for="date">Sélectionner une date :</label>
    <input type="date" name="date" id="date" required>
    <button type="submit">Voir</button>
</form>

<?php if (isset($selected_date)): ?>
    <h3>Présences du <?= htmlspecialchars($selected_date) ?></h3>
    <table>
        <tr>
            <th>UID</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Présence Matin</th>
            <th>Présence Après-midi</th>
        </tr>
        
        <?php foreach ($presences_par_etudiant as $uid => $presence): ?>
            <tr>
                <td><?= htmlspecialchars($uid) ?></td>
                <td><?= htmlspecialchars($presence['nom']) ?></td>
                <td><?= htmlspecialchars($presence['prenom']) ?></td>
                <td><?= $presence['matin'] ? "✔️ Présent" : "❌ Absent" ?></td>
                <td><?= $presence['apres_midi'] ? "✔️ Présent" : "❌ Absent" ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if (isset($selected_date)): ?>
    <a href="export_csv.php?date=<?= htmlspecialchars($selected_date) ?>" class="btn-export">Exporter en CSV</a>
<?php endif; ?>


</body>
</html>

<?php
$conn->close();
?>
