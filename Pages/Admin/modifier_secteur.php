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

// Récupérer le secteur
$stmt = $db->prepare("SELECT * FROM secteur WHERE id_secteur = ?");
$stmt->execute([$secteur_id]);
$secteur = $stmt->fetch();

if (!$secteur) {
    header('Location: gestion_secteurs.php?error=secteur_introuvable');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designation = $_POST['designation'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($designation)) {
        $error = 'Veuillez saisir la désignation du secteur.';
    } else {
        $secteur_obj = new Secteur();
        $result = $secteur_obj->update($secteur_id, [
            'designation' => $designation,
            'description' => $description
        ]);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = 'Modifier un secteur - Admin';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-primary { background: #1e3a5f; }
        .bg-accent { background: #f59e0b; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .input-focus:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">

<nav class="bg-primary text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <i class="fas fa-store text-accent text-2xl"></i>
                <span class="font-bold text-lg">Marché Virunga</span>
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Admin</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="gestion_secteurs.php" class="text-sm hover:text-accent transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
                <a href="../../login.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-primary mb-6">
            <i class="fas fa-edit text-accent mr-2"></i>Modifier le secteur
        </h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($success) ?>
                <a href="gestion_secteurs.php" class="ml-4 text-green-700 font-semibold hover:text-green-900">Voir la liste</a>
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Désignation <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="designation" required
                           value="<?= htmlspecialchars($secteur['designation']) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none"><?= htmlspecialchars($secteur['description'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i> Modifier le secteur
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>