<?php
require_once __DIR__ . '/Database.php';

class Location {
    private $db;
    private $id_location;
    private $montant_location;
    private $duree_location;
    private $date_debut;
    private $date_fin;
    private $id_commercant;
    private $id_etalage;
    private $created_at;
    
    // Données jointes
    private $commercant_nom;
    private $etalage_numero;
    private $etalage_localisation;
    
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
        $this->id_location = $data['id_location'] ?? null;
        $this->montant_location = $data['montant_location'] ?? null;
        $this->duree_location = $data['duree_location'] ?? null;
        $this->date_debut = $data['date_debut'] ?? null;
        $this->date_fin = $data['date_fin'] ?? null;
        $this->id_commercant = $data['id_commercant'] ?? null;
        $this->id_etalage = $data['id_etalage'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->commercant_nom = $data['commercant_nom'] ?? null;
        $this->etalage_numero = $data['etalage_numero'] ?? null;
        $this->etalage_localisation = $data['etalage_localisation'] ?? null;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer une location
     */
    public function create($data) {
        // Validation
        if (empty($data['montant_location']) || $data['montant_location'] <= 0) {
            return ['success' => false, 'error' => 'Le montant de la location est requis'];
        }
        if (empty($data['id_commercant'])) {
            return ['success' => false, 'error' => 'Le commerçant est requis'];
        }
        if (empty($data['id_etalage'])) {
            return ['success' => false, 'error' => 'L\'étalage est requis'];
        }
        
        try {
            // Vérifier si l'étalage est disponible
            $etalage = new Etalage();
            $etalageData = $etalage->getById($data['id_etalage']);
            
            if (!$etalageData || ($etalageData->getStatut() !== 'disponible' && $etalageData->getStatut() !== null)) {
                return ['success' => false, 'error' => 'Cet étalage n\'est pas disponible'];
            }
            
            // Calculer la date de fin
            $duree = $data['duree'] ?? 30;
            $date_debut = $data['date_debut'] ?? date('Y-m-d');
            $date_fin = date('Y-m-d', strtotime("+$duree days", strtotime($date_debut)));
            
            $sql = "INSERT INTO location (montant_location, duree_location, date_debut, date_fin, id_commercant, id_etalage) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['montant_location'],
                $duree . ' jours',
                $date_debut,
                $date_fin,
                $data['id_commercant'],
                $data['id_etalage']
            ]);
            
            $id = $this->db->lastInsertId();
            
            // Mettre à jour l'étalage
            $etalage->attribuer($data['id_etalage'], $data['id_commercant']);
            
            return [
                'success' => true,
                'message' => 'Location créée avec succès',
                'id_location' => $id
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer toutes les locations
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT l.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero,
                       e.localisation as etalage_localisation
                FROM location l
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                ORDER BY l.date_debut DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer une location par son ID
     */
    public function getById($id_location) {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero,
                       e.localisation as etalage_localisation
                FROM location l
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.id_location = ?
            ");
            $stmt->execute([$id_location]);
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
     * Récupérer les locations d'un commerçant
     */
    public function getByCommercant($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, 
                       e.numero as etalage_numero,
                       e.localisation as etalage_localisation
                FROM location l
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.id_commercant = ?
                ORDER BY l.date_debut DESC
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les locations actives
     */
    public function getActives() {
        try {
            $stmt = $this->db->query("
                SELECT l.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM location l
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.date_fin >= CURDATE()
                ORDER BY l.date_fin ASC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les locations expirées
     */
    public function getExpirees() {
        try {
            $stmt = $this->db->query("
                SELECT l.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM location l
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.date_fin < CURDATE()
                ORDER BY l.date_fin DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour une location
     */
    public function update($id_location, $data) {
        try {
            $sql = "UPDATE location SET 
                        montant_location = ?,
                        duree_location = ?,
                        date_debut = ?,
                        date_fin = ?
                    WHERE id_location = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['montant_location'],
                $data['duree_location'],
                $data['date_debut'],
                $data['date_fin'],
                $id_location
            ]);
            
            return ['success' => true, 'message' => 'Location mise à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer une location
     */
    public function delete($id_location) {
        try {
            // Récupérer l'étalage pour le libérer
            $stmt = $this->db->prepare("SELECT id_etalage FROM location WHERE id_location = ?");
            $stmt->execute([$id_location]);
            $location = $stmt->fetch();
            
            if ($location) {
                $etalage = new Etalage();
                $etalage->liberer($location['id_etalage']);
            }
            
            $stmt = $this->db->prepare("DELETE FROM location WHERE id_location = ?");
            $stmt->execute([$id_location]);
            
            return ['success' => true, 'message' => 'Location supprimée avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Vérifier si une location est active
     */
    public function isActive($id_location) {
        try {
            $stmt = $this->db->prepare("SELECT date_fin FROM location WHERE id_location = ?");
            $stmt->execute([$id_location]);
            $location = $stmt->fetch();
            
            if (!$location) return false;
            
            return strtotime($location['date_fin']) >= time();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Prolonger une location
     */
    public function prolonger($id_location, $jours) {
        try {
            $stmt = $this->db->prepare("SELECT date_fin FROM location WHERE id_location = ?");
            $stmt->execute([$id_location]);
            $location = $stmt->fetch();
            
            if (!$location) {
                return ['success' => false, 'error' => 'Location non trouvée'];
            }
            
            $new_date_fin = date('Y-m-d', strtotime("+$jours days", strtotime($location['date_fin'])));
            
            $stmt = $this->db->prepare("UPDATE location SET date_fin = ? WHERE id_location = ?");
            $stmt->execute([$new_date_fin, $id_location]);
            
            return ['success' => true, 'message' => 'Location prolongée avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtenir les statistiques des locations
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM location");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM location WHERE date_fin >= CURDATE()");
            $stats['actives'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM location WHERE date_fin < CURDATE()");
            $stats['expirees'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT SUM(montant_location) as total FROM location");
            $stats['revenus_totaux'] = $stmt->fetch()['total'] ?? 0;
            
            return $stats;
        } catch (PDOException $e) {
            return ['total' => 0, 'actives' => 0, 'expirees' => 0, 'revenus_totaux' => 0];
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdLocation() { return $this->id_location; }
    public function getMontantLocation() { return $this->montant_location; }
    public function getDureeLocation() { return $this->duree_location; }
    public function getDateDebut() { return $this->date_debut; }
    public function getDateFin() { return $this->date_fin; }
    public function getIdCommercant() { return $this->id_commercant; }
    public function getIdEtalage() { return $this->id_etalage; }
    public function getCommercantNom() { return $this->commercant_nom; }
    public function getEtalageNumero() { return $this->etalage_numero; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_location' => $this->id_location,
            'montant_location' => $this->montant_location,
            'duree_location' => $this->duree_location,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'id_commercant' => $this->id_commercant,
            'id_etalage' => $this->id_etalage,
            'commercant_nom' => $this->commercant_nom,
            'etalage_numero' => $this->etalage_numero,
            'created_at' => $this->created_at
        ];
    }
}