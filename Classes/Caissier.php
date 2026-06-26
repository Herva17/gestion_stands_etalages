<?php
require_once __DIR__ . '/Database.php';

class Caissier {
    private $db;
    private $id_caissier;
    private $id_user;
    private $user_data = [];
    
    // Propriétés de l'utilisateur
    private $nom_complet;
    private $sexe;
    private $nationalite;
    private $date_naissance;
    private $adresse;
    private $matricule;
    private $nom_user;
    private $telephone;
    private $email;
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
    private function hydrate($user_data, $caissier_data = null) {
        $this->user_data = $user_data;
        $this->id_user = $user_data['id_user'];
        $this->nom_complet = $user_data['nom_complet'];
        $this->sexe = $user_data['sexe'];
        $this->nationalite = $user_data['nationalite'];
        $this->date_naissance = $user_data['date_naissance'];
        $this->adresse = $user_data['adresse'];
        $this->matricule = $user_data['matricule'];
        $this->nom_user = $user_data['nom_user'];
        $this->telephone = $user_data['telephone'];
        $this->email = $user_data['email'] ?? null;
        $this->created_at = $user_data['created_at'];
        
        if ($caissier_data) {
            $this->id_caissier = $caissier_data['id_caissier'];
        }
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un caissier
     */
    public function create($data) {
        // Validation
        $errors = $this->validateData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Vérifier si le nom d'utilisateur existe
            if ($this->usernameExists($data['nom_user'])) {
                return ['success' => false, 'errors' => ['nom_user' => 'Ce nom d\'utilisateur existe déjà']];
            }
            
            // Hasher le mot de passe
            $hashed_password = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur
            $sql = "INSERT INTO utilisateurs (
                nom_complet, sexe, nationalite, date_naissance, adresse,
                matricule, nom_user, mot_de_passe, telephone, email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'],
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $data['matricule'],
                $data['nom_user'],
                $hashed_password,
                $data['telephone'],
                $data['email'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Créer le compte caissier
            $sql = "INSERT INTO caissier (id_user) VALUES (?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            
            $caissier_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Caissier créé avec succès',
                'user_id' => $user_id,
                'caissier_id' => $caissier_id
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les caissiers
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT u.*, c.id_caissier
                FROM utilisateurs u
                INNER JOIN caissier c ON u.id_user = c.id_user
                ORDER BY u.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un caissier par son ID
     */
    public function getById($id_caissier) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, c.id_caissier
                FROM utilisateurs u
                INNER JOIN caissier c ON u.id_user = c.id_user
                WHERE c.id_caissier = ?
            ");
            $stmt->execute([$id_caissier]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data, $data);
                return $this;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer les paiements d'un caissier
     */
    public function getPaiements($id_caissier) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE p.id_caissier = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$id_caissier]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un caissier
     */
    public function update($id_caissier, $data) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE utilisateurs u
                    INNER JOIN caissier c ON u.id_user = c.id_user
                    SET u.nom_complet = ?,
                        u.sexe = ?,
                        u.nationalite = ?,
                        u.date_naissance = ?,
                        u.adresse = ?,
                        u.telephone = ?,
                        u.email = ?
                    WHERE c.id_caissier = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'] ?? '',
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $data['telephone'] ?? '',
                $data['email'] ?? null,
                $id_caissier
            ]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Caissier mis à jour avec succès'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un caissier
     */
    public function delete($id_caissier) {
        try {
            $stmt = $this->db->prepare("SELECT id_user FROM caissier WHERE id_caissier = ?");
            $stmt->execute([$id_caissier]);
            $caissier = $stmt->fetch();
            
            if (!$caissier) {
                return ['success' => false, 'error' => 'Caissier non trouvé'];
            }
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("DELETE FROM caissier WHERE id_caissier = ?");
            $stmt->execute([$id_caissier]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Caissier supprimé avec succès'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Valider les données
     */
    private function validateData($data) {
        $errors = [];
        
        if (empty($data['nom_complet'])) {
            $errors['nom_complet'] = 'Le nom complet est requis';
        }
        if (empty($data['nom_user'])) {
            $errors['nom_user'] = 'Le nom d\'utilisateur est requis';
        }
        if (empty($data['mot_de_passe']) || strlen($data['mot_de_passe']) < 6) {
            $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 6 caractères';
        }
        if (empty($data['telephone'])) {
            $errors['telephone'] = 'Le téléphone est requis';
        }
        
        return $errors;
    }
    
    /**
     * Vérifier si un nom d'utilisateur existe
     */
    private function usernameExists($nom_user) {
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE nom_user = ?");
        $stmt->execute([$nom_user]);
        return $stmt->fetch() !== false;
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdCaissier() { return $this->id_caissier; }
    public function getIdUser() { return $this->id_user; }
    public function getNomComplet() { return $this->nom_complet; }
    public function getNomUser() { return $this->nom_user; }
    public function getMatricule() { return $this->matricule; }
    public function getTelephone() { return $this->telephone; }
    public function getEmail() { return $this->email; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_caissier' => $this->id_caissier,
            'id_user' => $this->id_user,
            'nom_complet' => $this->nom_complet,
            'nom_user' => $this->nom_user,
            'matricule' => $this->matricule,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'created_at' => $this->created_at
        ];
    }
}