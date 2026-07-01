<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Etalage.php';
require_once __DIR__ . '/../../Classes/Database.php';

$admin = new Administrateur();
if (!$admin->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$user = $admin->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ../../login.php?error=session_expired');
    exit;
}

$etalage_id = $_GET['id'] ?? 0;
if (!$etalage_id) {
    header('Location: gestion_etalages.php?error=id_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que l'étalage existe
$stmt = $db->prepare("SELECT id_etalage, numero FROM etalage WHERE id_etalage = ?");
$stmt->execute([$etalage_id]);
$etalage = $stmt->fetch();

if (!$etalage) {
    header('Location: gestion_etalages.php?error=etalage_introuvable');
    exit;
}

// Vérifier si l'étalage a des locations
$stmt = $db->prepare("SELECT COUNT(*) as total FROM location WHERE id_etalage = ? AND status != 'termine'");
$stmt->execute([$etalage_id]);
$result = $stmt->fetch();

if ($result['total'] > 0) {
    header('Location: gestion_etalages.php?error=etalage_a_locations');
    exit;
}

try {
    $etalage_obj = new Etalage();
    $result = $etalage_obj->delete($etalage_id);
    
    if ($result['success']) {
        header('Location: gestion_etalages.php?success=etalage_supprime');
    } else {
        header('Location: gestion_etalages.php?error=' . urlencode($result['error']));
    }
    exit;
    
} catch (Exception $e) {
    header('Location: gestion_etalages.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>