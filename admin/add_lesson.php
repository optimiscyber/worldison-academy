<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

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
$maxContentChars = 200000;
$maxDescriptionChars = 4000;
$maxUploadBytes = 10 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $outline_id = !empty($_POST['outline_id']) ? intval($_POST['outline_id']) : null;
    $new_outline = trim($_POST['new_outline'] ?? '');
    $lesson_type = $_POST['lesson_type'] ?? 'mixed';
    $media_type = $_POST['media_type'] ?? 'none';
    $video_url = null;
    $seo_title = trim($_POST['seo_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    if ($title === '') {
        $errors[] = 'Lesson title is required.';
    }

    if (mb_strlen($title, 'UTF-8') > 150) {
        $errors[] = 'Lesson title should be 150 characters or fewer.';
    }

    if (mb_strlen($description, 'UTF-8') > $maxDescriptionChars) {
        $errors[] = 'Lesson description is too long. Keep it under 4,000 characters.';
    }

    if (mb_strlen($content, 'UTF-8') > $maxContentChars) {
        $errors[] = 'Lesson content is too large. Keep it under 200,000 characters.';
    }

    if ($new_outline !== '' && empty($outline_id)) {
        $outlineStmt = $pdo->prepare("INSERT INTO course_outline (course_id, title, description, order_no, created_at) VALUES (?, ?, '', ?, NOW())");
        $outlineStmt->execute([$course_id, $new_outline, 999]);
        $outline_id = (int) $pdo->lastInsertId();
    }

    if ($lesson_type !== 'text') {
        if ($media_type === 'youtube') {
            $video_url = trim($_POST['youtube_url'] ?? '');
        } elseif ($media_type === 'upload' && !empty($_FILES['video_file']['name'])) {
            $target_dir = "../assets/uploads/media/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = time() . "-" . basename($_FILES['video_file']['name']);
            $video_path = $target_dir . $filename;
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_path)) {
                $video_url = $video_path;
            } else {
                $errors[] = 'Video upload failed.';
            }
        }
    }

    $attachmentPaths = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $target_dir = '../assets/uploads/attachments/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        foreach ($_FILES['attachments']['name'] as $index => $name) {
            if (empty($name)) continue;
            $tmp_name = $_FILES['attachments']['tmp_name'][$index] ?? '';
            $size = (int) ($_FILES['attachments']['size'][$index] ?? 0);
            if ($size > $maxUploadBytes) {
                $errors[] = 'One or more resources exceed the 10MB upload limit.';
                continue;
            }
            $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9\-_\.]/', '', basename($name));
            $dest = $target_dir . $safe;
            if (move_uploaded_file($tmp_name, $dest)) {
                $attachmentPaths[] = ['file_name' => basename($safe), 'file_path' => '../assets/uploads/attachments/' . $safe, 'file_type' => strtolower(pathinfo($safe, PATHINFO_EXTENSION))];
            } else {
                $errors[] = 'A resource upload failed.';
            }
        }
    }

    $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(order_no), 0) + 1 FROM lessons WHERE course_id = ?");
    $orderStmt->execute([$course_id]);
    $next_order = $orderStmt->fetchColumn();

    if (empty($errors)) {
        $word_count = preg_match_all('/\p{L}+/u', strip_tags($content), $matches);
        $reading_time = max(1, (int) ceil(($word_count ?: 0) / 180));
        $stmt = $pdo->prepare("INSERT INTO lessons 
            (course_id, outline_id, title, slug, seo_title, meta_description, content, content_format, reading_time, video_url, description, duration, lesson_type, order_no, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'html', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $course_id, $outline_id, $title, $slug, $seo_title, $meta_description, $content, $reading_time, $video_url,
            $description, $duration, $lesson_type, $next_order, $status
        ]);

        $lesson_id = (int) $pdo->lastInsertId();
        foreach ($attachmentPaths as $attachment) {
            $attachStmt = $pdo->prepare("INSERT INTO lesson_attachments (lesson_id, file_name, file_path, file_type, created_at) VALUES (?, ?, ?, ?, NOW())");
            $attachStmt->execute([$lesson_id, $attachment['file_name'], $attachment['file_path'], $attachment['file_type']]);
        }

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

            <div class="mb-3">
                <label class="form-label">Lesson Title</label>
                <input type="text" id="lessonTitle" name="title" class="form-control" required>
                <div class="form-text">Autosave drafts locally while you write. Long-form lessons are supported.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Module / Chapter</label>
                    <select name="outline_id" class="form-select">
                        <option value="">-- None (General Lesson) --</option>
                        <?php foreach ($outlines as $o): ?>
                            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Create New Module</label>
                    <input type="text" name="new_outline" class="form-control" placeholder="New chapter or module">
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label">SEO Title</label>
                    <input type="text" name="seo_title" class="form-control" maxlength="255" placeholder="Optional SEO title">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Meta Description</label>
                    <input type="text" name="meta_description" class="form-control" maxlength="255" placeholder="Optional meta description">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Short Description</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-table" data-editor="description">Insert Table</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-callout" data-editor="description">Insert Callout</button>
                </div>
                <div id="descriptionEditor" style="min-height:120px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
                <textarea name="description" id="description" style="display:none;"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Lesson Content (Text, Images, Resources)</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-table" data-editor="content">Insert Table</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-callout" data-editor="content">Insert Callout</button>
                </div>
                <div id="contentEditor" style="min-height:220px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
                <textarea name="content" id="content" style="display:none;"></textarea>
                <div class="form-text">Maximum 200,000 characters and 10MB per uploaded resource.</div>
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

            <div class="mb-3">
                <label class="form-label">Downloadable Resources</label>
                <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.png,.jpg,.jpeg">
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

const descriptionQuill = new Quill('#descriptionEditor', { theme: 'snow', modules:{ toolbar: toolbarOptions } });
const contentQuill = new Quill('#contentEditor', { theme: 'snow', modules:{ toolbar: toolbarOptions } });

function insertStructuredBlock(quill, type) {
    const range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
    if (type === 'table') {
        quill.insertEmbed(range.index, 'html', '<table class="table table-bordered mb-3"><tbody><tr><th>Header</th><th>Header</th></tr><tr><td>Cell</td><td>Cell</td></tr></tbody></table>');
    } else {
        quill.insertEmbed(range.index, 'html', '<div class="alert alert-info mb-3"><strong>Callout</strong><p>Use this section to highlight key points.</p></div>');
    }
    quill.setSelection(range.index + 1, 0);
}

document.querySelectorAll('[data-action="insert-table"]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const quill = btn.dataset.editor === 'content' ? contentQuill : descriptionQuill;
        insertStructuredBlock(quill, 'table');
    });
});

