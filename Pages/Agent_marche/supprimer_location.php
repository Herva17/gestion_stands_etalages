<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$location_id = $_GET['id'] ?? 0;
if (!$location_id) {
    header('Location: dashboard.php?error=id_location_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que la location existe
$stmt = $db->prepare("SELECT id_etalage FROM location WHERE id_location = ?");
$stmt->execute([$location_id]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: dashboard.php?error=location_introuvable');
    exit;
}

// Supprimer la location
try {
    $db->beginTransaction();
    
    // Récupérer l'id de l'étalage
    $id_etalage = $location['id_etalage'];
    
    // Supprimer la location
    $stmt = $db->prepare("DELETE FROM location WHERE id_location = ?");
    $stmt->execute([$location_id]);
    
    // Libérer l'étalage
    $stmt = $db->prepare("UPDATE etalage SET statut = 'disponible' WHERE id_etalage = ?");
    $stmt->execute([$id_etalage]);
    
    $db->commit();
    header('Location: dashboard.php?success=location_supprimee');
    exit;
    
} catch (Exception $e) {
    $db->rollBack();
    header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>