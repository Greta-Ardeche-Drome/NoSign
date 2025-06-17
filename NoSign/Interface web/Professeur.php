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

// Traitement du bouton "Présent"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['uid'])) {
    $uid = $_POST['uid'];
    $date_scan = date("Y-m-d");
    $heure_scan = date("H:i:s");

    // Déterminer si c'est le matin ou l'après-midi
    $est_matin = (date("H") < 12);

    // Vérifier si la présence est déjà enregistrée pour la période correspondante
    $check_sql = "SELECT heure_scan FROM presences WHERE uid = ? AND date_scan = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $uid, $date_scan);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    $est_present_matin = false;
    $est_present_apres_midi = false;

    while ($row = $result->fetch_assoc()) {
        if ($row['heure_scan'] < '12:00:00') {
            $est_present_matin = true;
        } else {
            $est_present_apres_midi = true;
        }
    }
    $check_stmt->close();

    // Insérer la présence seulement si elle n'est pas encore enregistrée pour la période
    if (($est_matin && !$est_present_matin) || (!$est_matin && !$est_present_apres_midi)) {
        $insert_sql = "INSERT INTO presences (uid, date_scan, heure_scan) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $uid, $date_scan, $heure_scan);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

// Récupérer la liste des étudiants
$sql = "SELECT id, uid, nom, prenom FROM etudiants";
$result = $conn->query($sql);


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
    <title>Gestion des présences</title>
    <link rel="stylesheet" href="styleProf.css">
</head>
<body>
<form action="logout.php" method="POST" style="position: absolute; top: 10px; right: 10px;">
    <button type="submit">Se déconnecter</button>
</form>

<h2>Gestion des présences</h2>
<table>
    <tr>
        <th>UID</th>
        <th>Nom</th>
        <th>Prénom</th>
        <th>Présence Matin</th>
        <th>Présence Après-midi</th>
        <th>Action</th>
    </tr>
    
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php
        $uid = $row["uid"];
        $nom = $row["nom"];
        $prenom = $row["prenom"];
        $date_scan = date("Y-m-d");

        // Vérifier la présence matin et après-midi
        $check_sql = "SELECT heure_scan FROM presences WHERE uid = ? AND date_scan = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $uid, $date_scan);
        $check_stmt->execute();
        $presence_result = $check_stmt->get_result();

        $est_present_matin = false;
        $est_present_apres_midi = false;

        while ($presence = $presence_result->fetch_assoc()) {
            if ($presence['heure_scan'] < '12:00:00') {
                $est_present_matin = true;
            } else {
                $est_present_apres_midi = true;
            }
        }
        $check_stmt->close();
        ?>
        
        <tr>
            <td><?= htmlspecialchars($uid) ?></td>
            <td><?= htmlspecialchars($nom) ?></td>
            <td><?= htmlspecialchars($prenom) ?></td>
            <td><?= $est_present_matin ? "✔️ Présent" : "❌ Absent" ?></td>
            <td><?= $est_present_apres_midi ? "✔️ Présent" : "❌ Absent" ?></td>
            <td>
                <?php if (!$est_present_matin && !$est_present_apres_midi): ?>
                    <form method="POST">
                        <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                        <button type="submit">Présent</button>
                    </form>
                <?php else: ?>
                    <button disabled>Validé</button>
                <?php endif; ?>

            </td>
        </tr>
    <?php endwhile; ?>
</table>

<!-- Section d'export CSV pour la date courante -->
<div style="margin: 20px 0;">
    <a href="export_csv.php?date=<?= date('Y-m-d') ?>" class="btn-export">Exporter les présences du jour en CSV</a>
</div>

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
    
    <!-- Bouton d'export CSV pour la date sélectionnée -->
    <div style="margin: 20px 0;">
        <a href="export_csv.php?date=<?= htmlspecialchars($selected_date) ?>" class="btn-export">Exporter en CSV</a>
    </div>
<?php endif; ?>


</body>
</html>

<?php
$conn->close();
?>