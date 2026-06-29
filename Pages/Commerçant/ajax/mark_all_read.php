<?php
session_start();
require_once __DIR__ . '/../../../Classes/Commercant.php';
require_once __DIR__ . '/../../../Classes/Database.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
$commercant = new Commercant();
if (!$commercant->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user = $commercant->getLoggedInUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Session expirée']);
    exit;
}

$id_commercant = $user->getIdCommercant();

// Marquer toutes les notifications comme lues
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    UPDATE notifications SET is_read = 1 
    WHERE id_commercant = ? AND is_read = 0
");
$result = $stmt->execute([$id_commercant]);

echo json_encode(['success' => true]);
exit;
?>