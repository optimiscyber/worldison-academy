<?php
session_start();
require_once "inc/db.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','ceo'])) {
    die("Access denied.");
}

// Handle Approvals
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $stmt = $pdo->prepare("UPDATE users SET status='approved' WHERE id=?");
    $stmt->execute([$id]);
    echo "<script>alert('User approved successfully!'); window.location='manage_users.php';</script>";
    exit;
}

// Handle Deactivate
if (isset($_GET['deactivate'])) {
    $id = intval($_GET['deactivate']);

    // Prevent admin from deactivating CEO
    $user = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $user->execute([$id]);
    $u = $user->fetch(PDO::FETCH_ASSOC);

    if ($u && $u['role'] === 'ceo' && $_SESSION['role'] !== 'ceo') {
        echo "<script>alert('Admins cannot deactivate CEO!'); window.location='manage_users.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET status='rejected' WHERE id=?");
    $stmt->execute([$id]);
    echo "<script>alert('User deactivated successfully!'); window.location='manage_users.php';</script>";
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Prevent admin from deleting CEO
    $user = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $user->execute([$id]);
    $u = $user->fetch(PDO::FETCH_ASSOC);

    if ($u && $u['role'] === 'ceo' && $_SESSION['role'] !== 'ceo') {
        echo "<script>alert('Admins cannot delete CEO!'); window.location='manage_users.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    echo "<script>alert('User deleted successfully!'); window.location='manage_users.php';</script>";
    exit;
}
// Handle Reactivate
if (isset($_GET['reactivate'])) {
  $id = intval($_GET['reactivate']);

  // Prevent admin from reactivating CEO (shouldn't happen but safe)
  $user = $pdo->prepare("SELECT role FROM users WHERE id=?");
  $user->execute([$id]);
  $u = $user->fetch(PDO::FETCH_ASSOC);

  if ($u && $u['role'] === 'ceo' && $_SESSION['role'] !== 'ceo') {
      echo "<script>alert('Admins cannot reactivate CEO!'); window.location='manage_users.php';</script>";
      exit;
  }

  $stmt = $pdo->prepare("UPDATE users SET status='approved' WHERE id=?");
  $stmt->execute([$id]);
  echo "<script>alert('User reactivated successfully!'); window.location='manage_users.php';</script>";
  exit;
}

// Fetch users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'Manage Users - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<main class="container">
  <div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0"><i data-lucide="users" class="me-2"></i> Manage Users</h2>
      <div>
        <a href="add_user.php" class="btn btn-outline-primary btn-sm"><i data-lucide="plus" class="me-1"></i> Add User</a>
      </div>
    </div>

    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($users)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">No users found.</td>
                </tr>
              <?php else: ?>
                <?php foreach($users as $user): ?>
                  <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= ucfirst($user['role']) ?></td>
                    <td>
                      <span class="badge <?= $user['status']==='approved'?'bg-success':'bg-warning' ?>">
                        <?= ucfirst($user['status']) ?>
                      </span>
                    </td>
                    <td>
  <?php if($user['status'] === 'pending'): ?>
    <a href="?approve=<?= $user['id'] ?>" class="btn btn-sm btn-success mb-1">
      <i data-lucide="check-circle" class="me-1"></i> Approve
    </a>
  <?php elseif($user['status'] === 'rejected'): ?>
    <?php if($_SESSION['role'] === 'ceo' || ($_SESSION['role']==='admin' && $user['role']!=='ceo')): ?>
      <a href="?reactivate=<?= $user['id'] ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('Reactivate this user?');">
        <i data-lucide="refresh-ccw" class="me-1"></i> Reactivate
      </a>
    <?php endif; ?>
  <?php else: ?>
    <button class="btn btn-sm btn-secondary mb-1" disabled>
      <i data-lucide="badge-check" class="me-1"></i> Approved
    </button>
  <?php endif; ?>

  <?php if($_SESSION['role'] === 'ceo' || ($_SESSION['role']==='admin' && $user['role']!=='ceo')): ?>
    <?php if($user['status'] !== 'rejected'): ?>
      <a href="?deactivate=<?= $user['id'] ?>" class="btn btn-sm btn-warning mb-1" onclick="return confirm('Are you sure to deactivate this user?');">
        <i data-lucide="slash" class="me-1"></i> Deactivate
      </a>
    <?php endif; ?>
    <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Are you sure to delete this user?');">
      <i data-lucide="trash" class="me-1"></i> Delete
    </a>
  <?php endif; ?>
</td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</main>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.table').forEach(table => {
    new simpleDatatables.DataTable(table, {
      searchable: true,
      fixedHeight: true,
      perPage: 10
    });
  });
});
</script>

<?php include 'inc/script.php'; ?>
