<?php
require_once __DIR__ . '/Database.php';

class Paiement {
    private $db;
    private $id_paiement;
    private $date_paiement;
    private $montant;
    private $mode_paiement;
    private $periode;
    private $id_location;
    private $id_caissier;
    private $created_at;
    
    // Données jointes
    private $commercant_nom;
    private $etalage_numero;
    private $caissier_nom;
    
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
        $this->id_paiement = $data['id_paiement'] ?? null;
        $this->date_paiement = $data['date_paiement'] ?? null;
        $this->montant = $data['montant'] ?? null;
        $this->mode_paiement = $data['mode_paiement'] ?? null;
        $this->periode = $data['periode'] ?? null;
        $this->id_location = $data['id_location'] ?? null;
        $this->id_caissier = $data['id_caissier'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->commercant_nom = $data['commercant_nom'] ?? null;
        $this->etalage_numero = $data['etalage_numero'] ?? null;
        $this->caissier_nom = $data['caissier_nom'] ?? null;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un paiement
     */
    public function create($data) {
        // Validation
        if (empty($data['montant']) || $data['montant'] <= 0) {
            return ['success' => false, 'error' => 'Le montant est requis'];
        }
        if (empty($data['id_location'])) {
            return ['success' => false, 'error' => 'La location est requise'];
        }
        if (empty($data['mode_paiement'])) {
            return ['success' => false, 'error' => 'Le mode de paiement est requis'];
        }
        
        try {
            $sql = "INSERT INTO paiement (date_paiement, montant, mode_paiement, periode, id_location, id_caissier) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['date_paiement'] ?? date('Y-m-d'),
                $data['montant'],
                $data['mode_paiement'],
                $data['periode'] ?? date('F Y'),
                $data['id_location'],
                $data['id_caissier'] ?? null
            ]);
            
            $id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'id_paiement' => $id
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les paiements
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero,
                       c.nom_user as caissier_nom
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant cm ON l.id_commercant = cm.id_commercant
                INNER JOIN utilisateurs u ON cm.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                LEFT JOIN caissier ca ON p.id_caissier = ca.id_caissier
                LEFT JOIN utilisateurs c ON ca.id_user = c.id_user
                ORDER BY p.date_paiement DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un paiement par son ID
     */
    public function getById($id_paiement) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero,
                       c.nom_user as caissier_nom
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant cm ON l.id_commercant = cm.id_commercant
                INNER JOIN utilisateurs u ON cm.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                LEFT JOIN caissier ca ON p.id_caissier = ca.id_caissier
                LEFT JOIN utilisateurs c ON ca.id_user = c.id_user
                WHERE p.id_paiement = ?
            ");
            $stmt->execute([$id_paiement]);
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
     * Récupérer les paiements d'une location
     */
    public function getByLocation($id_location) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as caissier_nom
                FROM paiement p
                LEFT JOIN caissier ca ON p.id_caissier = ca.id_caissier
                LEFT JOIN utilisateurs u ON ca.id_user = u.id_user
                WHERE p.id_location = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$id_location]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements d'un commerçant
     */
    public function getByCommercant($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.id_commercant = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements par période
     */
    public function getByPeriode($mois, $annee) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant cm ON l.id_commercant = cm.id_commercant
                INNER JOIN utilisateurs u ON cm.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE MONTH(p.date_paiement) = ? AND YEAR(p.date_paiement) = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$mois, $annee]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un paiement
     */
    public function update($id_paiement, $data) {
        try {
            $sql = "UPDATE paiement SET 
                        date_paiement = ?,
                        montant = ?,
                        mode_paiement = ?,
                        periode = ?
                    WHERE id_paiement = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['date_paiement'],
                $data['montant'],
                $data['mode_paiement'],
                $data['periode'],
                $id_paiement
            ]);
            
            return ['success' => true, 'message' => 'Paiement mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un paiement
     */
    public function delete($id_paiement) {
        try {
            $stmt = $this->db->prepare("DELETE FROM paiement WHERE id_paiement = ?");
            $stmt->execute([$id_paiement]);
            
            return ['success' => true, 'message' => 'Paiement supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtenir les statistiques des paiements
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM paiement");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT SUM(montant) as total FROM paiement");
            $stats['total_montant'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $this->db->query("
                SELECT SUM(montant) as total 
                FROM paiement 
                WHERE MONTH(date_paiement) = MONTH(CURDATE()) 
                AND YEAR(date_paiement) = YEAR(CURDATE())
            ");
            $stats['mois_montant'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $this->db->query("
                SELECT mode_paiement, COUNT(*) as count, SUM(montant) as total
                FROM paiement
                GROUP BY mode_paiement
            ");
            $stats['par_mode'] = $stmt->fetchAll();
            
            return $stats;
        } catch (PDOException $e) {
            return ['total' => 0, 'total_montant' => 0, 'mois_montant' => 0, 'par_mode' => []];
        }
    }
    
    /**
     * Vérifier si un paiement existe pour une location et une période
     */
    public function existsForLocation($id_location, $periode) {
        try {
            $stmt = $this->db->prepare("
                SELECT id_paiement FROM paiement 
                WHERE id_location = ? AND periode = ?
            ");
            $stmt->execute([$id_location, $periode]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdPaiement() { return $this->id_paiement; }
    public function getDatePaiement() { return $this->date_paiement; }
    public function getMontant() { return $this->montant; }
    public function getModePaiement() { return $this->mode_paiement; }
    public function getPeriode() { return $this->periode; }
    public function getIdLocation() { return $this->id_location; }
    public function getIdCaissier() { return $this->id_caissier; }
    public function getCommercantNom() { return $this->commercant_nom; }
    public function getEtalageNumero() { return $this->etalage_numero; }
    public function getCaissierNom() { return $this->caissier_nom; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_paiement' => $this->id_paiement,
            'date_paiement' => $this->date_paiement,
            'montant' => $this->montant,
            'mode_paiement' => $this->mode_paiement,
            'periode' => $this->periode,
            'id_location' => $this->id_location,
            'id_caissier' => $this->id_caissier,
            'commercant_nom' => $this->commercant_nom,
            'etalage_numero' => $this->etalage_numero,
            'caissier_nom' => $this->caissier_nom,
            'created_at' => $this->created_at
        ];
    }
}