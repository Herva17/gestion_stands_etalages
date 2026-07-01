<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
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

$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    header('Location: gestion_utilisateurs.php?error=id_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Vérifier que l'utilisateur existe
$stmt = $db->prepare("SELECT id_user, nom_complet FROM utilisateurs WHERE id_user = ?");
$stmt->execute([$user_id]);
$utilisateur = $stmt->fetch();

if (!$utilisateur) {
    header('Location: gestion_utilisateurs.php?error=utilisateur_introuvable');
    exit;
}

// Ne pas supprimer l'admin connecté
if ($user_id == $_SESSION['user_id']) {
    header('Location: gestion_utilisateurs.php?error=impossible_supprimer_soi_meme');
    exit;
}

try {
    $db->beginTransaction();
    
    // Supprimer les rôles
    $stmt = $db->prepare("DELETE FROM commercant WHERE id_user = ?");
    $stmt->execute([$user_id]);
    $stmt = $db->prepare("DELETE FROM agent_marche WHERE id_user = ?");
    $stmt->execute([$user_id]);
    $stmt = $db->prepare("DELETE FROM admin WHERE id_user = ?");
    $stmt->execute([$user_id]);
    
    // Supprimer l'utilisateur
    $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id_user = ?");
    $stmt->execute([$user_id]);
    
    $db->commit();
    header('Location: gestion_utilisateurs.php?success=utilisateur_supprime');
    exit;
    
} catch (Exception $e) {
    $db->rollBack();
    header('Location: gestion_utilisateurs.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>