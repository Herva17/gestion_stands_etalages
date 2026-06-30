<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$commercant_id = $_GET['id'] ?? 0;
if (!$commercant_id) {
    header('Location: dashboard.php?error=id_commercant_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que le commerçant existe
$stmt = $db->prepare("SELECT id_user FROM commercant WHERE id_commercant = ?");
$stmt->execute([$commercant_id]);
$commercant = $stmt->fetch();

if (!$commercant) {
    header('Location: dashboard.php?error=commercant_introuvable');
    exit;
}

// Supprimer le commerçant
try {
    $db->beginTransaction();
    
    // Supprimer le commerçant
    $stmt = $db->prepare("DELETE FROM commercant WHERE id_commercant = ?");
    $stmt->execute([$commercant_id]);
    
    // Supprimer l'utilisateur associé
    $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id_user = ?");
    $stmt->execute([$commercant['id_user']]);
    
    $db->commit();
    header('Location: dashboard.php?success=commercant_supprime');
    exit;
    
} catch (Exception $e) {
    $db->rollBack();
    header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>