document.querySelectorAll('[data-action="insert-callout"]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const quill = btn.dataset.editor === 'content' ? contentQuill : descriptionQuill;
        insertStructuredBlock(quill, 'callout');
    });
});

document.getElementById('lessonForm').addEventListener('submit', () => {
    document.getElementById('description').value = descriptionQuill.root.innerHTML;
    document.getElementById('content').value = contentQuill.root.innerHTML;
    localStorage.removeItem('lessonDraft_<?= $course_id ?>');
});

const draftKey = 'lessonDraft_<?= $course_id ?>';
function saveDraft() {
    const payload = {
        title: document.getElementById('lessonTitle').value,
        outline_id: document.querySelector('select[name="outline_id"]').value,
        new_outline: document.querySelector('input[name="new_outline"]').value,
        seo_title: document.querySelector('input[name="seo_title"]').value,
        meta_description: document.querySelector('input[name="meta_description"]').value,
        description: descriptionQuill.root.innerHTML,
        content: contentQuill.root.innerHTML,
        duration: document.querySelector('input[name="duration"]').value,
        lesson_type: document.querySelector('select[name="lesson_type"]').value,
        status: document.querySelector('select[name="status"]').value,
        youtube_url: document.querySelector('input[name="youtube_url"]').value
    };
    localStorage.setItem(draftKey, JSON.stringify(payload));
}

function restoreDraft() {
    const saved = localStorage.getItem(draftKey);
    if (!saved) return;
    try {
        const payload = JSON.parse(saved);
        if (payload.title) document.getElementById('lessonTitle').value = payload.title;
        if (payload.outline_id) document.querySelector('select[name="outline_id"]').value = payload.outline_id;
        if (payload.new_outline) document.querySelector('input[name="new_outline"]').value = payload.new_outline;
        if (payload.seo_title) document.querySelector('input[name="seo_title"]').value = payload.seo_title;
        if (payload.meta_description) document.querySelector('input[name="meta_description"]').value = payload.meta_description;
        if (payload.description) descriptionQuill.root.innerHTML = payload.description;
        if (payload.content) contentQuill.root.innerHTML = payload.content;
        if (payload.duration) document.querySelector('input[name="duration"]').value = payload.duration;
        if (payload.lesson_type) document.querySelector('select[name="lesson_type"]').value = payload.lesson_type;
        if (payload.status) document.querySelector('select[name="status"]').value = payload.status;
        if (payload.youtube_url) document.querySelector('input[name="youtube_url"]').value = payload.youtube_url;
        toggleLessonType();
        toggleMedia();
    } catch (e) {}
}

['input','change'].forEach((evt) => {
    document.getElementById('lessonForm').addEventListener(evt, saveDraft);
});

document.addEventListener('DOMContentLoaded', restoreDraft);

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
