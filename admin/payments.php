<?php
require_once "../inc/db.php";
session_start();

if (!in_array($_SESSION['role'], ['admin','ceo'])) {
    die("Unauthorized");
}

$stmt = $pdo->query("
    SELECT p.*, u.name, u.email, c.title AS course_title
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN courses c ON p.course_id = c.id
    ORDER BY p.created_at DESC
");
$payments = $stmt->fetchAll();
?>

<?php
$pageTitle = 'User Payments - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="container-fluid px-4 mt-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">💳 Payment History</h3>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            ← Back
        </a>
    </div>

    <!-- SEARCH BAR -->
    <div class="mb-3">
        <input type="text" id="paymentSearch" class="form-control"
               placeholder="Search payments by name, email, course, status, reference...">
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="table-responsive">
                <table id="paymentTable" class="table table-bordered table-hover text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-muted py-4">
                                    No payment records found.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['email']) ?></td>
                            <td><?= htmlspecialchars($p['course_title']) ?></td>

                            <td class="fw-bold text-success">
                                ₦<?= number_format($p['amount']) ?>
                            </td>

                            <td>
                                <?php if ($p['status'] === 'success'): ?>
                                    <span class="badge bg-success">Successful</span>

                                <?php elseif ($p['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>

                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php endif; ?>
                            </td>

                            <td><code><?= htmlspecialchars($p['reference']) ?></code></td>

                            <td><?= date('M d, Y • h:i A', strtotime($p['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

<?php include 'inc/script.php'; ?>

<!-- SIMPLE SEARCH SCRIPT -->
<script>
document.getElementById('paymentSearch').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#paymentTable tbody tr');

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>
