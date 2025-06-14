<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/TierController.php';

class RewardController extends BaseController {
    protected $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
    }

    public function createReward($name, $description, $pointsCost) {
        try {
            return $this->db->insert('rewards', [
                'name' => $name,
                'description' => $description,
                'points_cost' => $pointsCost,
                'is_active' => true
            ]);
        } catch (Exception $e) {
            error_log("Error creating reward: " . $e->getMessage());
            return false;
        }
    }

    public function updateReward($id, $name, $description, $pointsCost, $isActive) {
        try {
            $this->db->execute(
                "UPDATE rewards SET name = ?, description = ?, points_cost = ?, is_active = ? WHERE id = ?",
                [$name, $description, $pointsCost, $isActive, $id]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error updating reward: " . $e->getMessage());
            return false;
        }
    }

    public function deleteReward($id) {
        try {
            $this->db->execute("DELETE FROM rewards WHERE id = ?", [$id]);
            return true;
        } catch (Exception $e) {
            error_log("Error deleting reward: " . $e->getMessage());
            return false;
        }
    }

    public function getAllRewards($activeOnly = true) {
        $sql = "SELECT * FROM rewards";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY points_cost ASC";
        return $this->db->fetchAll($sql);
    }

    public function getRewardById($id) {
        return $this->db->fetch("SELECT * FROM rewards WHERE id = ?", [$id]);
    }

    public function redeemReward($patientId, $rewardId) {
        try {
            $this->db->getConnection()->beginTransaction();

            // Get patient and reward details
            $patient = $this->db->fetch(
                "SELECT total_points FROM patients WHERE id = ?",
                [$patientId]
            );
            $reward = $this->db->fetch(
                "SELECT * FROM rewards WHERE id = ? AND is_active = 1",
                [$rewardId]
            );

            if (!$patient || !$reward) {
                throw new Exception("Invalid patient or reward");
            }

            if ($patient['total_points'] < $reward['points_cost']) {
                throw new Exception("Insufficient points");
            }

            // Create redemption record
            $redemptionId = $this->db->insert('redemptions', [
                'PatientID' => $patientId,
                'reward_id' => $rewardId,
                'points_spent' => $reward['points_cost'],
                'status' => 'pending'
            ]);

            // Update patient points
            $this->db->execute(
                "UPDATE patients SET total_points = total_points - ? WHERE id = ?",
                [$reward['points_cost'], $patientId]
            );

            // Add to points ledger
            $this->db->insert('points_ledger', [
                'PatientID' => $patientId,
                'points' => -$reward['points_cost'],
                'type' => 'redeem',
                'reference_id' => $redemptionId,
                'reference_type' => 'redemption',
                'description' => "Redeemed reward: " . $reward['name']
            ]);

            // Update patient's tier
            $tierController = new TierController(false);
            $tierController->updatePatientTier($patientId);

            $this->db->getConnection()->commit();
            return $redemptionId;
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Error redeeming reward: " . $e->getMessage());
            return false;
        }
    }

    public function completeRedemption($redemptionId) {
        try {
            $this->db->execute(
                "UPDATE redemptions SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$redemptionId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Error completing redemption: " . $e->getMessage());
            return false;
        }
    }

    public function cancelRedemption($redemptionId) {
        try {
            $this->db->getConnection()->beginTransaction();

            // Get redemption details
            $redemption = $this->db->fetch(
                "SELECT * FROM redemptions WHERE id = ? AND status = 'pending'",
                [$redemptionId]
            );

            if (!$redemption) {
                throw new Exception("Invalid redemption");
            }

            // Return points to patient
            $this->db->execute(
                "UPDATE patients SET total_points = total_points + ? WHERE id = ?",
                [$redemption['points_spent'], $redemption['PatientID']]
            );

            // Update redemption status
            $this->db->execute(
                "UPDATE redemptions SET status = 'cancelled' WHERE id = ?",
                [$redemptionId]
            );

            // Add to points ledger
            $this->db->insert('points_ledger', [
                'PatientID' => $redemption['PatientID'],
                'points' => $redemption['points_spent'],
                'type' => 'earn',
                'reference_id' => $redemptionId,
                'reference_type' => 'redemption',
                'description' => "Refund for cancelled redemption"
            ]);

            // Update patient's tier
            $tierController = new TierController(false);
            $tierController->updatePatientTier($redemption['PatientID']);

            $this->db->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Error cancelling redemption: " . $e->getMessage());
            return false;
        }
    }

    public function getPatientRedemptions($patientId) {
        return $this->db->fetchAll(
            "SELECT r.*, rw.name as reward_name, rw.description as reward_description 
            FROM redemptions r 
            JOIN rewards rw ON r.reward_id = rw.id 
            WHERE r.PatientID = ? 
            ORDER BY r.created_at DESC",
            [$patientId]
        );
    }
} 