<?php
session_start();
require_once "../inc/db.php";

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'ceo') {
    die("Access denied.");
}

$stmt = $pdo->query("
  SELECT c.*, u.name AS instructor_name 
  FROM courses c 
  JOIN users u ON c.instructor_id = u.id 
  ORDER BY c.created_at DESC
");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'Manage Courses - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<main class="container">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3"><i data-lucide="book-open" class="me-2"></i> Manage Courses</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i data-lucide="arrow-left" class="me-1"></i> Back to Dashboard</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Instructor</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Price Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['title']) ?></td>
                                    <td><?= htmlspecialchars($c['instructor_name']) ?></td>
                                    <td><?= ucfirst($c['type']) ?></td>
                                    <td>₦<?= number_format($c['price'], 2) ?></td>
                                    <td>
                                        <span class="badge <?= $c['price_status'] === 'pending' ? 'bg-warning' : ($c['price_status'] === 'approved' ? 'bg-success' : 'bg-danger') ?>">
                                            <?= ucfirst($c['price_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($c['type'] === 'paid' && $c['price_status'] === 'pending'): ?>
                                            <a href="approve_price.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-success"><i data-lucide="check-circle" class="me-1"></i> Approve Price</a>
                                        <?php else: ?>
                                            <a href="edit_course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary"><i data-lucide="edit-3" class="me-1"></i> Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No courses found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'inc/script.php'; ?>
