<?php
require_once "inc/db.php";

$instructor_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$course_id) die("Course ID missing.");

// Confirm course ownership
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) die("Invalid course or permission denied.");

// Fetch course outline modules
$outlineStmt = $pdo->prepare("SELECT * FROM course_outline WHERE course_id = ? ORDER BY order_no ASC");
$outlineStmt->execute([$course_id]);
$outlines = $outlineStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
    $description = $_POST['description'] ?? '';
    $content = $_POST['content'] ?? '';
    $duration = trim($_POST['duration']);
    $status = $_POST['status'] ?? 'draft';
    $outline_id = !empty($_POST['outline_id']) ? intval($_POST['outline_id']) : NULL;
    $lesson_type = $_POST['lesson_type'] ?? 'mixed';
    $media_type = $_POST['media_type'] ?? 'none';
    $video_url = NULL;

    // Media handling
    if ($lesson_type !== 'text') {
        if ($media_type === 'youtube') {
            $video_url = trim($_POST['youtube_url']);
        } elseif ($media_type === 'upload' && !empty($_FILES['video_file']['name'])) {
            $target_dir = "../assets/uploads/media/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = time() . "-" . basename($_FILES["video_file"]["name"]);
            $video_path = $target_dir . $filename;
            if (move_uploaded_file($_FILES["video_file"]["tmp_name"], $video_path)) {
                $video_url = $video_path;
            } else {
                $errors[] = "Video upload failed.";
            }
        }
    }

    // Determine lesson order
    $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(order_no), 0) + 1 FROM lessons WHERE course_id = ?");
    $orderStmt->execute([$course_id]);
    $next_order = $orderStmt->fetchColumn();

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO lessons 
            (course_id, outline_id, title, slug, content, video_url, description, duration, lesson_type, order_no, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $course_id, $outline_id, $title, $slug, $content, $video_url,
            $description, $duration, $lesson_type, $next_order, $status
        ]);

        echo "<script>alert('Lesson added successfully'); window.location='edit_course.php?id={$course_id}';</script>";
        exit;
    }
}

$pageTitle = "Add Lesson - " . htmlspecialchars($course['title']);
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>

<div class="container-fluid pt-4 px-4">
    <div class="bg-light rounded p-4">
        <h4 class="mb-4">Add Lesson to: <?= htmlspecialchars($course['title']) ?></h4>

        <?php if($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
        </div>
        <?php endif; ?>

        <form id="lessonForm" method="POST" enctype="multipart/form-data">

            <!-- Title -->
            <div class="mb-3">
                <label class="form-label">Lesson Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <!-- Outline Module -->
            <div class="mb-3">
                <label class="form-label">Module / Outline</label>
                <select name="outline_id" class="form-select">
                    <option value="">-- None (General Lesson) --</option>
                    <?php foreach ($outlines as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Quill Description -->
            <div class="mb-3">
                <label class="form-label">Short Description</label>
                <div id="descriptionEditor" style="min-height:100px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
                <textarea name="description" id="description" style="display:none;"></textarea>
            </div>

            <!-- Quill Content -->
            <div class="mb-3">
                <label class="form-label">Lesson Content (Text)</label>
                <div id="contentEditor" style="min-height:150px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
                <textarea name="content" id="content" style="display:none;"></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Duration</label>
                    <input type="text" name="duration" class="form-control">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Lesson Type</label>
                    <select name="lesson_type" class="form-select" onchange="toggleLessonType()">
                        <option value="mixed">Mixed (Text + Video)</option>
                        <option value="video">Video Only</option>
                        <option value="text">Text Only</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
            </div>

            <hr>

            <!-- Video Section -->
            <div id="videoSection">
                <label class="form-label d-block">Select Video Source</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" checked type="radio" name="media_type" value="youtube" onchange="toggleMedia()">
                    <label class="form-check-label">YouTube URL</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="media_type" value="upload" onchange="toggleMedia()">
                    <label class="form-check-label">Upload Video</label>
                </div>

                <div class="mb-3 mt-3" id="youtubeField">
                    <label class="form-label">YouTube URL</label>
                    <input type="url" name="youtube_url" class="form-control">
                </div>

                <div class="mb-3" id="uploadField" style="display:none;">
                    <label class="form-label">Upload Video File</label>
                    <input type="file" name="video_file" class="form-control" accept="video/*">
                </div>
            </div>

            <div class="text-end mt-4">
                <button class="btn btn-primary"><i class="fa fa-save me-2"></i>Add Lesson</button>
                <a href="edit_course.php?id=<?= $course_id ?>" class="btn btn-secondary">Cancel</a>
            </div>

        </form>
    </div>
</div>

<!-- Quill JS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
const toolbarOptions = [
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

// Initialize Quill editors
const descriptionQuill = new Quill('#descriptionEditor', { theme: 'snow', modules:{ toolbar: toolbarOptions } });
const contentQuill = new Quill('#contentEditor', { theme: 'snow', modules:{ toolbar: toolbarOptions } });

// Copy Quill content to hidden textareas on submit
document.getElementById('lessonForm').addEventListener('submit', () => {
    document.getElementById('description').value = descriptionQuill.root.innerHTML;
    document.getElementById('content').value = contentQuill.root.innerHTML;
});

// Video section toggle
function toggleLessonType() {
    let type = document.querySelector('select[name="lesson_type"]').value;
    document.getElementById('videoSection').style.display =
        (type === 'video' || type === 'mixed') ? 'block' : 'none';
}

function toggleMedia() {
    const type = document.querySelector('input[name="media_type"]:checked').value;
    document.getElementById('youtubeField').style.display = (type === 'youtube') ? 'block' : 'none';
    document.getElementById('uploadField').style.display = (type === 'upload') ? 'block' : 'none';
}
</script>

<?php include 'inc/script.php'; ?>
