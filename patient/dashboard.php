<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../src/controllers/PatientController.php';

// Configure session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
session_name(SESSION_NAME);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session data
error_log("Dashboard - Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_patient'])) {
    error_log("Dashboard - No user_id or not a patient in session. Redirecting to login.");
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['is_admin'])) {
    error_log("Dashboard - Admin trying to access patient area. Redirecting to admin.");
    header('Location: ../admin/dashboard.php');
    exit;
}

$controller = new PatientController();
$patient = $controller->getPatientDetails($_SESSION['user_id']);
$transactions = $controller->getPatientTransactions($_SESSION['user_id']);
$rewards = $controller->getAvailableRewards();
$redemptions = $controller->getPatientRedemptions($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../src/assets/css/style.css">
</head>
<body>
    <div class="patient-container">
        <header>
            <h1>Welcome, <?php echo htmlspecialchars($patient['name']); ?></h1>
            <nav>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="transactions.php">Transactions</a>
                <a href="rewards.php">Rewards</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main>
            <section class="points-summary">
                <div class="points-card">
                    <h3>Total Points</h3>
                    <p class="points"><?php echo $patient['total_points']; ?></p>
                </div>
                <div class="points-card">
                    <h3>Current Tier</h3>
                    <p class="tier"><?php echo htmlspecialchars($patient['tier_name'] ?? 'No Tier'); ?></p>
                </div>
                <div class="points-card">
                    <h3>Patient ID</h3>
                    <p><?php echo htmlspecialchars($patient['PatientID']); ?></p>
                </div>
            </section>

            <section class="available-rewards">
                <h2>Available Rewards</h2>
                <?php if (empty($rewards)): ?>
                    <p>No rewards available at the moment.</p>
                <?php else: ?>
                    <div class="rewards-grid">
                        <?php foreach ($rewards as $reward): ?>
                            <div class="reward-card">
                                <h3><?php echo htmlspecialchars($reward['name']); ?></h3>
                                <p class="description"><?php echo htmlspecialchars($reward['description']); ?></p>
                                <p class="points-cost"><?php echo $reward['points_cost']; ?> points</p>
                                <?php if ($patient['total_points'] >= $reward['points_cost']): ?>
                                    <button class="redeem-button" data-reward-id="<?php echo $reward['id']; ?>">
                                        Redeem
                                    </button>
                                <?php else: ?>
                                    <button class="redeem-button disabled" disabled>
                                        Not enough points
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="recent-transactions">
                <h2>Recent Transactions</h2>
                <?php if (empty($transactions)): ?>
                    <p>No transactions found.</p>
                <?php else: ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                <td>KES <?php echo number_format($transaction['amount_paid'], 2); ?></td>
                                <td><?php echo $transaction['points_earned']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="recent-redemptions">
                <h2>Recent Redemptions</h2>
                <?php if (empty($redemptions)): ?>
                    <p>No redemptions found.</p>
                <?php else: ?>
                    <table class="redemptions-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reward</th>
                                <th>Points</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($redemptions as $redemption): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($redemption['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($redemption['reward_name']); ?></td>
                                <td><?php echo $redemption['points_spent']; ?></td>
                                <td>
                                    <span class="status <?php echo $redemption['status']; ?>">
                                        <?php echo ucfirst($redemption['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        document.querySelectorAll('.redeem-button:not(.disabled)').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to redeem this reward?')) {
                    const rewardId = this.dataset.rewardId;
                    fetch('redeem_reward.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ reward_id: rewardId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error redeeming reward: ' + data.error);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 