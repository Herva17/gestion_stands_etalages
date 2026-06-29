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

// Récupérer l'ID de la notification
$id_notification = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id_notification <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

// Marquer la notification comme lue
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    UPDATE notifications SET is_read = 1 
    WHERE id_notification = ? AND id_commercant = ?
");
$result = $stmt->execute([$id_notification, $id_commercant]);

if ($result && $stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Notification non trouvée']);
}
exit;
?>