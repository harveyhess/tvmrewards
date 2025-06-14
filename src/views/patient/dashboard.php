<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="patient-container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($patient['name']); ?></h1>
            <nav>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="transactions.php">Transactions</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main>
            <section class="patient-info">
                <div class="info-card">
                    <h3>Patient Information</h3>
                    <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['PatientID']); ?></p>
                    <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($patient['phone_number']); ?></p>
                    <p><strong>Total Points:</strong> <span class="points"><?php echo $patient['total_points']; ?></span></p>
                </div>
            </section>

            <section class="recent-transactions">
                <h2>Recent Transactions</h2>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount Paid</th>
                            <th>Points Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></td>
                                <td>KES <?php echo number_format($transaction['amount_paid'], 2); ?></td>
                                <td><?php echo $transaction['points_earned']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (count($transactions) >= 10): ?>
                    <div class="view-more">
                        <a href="transactions.php" class="button">View All Transactions</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../assets/js/patient.js"></script>
</body>
</html> 