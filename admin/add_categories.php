<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once "inc/db.php";
require_once __DIR__ . '/../inc/csrf.php';

// Restrict access
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'ceo'])) {
    die("Access denied.");
}

// Handle form submission (Add Category)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name'])) {
  csrf_verify();
  $name = trim($_POST['category_name']);
    if ($name !== '') {
        // Prevent duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name=?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO categories (name, created_at) VALUES (?, NOW())");
            $insert->execute([$name]);
            $success = "Category '$name' added successfully.";
        } else {
            $error = "Category '$name' already exists.";
        }
    } else {
        $error = "Category name cannot be empty.";
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    // Optional: Check if category exists
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id=?");
    $stmt->execute([$deleteId]);
    header("Location: add_categories.php");
    exit;
}

// Fetch all categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'Manage Categories - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="main-content">

  <div class="bg-body-light py-4 mb-4">
    <div class="container text-center">
      <h1 class="h3 fw-bold mb-1">Manage Course Categories</h1>
      <p class="fs-sm text-muted">Add, manage, and delete course categories for your LMS.</p>
    </div>
  </div>

  <div class="container">
    <div class="row">
      <div class="col-md-4">
        <!-- Add Category Form -->
        <div class="block block-rounded">
          <div class="block-header">
            <h3 class="block-title">Add New Category</h3>
          </div>
          <div class="block-content block-content-full">
            <?php if(isset($success)): ?>
              <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
              <?php echo csrf_input(); ?>
              <div class="mb-3">
                <label for="category_name" class="form-label">Category Name</label>
                <input type="text" name="category_name" id="category_name" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Add Category</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <!-- Existing Categories -->
        <div class="block block-rounded">
          <div class="block-header">
            <h3 class="block-title">Existing Categories</h3>
          </div>
          <div class="block-content block-content-full">
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Created At</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(empty($categories)): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted">No categories yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach($categories as $idx => $cat): ?>
                      <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['created_at']) ?></td>
                        <td>
                          <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?');">
                            <i class="fa fa-trash"></i> Delete
                          </a>
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

    </div>
  </div>

</div>

<?php include 'inc/script.php'; ?>
</body>
</html>
