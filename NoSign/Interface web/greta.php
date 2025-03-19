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

$presences_par_mois = [];
$mois_noms = [
    "01" => "Janvier", "02" => "Février", "03" => "Mars",
    "04" => "Avril", "05" => "Mai", "06" => "Juin",
    "07" => "Juillet", "08" => "Août", "09" => "Septembre",
    "10" => "Octobre", "11" => "Novembre", "12" => "Décembre"
];

$selected_month = "";
$selected_year = date("Y");

if (isset($_GET['month']) && isset($_GET['year'])) {
    $selected_month = $_GET['month'];
    $selected_year = $_GET['year'];

    $query = "SELECT e.uid, e.nom, e.prenom, p.date_scan, p.heure_scan 
          FROM etudiants e 
          INNER JOIN presences p ON e.uid = p.uid 
          WHERE DATE_FORMAT(p.date_scan, '%Y-%m') = ?
          ORDER BY p.date_scan, e.nom, e.prenom";

    $date_filter = $selected_year . "-" . $selected_month;
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date_filter);
    $stmt->execute();
    $result_presences = $stmt->get_result();

    while ($row = $result_presences->fetch_assoc()) {
        $date = $row['date_scan'] ?? 'Non scanné';
        $presences_par_mois[] = [
            'date' => $date,
            'uid' => $row['uid'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'heure_scan' => $row['heure_scan'] ?? 'Non scanné'
        ];
    }

    // Exportation CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=presences_' . $selected_month . '_' . $selected_year . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Écriture de l'en-tête
        fputcsv($output, ['Date', 'UID', 'Nom', 'Prénom', 'Heure Scan'], ';');
        
        // Écriture des données
        foreach ($presences_par_mois as $presence) {
            fputcsv($output, [
                $presence['date'], $presence['uid'],
                $presence['nom'], $presence['prenom'],
                $presence['heure_scan'],
            ], ';');
        }
        
        fclose($output);
        exit();
    }
}

// Ajout d'une présence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajouter_presence') {
    $uid = $_POST['uid'];
    $date_scan = $_POST['date_scan'];
    $heure_scan = $_POST['heure_scan'];
    
    // Insérer la présence dans la base de données
    $stmt = $conn->prepare("INSERT INTO presences (uid, date_scan, heure_scan) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $uid, $date_scan, $heure_scan);
    $stmt->execute();
    $stmt->close();
    
    // Rediriger pour actualiser la page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$conn->close();
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
<p>Les entreprises peuvent consulter les présences des étudiants pour un mois donné.</p>

<h2>Consulter et exporter les présences d'un mois entier</h2>
<form method="GET">
    <label for="month">Sélectionner un mois :</label>
    <select name="month" id="month" required>
        <?php foreach ($mois_noms as $key => $value): ?>
            <option value="<?= $key ?>" <?= ($key == $selected_month) ? "selected" : "" ?>>
                <?= $value ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="year">Sélectionner une année :</label>
    <select name="year" id="year" required>
        <?php for ($i = date("Y"); $i >= date("Y") - 5; $i--): ?>
            <option value="<?= $i ?>" <?= ($i == $selected_year) ? "selected" : "" ?>>
                <?= $i ?>
            </option>
        <?php endfor; ?>
    </select>

    <button type="submit">Voir les présences</button>
</form>

<?php if (!empty($selected_month)): ?>
    <h3>Présences du mois de <?= $mois_noms[$selected_month] ?> <?= htmlspecialchars($selected_year) ?></h3>
    <table>
        <tr>
            <th>Date</th>
            <th>UID</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Heure Scan</th>
        </tr>
        
        <?php foreach ($presences_par_mois as $presence): ?>
            <tr>
                <td><?= htmlspecialchars($presence['date']) ?></td>
                <td><?= htmlspecialchars($presence['uid']) ?></td>
                <td><?= htmlspecialchars($presence['nom']) ?></td>
                <td><?= htmlspecialchars($presence['prenom']) ?></td>
                <td><?= htmlspecialchars($presence['heure_scan']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <a href="Entreprise.php?month=<?= htmlspecialchars($selected_month) ?>&year=<?= htmlspecialchars($selected_year) ?>&export=csv" class="btn-export">Exporter en CSV</a>
<?php endif; ?>

<h3>Ajouter une présence</h3>
<form method="POST">
    <input type="hidden" name="action" value="ajouter_presence">
    
    <label for="uid">Étudiant :</label>
    <select name="uid" required>
        <?php
        // Récupérer la liste des étudiants
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
        $students_sql = "SELECT uid, nom, prenom FROM etudiants ORDER BY nom";
        $students_result = $conn->query($students_sql);
        while ($row = $students_result->fetch_assoc()) { ?>
            <option value="<?= htmlspecialchars($row['uid']) ?>">
                <?= htmlspecialchars($row['nom']) ?> <?= htmlspecialchars($row['prenom']) ?>
            </option>
        <?php }
        $conn->close();
        ?>
    </select>
    
    <label for="date_scan">Date :</label>
    <input type="date" name="date_scan" value="<?= $selected_year . '-' . $selected_month . '-01' ?>" required>
    
    <label for="heure_scan">Heure :</label>
    <input type="time" name="heure_scan" required>
    
    <button type="submit">Ajouter la présence</button>
</form>

</body>
</html>
