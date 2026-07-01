<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Secteur.php';
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

$secteur_id = $_GET['id'] ?? 0;
if (!$secteur_id) {
    header('Location: gestion_secteurs.php?error=id_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que le secteur existe
$stmt = $db->prepare("SELECT id_secteur, designation FROM secteur WHERE id_secteur = ?");
$stmt->execute([$secteur_id]);
$secteur = $stmt->fetch();

if (!$secteur) {
    header('Location: gestion_secteurs.php?error=secteur_introuvable');
    exit;
}

// Vérifier si le secteur a des étalages
$stmt = $db->prepare("SELECT COUNT(*) as total FROM etalage WHERE id_secteur = ?");
$stmt->execute([$secteur_id]);
$result = $stmt->fetch();

if ($result['total'] > 0) {
    header('Location: gestion_secteurs.php?error=secteur_a_etalages');
    exit;
}

try {
    $secteur_obj = new Secteur();
    $result = $secteur_obj->delete($secteur_id);
    
    if ($result['success']) {
        header('Location: gestion_secteurs.php?success=secteur_supprime');
    } else {
        header('Location: gestion_secteurs.php?error=' . urlencode($result['error']));
    }
    exit;
    
} catch (Exception $e) {
    header('Location: gestion_secteurs.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>