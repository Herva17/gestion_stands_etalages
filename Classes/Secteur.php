<?php
require_once __DIR__ . '/Database.php';

class Secteur {
    private $db;
    private $id_secteur;
    private $designation;
    private $created_at;
    
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
        $this->id_secteur = $data['id_secteur'] ?? null;
        $this->designation = $data['designation'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un secteur - Accepte soit une chaîne, soit un tableau
     */
    public function create($designation) {
        // Si c'est un tableau, extraire la désignation
        if (is_array($designation)) {
            $designation = $designation['designation'] ?? '';
        }
        
        if (empty($designation)) {
            return ['success' => false, 'error' => 'La désignation est requise'];
        }
        
        try {
            $stmt = $this->db->prepare("INSERT INTO secteur (designation) VALUES (?)");
            $stmt->execute([$designation]);
            
            $id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Secteur créé avec succès',
                'id_secteur' => $id
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les secteurs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT s.*, COUNT(e.id_etalage) as nb_etalages
                FROM secteur s
                LEFT JOIN etalage e ON s.id_secteur = e.id_secteur
                GROUP BY s.id_secteur
                ORDER BY s.designation
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un secteur par son ID
     */
    public function getById($id_secteur) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, COUNT(e.id_etalage) as nb_etalages
                FROM secteur s
                LEFT JOIN etalage e ON s.id_secteur = e.id_secteur
                WHERE s.id_secteur = ?
                GROUP BY s.id_secteur
            ");
            $stmt->execute([$id_secteur]);
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
     * Mettre à jour un secteur - Accepte soit une chaîne, soit un tableau
     */
    public function update($id_secteur, $designation) {
        // Si c'est un tableau, extraire la désignation
        if (is_array($designation)) {
            $designation = $designation['designation'] ?? '';
        }
        
        if (empty($designation)) {
            return ['success' => false, 'error' => 'La désignation est requise'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE secteur SET designation = ? WHERE id_secteur = ?");
            $stmt->execute([$designation, $id_secteur]);
            
            return ['success' => true, 'message' => 'Secteur mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un secteur
     */
    public function delete($id_secteur) {
        try {
            // Vérifier si le secteur a des étalages
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM etalage WHERE id_secteur = ?");
            $stmt->execute([$id_secteur]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                return ['success' => false, 'error' => 'Ce secteur contient des étalages. Supprimez-les d\'abord.'];
            }
            
            $stmt = $this->db->prepare("DELETE FROM secteur WHERE id_secteur = ?");
            $stmt->execute([$id_secteur]);
            
            return ['success' => true, 'message' => 'Secteur supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer les étalages d'un secteur
     */
    public function getEtalages($id_secteur) {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, 
                       u.nom_complet as commercant_nom
                FROM etalage e
                LEFT JOIN commercant c ON e.id_commercant = c.id_commercant
                LEFT JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE e.id_secteur = ?
                ORDER BY e.numero
            ");
            $stmt->execute([$id_secteur]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdSecteur() { return $this->id_secteur; }
    public function getDesignation() { return $this->designation; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_secteur' => $this->id_secteur,
            'designation' => $this->designation,
            'created_at' => $this->created_at
        ];
    }
}
?>