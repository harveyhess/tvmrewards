<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../includes/CsvHandler.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/RewardController.php';
require_once __DIR__ . '/TierController.php';

class AdminController extends BaseController {
    protected $db;

    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
        $this->db = Database::getInstance();
    }

    public function dashboard() {
        $stats = $this->getDashboardStats();
        return $this->render('admin/dashboard', ['stats' => $stats]);
    }

    public function uploadCsv() {
        error_log("Starting CSV upload process");
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request method'];
            header('Location: upload.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error: " . ($_FILES['csv_file']['error'] ?? 'No file uploaded'));
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Please select a valid CSV file'];
            header('Location: upload.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $pointsRate = isset($_POST['points_rate']) ? (int)$_POST['points_rate'] : DEFAULT_POINTS_RATE;
        
        error_log("Processing file: " . $file['name'] . " with points rate: " . $pointsRate);

        // Validate file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            error_log("Invalid file type: " . $fileExt);
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Only CSV files are allowed'];
            header('Location: upload.php');
            exit;
        }

        // Process the CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            error_log("Could not open file: " . $file['tmp_name']);
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Could not open file'];
            header('Location: upload.php');
            exit;
        }

        // Skip header row
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        error_log("CSV Headers: " . print_r($headers, true));

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            error_log("Processing row: " . print_r($data, true));
            
            if (count($data) < 4) {
                $errorCount++;
                $errors[] = "Invalid data format in row";
                continue;
            }

            $patientId = trim($data[0]);
            $name = trim($data[1]);
            $phone = trim($data[2]);
            $amountPaid = (float)trim($data[3]);

            try {
                // Start transaction
                $this->db->getPdo()->beginTransaction();

                // Insert or update patient
                $patient = $this->db->fetch(
                    "SELECT id FROM patients WHERE PatientID = ?",
                    [$patientId]
                );

                if ($patient) {
                    $patientId = $patient['id'];
                    error_log("Updating existing patient: " . $patientId);
                } else {
                    error_log("Creating new patient: " . $patientId);
                    $this->db->execute(
                        "INSERT INTO patients (PatientID, name, phone_number) VALUES (?, ?, ?)",
                        [$patientId, $name, $phone]
                    );
                    $patientId = $this->db->lastInsertId();
                }

                // Calculate points based on amount paid
                $points = floor($amountPaid / $pointsRate);
                error_log("Calculated points: " . $points . " for amount: " . $amountPaid);

                // Add points to patient
                $this->updatePoints($patientId, $points);

                $this->db->getPdo()->commit();
                $successCount++;
            } catch (Exception $e) {
                $this->db->getPdo()->rollBack();
                $errorCount++;
                $errors[] = "Error processing patient ID: $patientId - " . $e->getMessage();
                error_log("Error processing patient: " . $e->getMessage());
            }
        }

        fclose($handle);

        error_log("Upload complete. Success: $successCount, Errors: $errorCount");
        
        $_SESSION['flash_message'] = [
            'type' => $errorCount === 0 ? 'success' : 'warning',
            'message' => "Upload complete. Successfully processed $successCount patients. Failed: $errorCount" . 
                        ($errors ? "<br>Errors: " . implode("<br>", $errors) : "")
        ];
        header('Location: patients.php');
        exit;
    }

    public function patients() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $patients = $this->getPatients($page, $limit);
        $total = $this->getPatientCount();
        $totalPages = ceil($total / $limit);

        return $this->render('admin/patients', [
            'patients' => $patients,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function handleUpdatePoints() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonResponse(['error' => 'Invalid request method'], 400);
        }

        $patientId = $this->sanitizeInput($_POST['PatientID']);
        $points = (int)$_POST['points'];

        try {
            $this->updatePoints($patientId, $points);
            return $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function updatePointsRate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonResponse(['error' => 'Invalid request method'], 400);
        }

        $rate = (int)$_POST['rate'];
        $adminId = $_SESSION['user_id'];

        try {
            $this->db->insert('points_settings', [
                'points_rate' => $rate,
                'updated_by' => $adminId
            ]);

            return $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function exportPatients() {
        $patients = $this->db->fetchAll(
            "SELECT PatientID, name, phone_number, total_points FROM patients ORDER BY name"
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="patients.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Patient ID', 'Name', 'Phone Number', 'Total Points']);

        foreach ($patients as $patient) {
            fputcsv($output, $patient);
        }

        fclose($output);
        exit;
    }

    public function generateQrCode($patientId) {
        $patient = $this->getPatientDetails($patientId);
        if (!$patient) {
            return false;
        }

        // Generate a unique token if not exists
        if (empty($patient['qr_token'])) {
            $token = bin2hex(random_bytes(32)); // Using 32 bytes for better security
            $this->db->execute(
                "UPDATE patients SET qr_token = ? WHERE id = ?",
                [$token, $patientId]
            );
            $patient['qr_token'] = $token;
        }

        // Create login data
        $loginData = [
            'name' => $patient['name'],
            'phone' => $patient['phone_number'],
            'token' => $patient['qr_token']
        ];

        // Return both token and patient info
        return [
            'token' => $patient['qr_token'],
            'PatientID' => $patient['PatientID'],
            'name' => $patient['name'],
            'phone' => $patient['phone_number'],
            'login_data' => base64_encode(json_encode($loginData))
        ];
    }

    public function getDashboardStats() {
        $stats = [
            'total_patients' => 0,
            'total_points' => 0,
            'total_transactions' => 0
        ];

        // Get total patients
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM patients");
        $stats['total_patients'] = $result['count'];

        // Get total points
        $result = $this->db->fetch("SELECT SUM(total_points) as total FROM patients");
        $stats['total_points'] = $result['total'] ?? 0;

        // Get total transactions
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM transactions");
        $stats['total_transactions'] = $result['count'];

        // Ensure points are being updated when transactions are processed
        $this->updatePointsFromTransactions();

        return $stats;
    }

    private function updatePointsFromTransactions() {
        $transactions = $this->db->fetchAll("SELECT * FROM transactions");
        foreach ($transactions as $transaction) {
            $this->updatePoints($transaction['PatientID'], $transaction['points_earned']);
        }
    }

    public function getPatients($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        return $this->db->fetchAll(
            "SELECT * FROM patients ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    }

    public function getPatientCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM patients");
        return $result['count'];
    }

    public function getPatientDetails($id) {
        return $this->db->fetch(
            "SELECT * FROM patients WHERE id = ?",
            [$id]
        );
    }

    public function updatePoints($patientId, $points) {
        try {
            $this->db->getConnection()->beginTransaction();

        // First update the patient's total points
        $this->db->execute(
            "UPDATE patients SET total_points = total_points + ? WHERE id = ?",
            [$points, $patientId]
        );

            // If points are being added, create a transaction record and points ledger entry
        if ($points > 0) {
                $transactionId = $this->db->insert('transactions', [
                    'PatientID' => $patientId,
                    'amount_paid' => $points * 100, // Assuming 1 point per 100 KES
                    'points_earned' => $points
                ]);

                // Add to points ledger
                $this->db->insert('points_ledger', [
                    'PatientID' => $patientId,
                    'points' => $points,
                    'type' => 'earn',
                    'reference_id' => $transactionId,
                    'reference_type' => 'transaction',
                    'description' => "Points earned from transaction"
                ]);
            }

            // Update patient's tier
            $tierController = new TierController();
            $tierController->updatePatientTier($patientId);

            $this->db->getConnection()->commit();
            return true;
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Error updating points: " . $e->getMessage());
            return false;
        }
    }

    public function getTiers() {
        $tierController = new TierController();
        return $tierController->getAllTiers();
    }

    public function getRewards() {
        try {
            $rewardController = new RewardController();
            return $rewardController->getAllRewards(false); // Get all rewards, including inactive ones
        } catch (Exception $e) {
            error_log("Error getting rewards: " . $e->getMessage());
            return [];
        }
    }

    public function getPointsLedger($patientId) {
        return $this->db->fetchAll(
            "SELECT * FROM points_ledger WHERE PatientID = ? ORDER BY created_at DESC",
            [$patientId]
        );
    }

    public function getRedemptions($patientId) {
        $rewardController = new RewardController();
        return $rewardController->getPatientRedemptions($patientId);
    }
} 