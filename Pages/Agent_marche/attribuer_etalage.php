<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Location.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/Agent/dashboard.php');
    exit;
}

$id_etalage = $_POST['id_etalage'] ?? 0;
$id_commercant = $_POST['id_commercant'] ?? 0;
$montant_location = $_POST['montant_location'] ?? 0;
$duree = $_POST['duree'] ?? 30;

if ($id_etalage <= 0 || $id_commercant <= 0 || $montant_location <= 0) {
    header('Location: /pages/Agent/dashboard.php?error=invalid_data');
    exit;
}

$data = [
    'id_etalage' => $id_etalage,
    'id_commercant' => $id_commercant,
    'montant_location' => $montant_location,
    'duree' => $duree
];

$location = new Location();
$result = $location->create($data);

if ($result['success']) {
    header('Location: /pages/Agent/dashboard.php?success=attribution_done');
} else {
    header('Location: /pages/Agent/dashboard.php?error=' . urlencode($result['error']));
}
exit;