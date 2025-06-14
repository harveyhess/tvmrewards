<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../src/controllers/TierController.php';

class CsvHandler {
    private $db;
    private $errors = [];
    private $processedRows = 0;
    private $skippedRows = 0;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function processCsv($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds limit');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open file');
        }

        // Read headers
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) {
            throw new Exception('Invalid CSV format');
        }

        // Normalize headers
        $headers = array_map('strtolower', array_map('trim', $headers));
        
        // Required fields
        $requiredFields = ['patientid', 'name', 'phonenumber', 'amountpaid'];
        $missingFields = array_diff($requiredFields, $headers);
        
        if (!empty($missingFields)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
        }

        // Get points rate
        $pointsRate = $this->getPointsRate();

        // Process rows
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $this->processRow($row, $headers, $pointsRate);
        }

        fclose($handle);

        return [
            'processed' => $this->processedRows,
            'skipped' => $this->skippedRows,
            'errors' => $this->errors
        ];
    }

    private function processRow($row, $headers, $pointsRate) {
        // Skip if row has different number of columns
        if (count($row) !== count($headers)) {
            $this->skippedRows++;
            $this->errors[] = "Row skipped: Invalid number of columns";
            return;
        }

        // Create associative array from row
        $data = array_combine($headers, $row);
        
        // Clean and validate data
        $data = array_map('trim', $data);
        
        // Validate required fields
        if (empty($data['patientid']) || empty($data['name']) || 
            empty($data['phonenumber']) || !is_numeric($data['amountpaid'])) {
            $this->skippedRows++;
            $this->errors[] = "Row skipped: Missing or invalid required fields";
            return;
        }

        // Clean phone number (remove non-numeric characters)
        $data['phonenumber'] = preg_replace('/[^0-9]/', '', $data['phonenumber']);
        
        // Calculate points
        $points = floor($data['amountpaid'] / $pointsRate);

        try {
            // Start transaction
            $this->db->getConnection()->beginTransaction();

            // Check if patient exists
            $patient = $this->db->fetch(
                "SELECT id FROM patients WHERE PatientID = ?",
                [$data['patientid']]
            );

            if (!$patient) {
                // Create new patient
                $patientId = $this->db->insert('patients', [
                    'PatientID' => $data['patientid'],
                    'name' => $data['name'],
                    'phone_number' => $data['phonenumber'],
                    'total_points' => $points,
                    'qr_token' => bin2hex(random_bytes(16))
                ]);
            } else {
                // Update existing patient
                $this->db->update(
                    'patients',
                    ['total_points' => $points],
                    'id = ?',
                    [$patient['id']]
                );
                $patientId = $patient['id'];
            }

            // Record transaction
            $this->db->insert('transactions', [
                'PatientID' => $patientId,
                'amount_paid' => $data['amountpaid'],
                'points_earned' => $points
            ]);

            // Update patient's tier
            $tierController = new TierController(false);
            $tierController->updatePatientTier($patientId);

            $this->db->getConnection()->commit();
            $this->processedRows++;

        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            $this->skippedRows++;
            $this->errors[] = "Error processing row: " . $e->getMessage();
        }
    }

    private function getPointsRate() {
        $settings = $this->db->fetch("SELECT points_rate FROM points_settings ORDER BY id DESC LIMIT 1");
        return $settings ? $settings['points_rate'] : DEFAULT_POINTS_RATE;
    }
} 