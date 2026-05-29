<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "inc/db.php";

$allowed = ['admin', 'ceo', 'instructor'];

if (!in_array($_SESSION['role'], $allowed)) {
    die("Access denied.");
}

$instructor_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$course_id) {
    die("Course ID missing.");
}

// Fetch course and ensure it belongs to the instructor
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    die("Course not found or you don't have permission to edit it.");
}

// Handle POST: update course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'] ?? 'free';
    $price = ($type === 'paid') ? (float) $_POST['price'] : 0.00;
    // Only admin approves price; instructor sets price_status to pending when paid
    $price_status = ($type === 'paid') ? 'pending' : 'approved';

    // Thumbnail upload (optional)
    $thumbnail = $course['thumbnail'];
    if (!empty($_FILES['thumbnail']['name'])) {
        $target_dir = "../assets/uploads/thumbnails/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $filename = time() . "-" . basename($_FILES["thumbnail"]["name"]);
        $thumbnail_path = $target_dir . $filename;
        if (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $thumbnail_path)) {
            $thumbnail = $thumbnail_path;
            // optionally delete old thumbnail file here (careful)
        }
    }

    $update = $pdo->prepare("UPDATE courses SET title = ?, description = ?, type = ?, price = ?, price_status = ?, thumbnail = ?, updated_at = NOW() WHERE id = ? AND instructor_id = ?");
    $update->execute([$title, $description, $type, $price, $price_status, $thumbnail, $course_id, $instructor_id]);

    // reload course
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<script>alert('Course updated successfully'); window.location='edit_course.php?id={$course_id}';</script>";
    exit;
}

// Fetch lessons for this course
$lessonStmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_no ASC, created_at ASC");
$lessonStmt->execute([$course_id]);
$lessons = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Edit Course - <?= htmlspecialchars($course['title']) ?></title>
  <?php
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>
<body>
  <div class="main-content" id="mainContent">
    <div class="manage-container">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i data-lucide="edit-3" class="me-2"></i> Edit Course</h2>
        <div>
          <a href="upload_course.php" class="btn btn-outline-primary btn-sm me-2">
            <i data-lucide="upload"></i> Upload New Course
          </a>
          <a href="my_courses.php" class="btn btn-outline-secondary btn-sm">
            <i data-lucide="arrow-left"></i> Back to My Courses
          </a>
        </div>
      </div>

      <!-- Course Edit Form -->
      <form id="courseForm" method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Course Title</label>
            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($course['title']) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type" id="courseType" class="form-select" onchange="togglePrice()">
              <option value="free" <?= $course['type'] === 'free' ? 'selected' : '' ?>>Free</option>
              <option value="paid" <?= $course['type'] === 'paid' ? 'selected' : '' ?>>Paid</option>
            </select>
          </div>

          <div class="col-12" id="priceField" style="display: <?= $course['type'] === 'paid' ? 'block' : 'none' ?>;">
            <label class="form-label">Price (₦)</label>
            <input type="number" step="0.01" name="price" class="form-control" value="<?= number_format($course['price'], 2, '.', '') ?>">
            <small class="text-muted">Price changes will require admin approval.</small>
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>

            <!-- Quill editor container -->
            <div id="courseDescriptionEditor" style="min-height:200px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>

            <!-- Hidden textarea that will be submitted -->
            <textarea name="description" id="courseDescription" style="display:none;"></textarea>
          </div>


          <div class="col-md-6">
            <label class="form-label">Thumbnail</label><br>
            <?php if (!empty($course['thumbnail'])): ?>
              <img src="<?= htmlspecialchars($course['thumbnail']) ?>" alt="thumbnail" style="max-width:140px; display:block; margin-bottom:8px; border-radius:8px;"/>
            <?php endif; ?>
            <input type="file" name="thumbnail" accept="image/*" class="form-control">
          </div>

          <div class="col-md-6 d-flex align-items-end">
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Course</button>
          </div>
        </div>
      </form>

