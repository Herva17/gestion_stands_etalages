<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Etalage.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$id_etalage = $_GET['id'] ?? 0;

if ($id_etalage <= 0) {
    header('Location: /pages/Agent/dashboard.php?error=invalid_id');
    exit;
}

$etalage = new Etalage();
$result = $etalage->liberer($id_etalage);

if ($result['success']) {
    header('Location: /pages/Agent/dashboard.php?success=etalage_freed');
} else {
    header('Location: /pages/Agent/dashboard.php?error=' . urlencode($result['error']));
}
exit;