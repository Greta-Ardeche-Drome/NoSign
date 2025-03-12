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

if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];

    // En-têtes HTTP pour forcer le téléchargement du fichier CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=presences_' . $selected_date . '.csv');

    // Ouvrir la sortie en mode écriture CSV
    $output = fopen('php://output', 'w');

    // Écrire BOM (Byte Order Mark) pour spécifier l'encodage UTF-8
    // Cela permettra à Excel de mieux reconnaître l'encodage des caractères
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Écrire l'en-tête du fichier CSV avec un séparateur ;
    fputcsv($output, ['Nom', 'Prénom', 'Présent Matin', 'Heure Scan Matin', 'Présent Après-midi', 'Heure Scan Après-midi'], ';');

    // Requête SQL pour récupérer les données
    $query = "SELECT e.nom, e.prenom, 
                     CASE WHEN MIN(CASE WHEN p.heure_scan < '12:00:00' THEN p.heure_scan ELSE NULL END) IS NOT NULL THEN 'Oui' ELSE 'Non' END AS present_matin,
                     MIN(CASE WHEN p.heure_scan < '12:00:00' THEN p.heure_scan ELSE NULL END) AS heure_matin,
                     CASE WHEN MAX(CASE WHEN p.heure_scan >= '12:00:00' THEN p.heure_scan ELSE NULL END) IS NOT NULL THEN 'Oui' ELSE 'Non' END AS present_apres_midi,
                     MAX(CASE WHEN p.heure_scan >= '12:00:00' THEN p.heure_scan ELSE NULL END) AS heure_apres_midi
              FROM etudiants e
              LEFT JOIN presences p ON e.uid = p.uid AND p.date_scan = ?
              GROUP BY e.uid, e.nom, e.prenom
              ORDER BY e.nom, e.prenom";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();

    // Écrire chaque ligne de données dans le fichier CSV avec un séparateur ;
    while ($row = $result->fetch_assoc()) {
        // Pas de besoin de utf8_encode ici, les caractères spéciaux doivent être traités correctement
        fputcsv($output, [
            $row['nom'],
            $row['prenom'],
            $row['present_matin'],
            $row['heure_matin'] ?? 'Absent',
            $row['present_apres_midi'],
            $row['heure_apres_midi'] ?? 'Absent'
        ], ';');
    }

    fclose($output);
}

$conn->close();
?>
