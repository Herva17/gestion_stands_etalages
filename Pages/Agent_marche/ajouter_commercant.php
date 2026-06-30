<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricule = $_POST['matricule'] ?? '';
    $nom_complet = $_POST['nom_complet'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($matricule) || empty($nom_complet) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            $commercant = new Commercant();
            $commercant->create($matricule, $nom_complet, $telephone, $email, $password);
            $success = 'Commerçant ajouté avec succès !';
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un commerçant</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-user-plus text-accent mr-2"></i>Ajouter un commerçant
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?= htmlspecialchars($success) ?>
                    <a href="dashboard.php" class="ml-4 text-green-700 font-semibold hover:text-green-900">Retour au tableau de bord</a>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Matricule <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="matricule" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                               placeholder="Ex: C001">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nom complet <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom_complet" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                               placeholder="Ex: Jean Dupont">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Téléphone
                        </label>
                        <input type="tel" name="telephone"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                               placeholder="Ex: 0123456789">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input type="email" name="email"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                               placeholder="Ex: jean@email.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Mot de passe <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                               placeholder="******">
                    </div>
                    
                    <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                        <i class="fas fa-save mr-2"></i> Ajouter
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>