<!-- Lessons list -->
<div class="mb-3 d-flex justify-content-between align-items-center">
  <h4 class="mb-0"><i data-lucide="book-open" class="me-1"></i> Lessons (<?= count($lessons) ?>)</h4>
  <div>
    <a href="add_lesson.php?course_id=<?= $course_id ?>" class="btn btn-success btn-sm me-2">
      <i data-lucide="plus-circle"></i> Add Lesson
    </a>
    <a href="my_courses.php" class="btn btn-outline-secondary btn-sm">
      <i data-lucide="chevron-left"></i> Back
    </a>
  </div>
</div>

<?php if (empty($lessons)): ?>
  <div class="alert alert-info">No lessons yet. Click "Add Lesson" to create your first lesson.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order</th>
          <th>Title</th>
          <th>Type</th>
          <th>Media</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lessons as $lesson): ?>
          <tr>
            <td style="width:70px;"><?= (int)$lesson['order_no'] ?></td>
            <td><?= htmlspecialchars($lesson['title']) ?></td>

            <!-- Lesson Type Badge -->
            <td>
              <?php if ($lesson['lesson_type'] === 'text'): ?>
                <span class="badge bg-secondary">Text</span>
              <?php elseif ($lesson['lesson_type'] === 'video'): ?>
                <span class="badge bg-info">Video</span>
              <?php else: ?>
                <span class="badge bg-primary">Mixed</span>
              <?php endif; ?>
            </td>

            <!-- Media Badge -->
            <td>
              <?php if (!empty($lesson['video_url'])): ?>
                <?php if (preg_match('/youtu\.be|youtube\.com/i', $lesson['video_url'])): ?>
                  <span class="badge bg-danger">YouTube</span>
                <?php elseif (preg_match('/\.mp4|\.webm|\.ogg/i', $lesson['video_url'])): ?>
                  <span class="badge bg-dark">Video File</span>
                <?php else: ?>
                  <span class="badge bg-light text-dark">External</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted">None</span>
              <?php endif; ?>
            </td>

            <td>
              <span class="badge <?= $lesson['status'] === 'published' ? 'bg-success' : 'bg-warning' ?>">
                <?= ucfirst($lesson['status']) ?>
              </span>
            </td>

            <td><?= date("M d, Y", strtotime($lesson['created_at'])) ?></td>

            <td>
              <a href="edit_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                <i data-lucide="edit-3"></i> Edit
              </a>

              <a href="delete_lesson.php?id=<?= $lesson['id'] ?>&course_id=<?= $course_id ?>" 
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Delete this lesson?');">
                <i data-lucide="trash-2"></i> Delete
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

    </div>
  </div>
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<script>
// Full toolbar
const fullToolbar = [
    [{ font: [] }, { size: [] }],
    [{ header: [1,2,3,4,5,6,false] }],
    ['bold','italic','underline','strike'],
    [{ color: [] }, { background: [] }],
    [{ align: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ indent: '-1' }, { indent: '+1' }],
    ['blockquote','code-block'],
    ['link','image'],
    ['clean']
];

// Initialize Quill editor
const courseQuill = new Quill('#courseDescriptionEditor', {
    theme: 'snow',
    modules: {
        toolbar: fullToolbar
    }
});

// Load existing course description into Quill
const initialHtml = <?php echo json_encode(htmlspecialchars_decode($course['description'] ?? '')); ?>;
courseQuill.root.innerHTML = initialHtml;

// On form submit, copy Quill content into hidden textarea
document.getElementById('courseForm').addEventListener('submit', function() {
    document.getElementById('courseDescription').value = courseQuill.root.innerHTML;
});
</script>

  <?php include 'inc/script.php'; ?>

  <script>
    function togglePrice() {
      const type = document.getElementById('courseType').value;
      document.getElementById('priceField').style.display = type === 'paid' ? 'block' : 'none';
    }

    // initialize lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();
  </script>
</body>
</html>
