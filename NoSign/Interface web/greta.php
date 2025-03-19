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

</body>
</html>
