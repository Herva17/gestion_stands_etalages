<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$paiement_id = $_GET['id'] ?? 0;
if (!$paiement_id) {
    header('Location: dashboard.php?error=id_paiement_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que le paiement existe
$stmt = $db->prepare("SELECT id_paiement FROM paiement WHERE id_paiement = ?");
$stmt->execute([$paiement_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    header('Location: dashboard.php?error=paiement_introuvable');
    exit;
}

// Supprimer le paiement
try {
    $stmt = $db->prepare("DELETE FROM paiement WHERE id_paiement = ?");
    $stmt->execute([$paiement_id]);
    header('Location: dashboard.php?success=paiement_supprime');
    exit;
    
} catch (Exception $e) {
    header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>