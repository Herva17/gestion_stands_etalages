<?php
require_once __DIR__ . '/Database.php';

class Produit {
    private $db;
    private $id_produit;
    private $nom_produit;
    private $description;
    private $prix_unitaire;
    private $quantite_stock;
    private $unite;
    private $id_commercant;
    private $created_at;
    
    // Données jointes
    private $commercant_nom;
    private $commercant_matricule;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Hydrate l'objet
     */
    private function hydrate($data) {
        $this->id_produit = $data['id_produit'] ?? null;
        $this->nom_produit = $data['nom_produit'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->prix_unitaire = $data['prix_unitaire'] ?? null;
        $this->quantite_stock = $data['quantite_stock'] ?? 0;
        $this->unite = $data['unite'] ?? 'pièce';
        $this->id_commercant = $data['id_commercant'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->commercant_nom = $data['commercant_nom'] ?? null;
        $this->commercant_matricule = $data['commercant_matricule'] ?? null;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un produit
     */
    public function create($data) {
        // Validation
        if (empty($data['nom_produit'])) {
            return ['success' => false, 'error' => 'Le nom du produit est requis'];
        }
        if (empty($data['prix_unitaire']) || $data['prix_unitaire'] <= 0) {
            return ['success' => false, 'error' => 'Le prix unitaire est requis'];
        }
        if (empty($data['id_commercant'])) {
            return ['success' => false, 'error' => 'Le commerçant est requis'];
        }
        
        try {
            $sql = "INSERT INTO produit (nom_produit, description, prix_unitaire, quantite_stock, unite, id_commercant) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_produit'],
                $data['description'] ?? '',
                $data['prix_unitaire'],
                $data['quantite_stock'] ?? 0,
                $data['unite'] ?? 'pièce',
                $data['id_commercant']
            ]);
            
            $id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Produit créé avec succès',
                'id_produit' => $id
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les produits
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       u.matricule as commercant_matricule
                FROM produit p
                INNER JOIN commercant c ON p.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                ORDER BY p.nom_produit
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un produit par son ID
     */
    public function getById($id_produit) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       u.matricule as commercant_matricule
                FROM produit p
                INNER JOIN commercant c ON p.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE p.id_produit = ?
            ");
            $stmt->execute([$id_produit]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data);
                return $this;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer les produits d'un commerçant
     */
    public function getByCommercant($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*
                FROM produit p
                WHERE p.id_commercant = ?
                ORDER BY p.nom_produit
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les produits en stock
     */
    public function getEnStock() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commercant_nom
                FROM produit p
                INNER JOIN commercant c ON p.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE p.quantite_stock > 0
                ORDER BY p.nom_produit
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les produits en rupture de stock
     */
    public function getRuptureStock() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commercant_nom
                FROM produit p
                INNER JOIN commercant c ON p.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE p.quantite_stock = 0
                ORDER BY p.nom_produit
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un produit
     */
    public function update($id_produit, $data) {
        try {
            $sql = "UPDATE produit SET 
                        nom_produit = ?,
                        description = ?,
                        prix_unitaire = ?,
                        quantite_stock = ?,
                        unite = ?
                    WHERE id_produit = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_produit'],
                $data['description'] ?? '',
                $data['prix_unitaire'],
                $data['quantite_stock'] ?? 0,
                $data['unite'] ?? 'pièce',
                $id_produit
            ]);
            
            return ['success' => true, 'message' => 'Produit mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un produit
     */
    public function delete($id_produit) {
        try {
            $stmt = $this->db->prepare("DELETE FROM produit WHERE id_produit = ?");
            $stmt->execute([$id_produit]);
            
            return ['success' => true, 'message' => 'Produit supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour le stock
     */
    public function updateStock($id_produit, $quantite) {
        try {
            $stmt = $this->db->prepare("UPDATE produit SET quantite_stock = ? WHERE id_produit = ?");
            $stmt->execute([$quantite, $id_produit]);
            
            return ['success' => true, 'message' => 'Stock mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter au stock
     */
    public function addStock($id_produit, $quantite) {
        try {
            $stmt = $this->db->prepare("UPDATE produit SET quantite_stock = quantite_stock + ? WHERE id_produit = ?");
            $stmt->execute([$quantite, $id_produit]);
            
            return ['success' => true, 'message' => 'Stock augmenté avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Retirer du stock
     */
    public function removeStock($id_produit, $quantite) {
        try {
            $stmt = $this->db->prepare("UPDATE produit SET quantite_stock = quantite_stock - ? WHERE id_produit = ? AND quantite_stock >= ?");
            $stmt->execute([$quantite, $id_produit, $quantite]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Stock retiré avec succès'];
            } else {
                return ['success' => false, 'error' => 'Stock insuffisant ou produit non trouvé'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtenir les statistiques des produits
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM produit");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM produit WHERE quantite_stock > 0");
            $stats['en_stock'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM produit WHERE quantite_stock = 0");
            $stats['rupture'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT SUM(quantite_stock) as total FROM produit");
            $stats['total_stock'] = $stmt->fetch()['total'] ?? 0;
            
            return $stats;
        } catch (PDOException $e) {
            return ['total' => 0, 'en_stock' => 0, 'rupture' => 0, 'total_stock' => 0];
        }
    }
    
    /**
     * Rechercher des produits
     */
    public function search($terme) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom
                FROM produit p
                INNER JOIN commercant c ON p.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE p.nom_produit LIKE ? OR p.description LIKE ?
                ORDER BY p.nom_produit
            ");
            $searchTerm = '%' . $terme . '%';
            $stmt->execute([$searchTerm, $searchTerm]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdProduit() { return $this->id_produit; }
    public function getNomProduit() { return $this->nom_produit; }
    public function getDescription() { return $this->description; }
    public function getPrixUnitaire() { return $this->prix_unitaire; }
    public function getQuantiteStock() { return $this->quantite_stock; }
    public function getUnite() { return $this->unite; }
    public function getIdCommercant() { return $this->id_commercant; }
    public function getCommercantNom() { return $this->commercant_nom; }
    public function getCommercantMatricule() { return $this->commercant_matricule; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_produit' => $this->id_produit,
            'nom_produit' => $this->nom_produit,
            'description' => $this->description,
            'prix_unitaire' => $this->prix_unitaire,
            'quantite_stock' => $this->quantite_stock,
            'unite' => $this->unite,
            'id_commercant' => $this->id_commercant,
            'commercant_nom' => $this->commercant_nom,
            'commercant_matricule' => $this->commercant_matricule,
            'created_at' => $this->created_at
        ];
    }
}