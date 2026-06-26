<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Etalage.php';

// Vérifier si l'utilisateur est connecté
$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$data = [
    'numero' => $_POST['numero'] ?? '',
    'localisation' => $_POST['localisation'] ?? '',
    'id_secteur' => $_POST['id_secteur'] ?? 0
];

if (empty($data['numero']) || $data['id_secteur'] <= 0) {
    header('Location: dashboard.php?error=invalid_data');
    exit;
}

$etalage = new Etalage();
$result = $etalage->create($data);

if ($result['success']) {
    header('Location: dashboard.php?success=etalage_added');
} else {
    header('Location:dashboard.php?error=' . urlencode($result['error']));
}
exit;