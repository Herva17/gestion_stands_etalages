<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Secteur.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./pages/Agent_marche/dashboard.php');
    exit;
}

$designation = $_POST['designation'] ?? '';

if (empty($designation)) {
    header('Location: dashboard.php?error=empty_designation');
    exit;
}

$secteur = new Secteur();
$result = $secteur->create($designation);

if ($result['success']) {
    header('Location: dashboard.php?success=secteur_added');
} else {
    header('Location: dashboard.php?error=' . urlencode($result['error']));
}
exit;