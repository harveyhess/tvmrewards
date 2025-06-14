<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../src/controllers/TierController.php';
require_once __DIR__ . '/../src/controllers/CsvController.php';

class TransactionProcessor {
    private $db;
    private $tierController;
    private $csvController;
    private $processedFiles = [];
    private $errors = [];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->tierController = new TierController(false);
        $this->csvController = new CsvController();
    }

    public function processTransactions() {
        try {
            // Fetch and process remote CSVs
            $this->processRemoteCsvs();
            
            // Process local CSV files
            $csvFiles = glob(__DIR__ . '/../uploads/*.csv');
            foreach ($csvFiles as $file) {
                $this->processFile($file);
            }

            // Log results
            $this->logResults();

            return [
                'success' => true,
                'processed_files' => $this->processedFiles,
                'errors' => $this->errors
            ];
        } catch (Exception $e) {
            error_log("Error processing transactions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function processRemoteCsvs() {
        $links = $this->db->fetchAll(
            "SELECT * FROM transaction_csv_links WHERE status = 'active'"
        );

        foreach ($links as $link) {
            try {
                $fileName = $this->csvController->fetchRemoteCsv($link['url']);
                $this->processFile(__DIR__ . '/../uploads/' . $fileName);
            } catch (Exception $e) {
                $this->errors[] = "Error processing remote CSV {$link['url']}: " . $e->getMessage();
            }
        }
    }

    private function processFile($file) {
        $fileName = basename($file);
        
        // Get or create sync record
        $syncRecord = $this->db->fetch(
            "SELECT * FROM transaction_sync WHERE file_name = ?",
            [$fileName]
        );

        if (!$syncRecord) {
            $syncId = $this->db->insert('transaction_sync', [
                'file_name' => $fileName,
                'status' => 'pending'
            ]);
            $syncRecord = $this->db->fetch(
                "SELECT * FROM transaction_sync WHERE id = ?",
                [$syncId]
            );
        }

        // Skip if file is already processed
        if ($syncRecord['status'] === 'completed') {
            return;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new Exception("Could not open file: $fileName");
        }

        // Read headers
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) {
            throw new Exception("Invalid CSV format in file: $fileName");
        }

        // Normalize headers
        $headers = array_map('strtolower', array_map('trim', $headers));
        
        // Map of required fields to their possible variations
        $fieldMappings = [
            'patientid' => ['patientid', 'PatientID', 'patient id'],
            'amountpaid' => ['amountpaid', 'amount_paid', 'amount paid'],
            'transactiondate' => ['dateofvisit', 'date_of_visit', 'date of visit', 'transactiondate', 'transaction_date', 'transaction date']
        ];
        
        // Check for required fields
        $missingFields = [];
        foreach ($fieldMappings as $requiredField => $variations) {
            $found = false;
            foreach ($variations as $variation) {
                if (in_array($variation, $headers)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingFields[] = $requiredField;
            }
        }
        
        if (!empty($missingFields)) {
            throw new Exception("Missing required fields in file $fileName: " . implode(', ', $missingFields));
        }

        // Get points rate
        $pointsRate = $this->getPointsRate();

        // Skip already processed lines
        for ($i = 0; $i < $syncRecord['last_processed_line']; $i++) {
            fgetcsv($handle, 0, ',', '"', '\\');
        }

        // Process new lines
        $lineNumber = $syncRecord['last_processed_line'];
        $processedCount = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            // Check if the number of keys and values match
            if (count($headers) !== count($row)) {
                error_log("Error: Number of keys and values do not match in row: " . implode(',', $row));
                continue; // Skip this row
            }

            $transaction = array_combine($headers, $row);
            
            try {
                $this->processTransaction($transaction, $pointsRate);
                $processedCount++;
            } catch (Exception $e) {
                $this->errors[] = "Error processing line $lineNumber in $fileName: " . $e->getMessage();
            }
        }

        fclose($handle);

        // Update sync record
        $this->db->execute(
            "UPDATE transaction_sync SET 
                last_processed_line = ?,
                last_sync_time = CURRENT_TIMESTAMP,
                status = 'completed'
            WHERE id = ?",
            [$lineNumber, $syncRecord['id']]
        );

        $this->processedFiles[] = [
            'file' => $fileName,
            'processed_lines' => $processedCount
        ];
    }

    private function processTransaction($data, $pointsRate) {
        // Clean and validate data
        $patientId = trim($data['patientid']);
        $amountPaid = (float)$data['amountpaid'];
        
        // Get transaction date from any of the possible field names
        $transactionDate = null;
        $dateFields = ['dateofvisit', 'date_of_visit', 'date of visit', 'transactiondate', 'transaction_date', 'transaction date'];
        foreach ($dateFields as $field) {
            if (isset($data[$field])) {
                $transactionDate = trim($data[$field]);
                break;
            }
        }

        if (empty($patientId) || $amountPaid <= 0 || empty($transactionDate)) {
            throw new Exception("Invalid transaction data");
        }

        // Get patient
        $patient = $this->db->fetch(
            "SELECT id FROM patients WHERE PatientID = ?",
            [$patientId]
        );

        if (!$patient) {
            // Create new patient if not exists
            $patientId = $this->db->insert('patients', [
                'PatientID' => $patientId,
                'name' => $data['name'] ?? 'Unknown',
                'phone_number' => $data['phonenumber'] ?? '',
                'total_points' => 0,
                'qr_token' => bin2hex(random_bytes(16))
            ]);
            $patient = ['id' => $patientId];
        }

        // Calculate points
        $points = floor($amountPaid / $pointsRate);

        // Start transaction
        $this->db->getConnection()->beginTransaction();

        try {
            // Record transaction
            $transactionId = $this->db->insert('transactions', [
                'PatientID' => $patient['id'],
                'amount_paid' => $amountPaid,
                'points_earned' => $points,
                'transaction_date' => $transactionDate
            ]);

            // Update patient points
            $this->db->execute(
                "UPDATE patients SET total_points = total_points + ? WHERE id = ?",
                [$points, $patient['id']]
            );

            // Add to points ledger
            $this->db->insert('points_ledger', [
                'PatientID' => $patient['id'],
                'points' => $points,
                'type' => 'earn',
                'reference_id' => $transactionId,
                'reference_type' => 'transaction',
                'description' => "Points earned from transaction"
            ]);

            // Update patient's tier
            $this->tierController->updatePatientTier($patient['id']);

            $this->db->getConnection()->commit();
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            throw $e;
        }

        // Check if transaction has already been processed
        $stmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM transactions WHERE PatientID = ? AND transaction_id = ?');
        $stmt->execute([$patient['id'], $transactionId]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            // Skip if already processed
            error_log("Transaction already processed: Patient ID: " . $patient['id'] . ", Transaction ID: " . $transactionId);
            return;
        }

        // Aggregate points for the user
        $stmt = $this->db->getConnection()->prepare('SELECT SUM(points) FROM transactions WHERE PatientID = ?');
        $stmt->execute([$patient['id']]);
        $total_points = $stmt->fetchColumn();

        // Update the patient's total points
        $stmt = $this->db->getConnection()->prepare('UPDATE patients SET total_points = ? WHERE id = ?');
        $stmt->execute([$total_points, $patient['id']]);

        // Log any errors
        if ($stmt->errorCode() != '00000') {
            error_log("Error processing transaction: " . $stmt->errorInfo()[2]);
            return;
        }
    }

    private function getPointsRate() {
        $settings = $this->db->fetch(
            "SELECT points_rate FROM points_settings ORDER BY updated_at DESC LIMIT 1"
        );
        return $settings ? $settings['points_rate'] : DEFAULT_POINTS_RATE;
    }

    private function logResults() {
        $logFile = __DIR__ . '/../logs/transaction_processing.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] Processed " . count($this->processedFiles) . " files\n";
        
        foreach ($this->processedFiles as $file) {
            $logMessage .= "- {$file['file']}: {$file['processed_lines']} lines processed\n";
        }
        
        if (!empty($this->errors)) {
            $logMessage .= "Errors:\n";
            foreach ($this->errors as $error) {
                $logMessage .= "- $error\n";
            }
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

// Run the processor
$processor = new TransactionProcessor();
$result = $processor->processTransactions();

if (!$result['success']) {
    error_log("Transaction processing failed: " . $result['error']);
    exit(1);
}

exit(0); 