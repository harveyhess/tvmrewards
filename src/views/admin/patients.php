<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../src/assets/css/style.css">
</head>
<body>
    <div class="admin-container">
        <header>
            <h1>Patients</h1>
            <nav>
                <a href="../../admin/dashboard.php">Dashboard</a>
                <a href="../../admin/patients.php" class="active">Patients</a>
                <a href="../../admin/upload.php">Upload CSV</a>
                <a href="../../admin/logout.php">Logout</a>
            </nav>
        </header>

        <main>
            <section class="patients-section">
                <div class="actions">
                    <a href="../../admin/export.php" class="button">Export to CSV</a>
                </div>

                <?php if (empty($patients)): ?>
                    <p>No patients found.</p>
                <?php else: ?>
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Phone Number</th>
                                <th>Total Points</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['PatientID']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone_number']); ?></td>
                                    <td>
                                        <span class="points"><?php echo $patient['total_points']; ?></span>
                                        <button class="edit-points" data-patient-id="<?php echo $patient['id']; ?>">Edit</button>
                                    </td>
                                    <td>
                                        <button class="qr-code" data-patient-id="<?php echo $patient['id']; ?>">QR Code</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="<?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Points Edit Modal -->
            <div id="pointsModal" class="modal">
                <div class="modal-content">
                    <h2>Edit Points</h2>
                    <form id="pointsForm">
                        <input type="hidden" id="patientId" name="PatientID">
                        <div class="form-group">
                            <label for="points">Points:</label>
                            <input type="number" id="points" name="points" required min="0">
                        </div>
                        <button type="submit" class="button">Save</button>
                        <button type="button" class="button cancel">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- QR Code Modal -->
            <div id="qrModal" class="modal">
                <div class="modal-content">
                    <h2>Patient QR Code</h2>
                    <div id="qrCode"></div>
                    <button type="button" class="button cancel">Close</button>
                </div>
            </div>
        </main>
    </div>

    <script src="../../src/assets/js/admin.js"></script>
</body>
</html> 