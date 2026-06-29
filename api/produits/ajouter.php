<?php
session_start();

// Définir la base URL pour les redirections
define('BASE_URL', '/projet'); // Ajustez selon votre structure

require_once __DIR__ . '/../../Classes/Produit.php';
require_once __DIR__ . '/../../Classes/Commercant.php';

// Vérifier la connexion
$commercant = new Commercant();
if (!$commercant->isLoggedIn()) {
    // Redirection vers la page de connexion
    header('Location: ' . BASE_URL . '/pages/Client/login.php');
    exit;
}

$user = $commercant->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/pages/Client/login.php?error=session_expired');
    exit;
}

$id_commercant = $user->getIdCommercant();

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirection vers la page d'ajout avec erreur
    header('Location: ' . BASE_URL . '/pages/Commercant/ajout_produit.php?error=method_not_allowed');
    exit;
}

// Récupérer et valider les données
$nom_produit = trim($_POST['nom_produit'] ?? '');
$description = trim($_POST['description'] ?? '');
$prix_unitaire = floatval($_POST['prix_unitaire'] ?? 0);
$quantite_stock = intval($_POST['quantite_stock'] ?? 0);
$unite = trim($_POST['unite'] ?? 'pièce');

// Validation
if (empty($nom_produit)) {
    // Redirection vers la page d'ajout avec erreur
    header('Location: ' . BASE_URL . '/pages/Commercant/ajout_produit.php?error=nom_requis');
    exit;
}

if ($prix_unitaire <= 0) {
    header('Location: ' . BASE_URL . '/pages/Commercant/ajout_produit.php?error=prix_invalide');
    exit;
}

// Créer le produit
$produit = new Produit();
$result = $produit->create([
    'nom_produit' => $nom_produit,
    'description' => $description,
    'prix_unitaire' => $prix_unitaire,
    'quantite_stock' => $quantite_stock,
    'unite' => $unite,
    'id_commercant' => $id_commercant
]);

// Redirection avec message
if ($result['success']) {
    // Redirection vers le dashboard avec succès
    header('Location: ' . BASE_URL . '/pages/Commercant/dashboard.php?success=produit_ajoute');
} else {
    // Redirection vers la page d'ajout avec erreur
    header('Location: ' . BASE_URL . '/pages/Commercant/ajout_produit.php?error=' . urlencode($result['error']));
}
exit;