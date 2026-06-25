<?php
// edit_lesson.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/inc/db.php';

// Allowed roles
$allowed = ['instructor','admin','ceo'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    die("Access denied.");
}

$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch lesson
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE id=?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lesson) die("Lesson not found.");

// Fetch attachments
$attachmentsStmt = $pdo->prepare("SELECT * FROM lesson_attachments WHERE lesson_id=?");
$attachmentsStmt->execute([$lesson_id]);
$attachments = $attachmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tests
$testsStmt = $pdo->prepare("SELECT * FROM lesson_tests WHERE lesson_id=?");
$testsStmt->execute([$lesson_id]);
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';
$maxContentChars = 200000;
$maxDescriptionChars = 4000;
$maxUploadBytes = 10 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $lesson_type = $_POST['lesson_type'] ?? 'mixed';
    $video_url = trim($_POST['video_url'] ?? null);
    $seo_title = trim($_POST['seo_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    if ($title === '') $errors[] = "Lesson title is required";
    if (mb_strlen($description, 'UTF-8') > $maxDescriptionChars) $errors[] = 'Lesson description is too long.';
    if (mb_strlen($content, 'UTF-8') > $maxContentChars) $errors[] = 'Lesson content is too large.';

    // Handle video upload
    if (!empty($_FILES['video_file']['name'])) {
        $mediaDir = __DIR__ . '/../assets/uploads/media/';
        if (!is_dir($mediaDir)) mkdir($mediaDir, 0777, true);
        $safe = time() . "_" . preg_replace('/[^a-zA-Z0-9\-_\.]/','', basename($_FILES['video_file']['name']));
        $tmp = $_FILES['video_file']['tmp_name'];
        $dest = $mediaDir . $safe;
        if (move_uploaded_file($tmp, $dest)) {
            $video_url = '../assets/uploads/media/' . $safe;
        } else {
            $errors[] = "Video upload failed.";
        }
    }

    if (empty($errors)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
        $word_count = preg_match_all('/\p{L}+/u', strip_tags($content), $matches);
        $reading_time = max(1, (int) ceil(($word_count ?: 0) / 180));
        $upd = $pdo->prepare("
            UPDATE lessons 
            SET title=?, slug=?, seo_title=?, meta_description=?, description=?, content=?, content_format='html', reading_time=?, video_url=?, lesson_type=?, updated_at=NOW() 
            WHERE id=?
        ");
        $upd->execute([$title, $slug, $seo_title, $meta_description, $description, $content, $reading_time, $video_url, $lesson_type, $lesson_id]);

        // Handle attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            $attDir = __DIR__ . '/../assets/uploads/attachments/';
            if (!is_dir($attDir)) mkdir($attDir, 0777, true);
            foreach ($_FILES['attachments']['name'] as $i => $name) {
                $tmp = $_FILES['attachments']['tmp_name'][$i];
                $safe = time() . "_" . preg_replace('/[^a-zA-Z0-9\-_\.]/','', basename($name));
                $dest = $attDir . $safe;
                if (move_uploaded_file($tmp, $dest)) {
                    $pdo->prepare("INSERT INTO lesson_attachments (lesson_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)")
                        ->execute([$lesson_id, basename($safe), '../assets/uploads/attachments/' . $safe, pathinfo($safe, PATHINFO_EXTENSION)]);
                }
            }
        }

        // Handle tests
        if (!empty($_POST['tests']) && is_array($_POST['tests'])) {
            foreach ($_POST['tests'] as $t) {
                $t_id = $t['id'] ?? null;
                $answers = json_encode([$t['a'], $t['b'], $t['c'], $t['d']]);
                if ($t_id) {
                    $updTest = $pdo->prepare("UPDATE lesson_tests SET question=?, answers=?, correct_index=?, updated_at=NOW() WHERE id=? AND lesson_id=?");
                    $updTest->execute([$t['question'], $answers, $t['correct_index'], $t_id, $lesson_id]);
                } else {
                    $insTest = $pdo->prepare("INSERT INTO lesson_tests (lesson_id, question, answers, correct_index, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $insTest->execute([$lesson_id, $t['question'], $answers, $t['correct_index']]);
                }
            }
        }

        $success = "Lesson updated successfully!";
    }
}
?>

<?php include __DIR__ . '/inc/header.php'; ?>

<main class="container my-4">
<h1>Edit Lesson</h1>

<?php if($errors): ?>
<div class="alert alert-danger">
    <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
</div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form id="lessonForm" method="POST" enctype="multipart/form-data">
    <div class="mb-3">
        <label>Lesson Title</label>
        <input name="title" id="lessonTitle" class="form-control" value="<?= htmlspecialchars($lesson['title']) ?>" required>
        <div class="form-text">Drafts autosave locally while you edit.</div>
    </div>

    <div class="row g-3">
        <div class="col-md-6 mb-3">
            <label>SEO Title</label>
            <input name="seo_title" class="form-control" value="<?= htmlspecialchars($lesson['seo_title'] ?? '') ?>" maxlength="255">
        </div>
        <div class="col-md-6 mb-3">
            <label>Meta Description</label>
            <input name="meta_description" class="form-control" value="<?= htmlspecialchars($lesson['meta_description'] ?? '') ?>" maxlength="255">
        </div>
    </div>

    <div class="mb-3">
        <label>Description</label>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-table" data-editor="description">Insert Table</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-callout" data-editor="description">Insert Callout</button>
        </div>
        <div id="descriptionEditor" style="min-height:100px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
        <textarea name="description" id="description" style="display:none;"></textarea>
    </div>

    <div class="mb-3">
        <label>Content</label>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-table" data-editor="content">Insert Table</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="insert-callout" data-editor="content">Insert Callout</button>
        </div>
        <div id="contentEditor" style="min-height:180px; background:#fff; border:1px solid #ddd; border-radius:6px;"></div>
        <textarea name="content" id="content" style="display:none;"></textarea>
        <div class="form-text">Long-form lessons are supported and will display with a cleaner reading experience.</div>
    </div>

    <div class="mb-3">
        <label>Lesson Type</label>
        <select name="lesson_type" class="form-select">
            <option value="mixed" <?= $lesson['lesson_type']=='mixed'?'selected':'' ?>>Mixed</option>
            <option value="text" <?= $lesson['lesson_type']=='text'?'selected':'' ?>>Text</option>
            <option value="video" <?= $lesson['lesson_type']=='video'?'selected':'' ?>>Video</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Video URL</label>
        <input type="url" name="video_url" class="form-control" value="<?= htmlspecialchars($lesson['video_url']) ?>">
    </div>

    <div class="mb-3">
        <label>Upload Video</label>
        <input type="file" name="video_file" class="form-control" accept="video/*">
    </div>

    <div class="mb-3">
        <label>Attachments</label>
        <input type="file" name="attachments[]" class="form-control" multiple>
        <?php if($attachments): ?>
        <ul>
            <?php foreach($attachments as $att): ?>
                <li><a href="<?= htmlspecialchars($att['file_path']) ?>" target="_blank"><?= htmlspecialchars($att['file_name'] ?: basename($att['file_path'])) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <h4>Tests</h4>
    <div id="testsContainer">
        <?php foreach($tests as $idx => $t): 
            $answers = json_decode($t['answers'], true); ?>
            <div class="test-item mb-2 p-2 border" data-idx="<?= $idx ?>">
                <label>Question</label>
                <input type="text" name="tests[<?= $idx ?>][question]" class="form-control mb-1" value="<?= htmlspecialchars($t['question']) ?>" required>
                <?php foreach(['a','b','c','d'] as $i): ?>
                    <label>Answer <?= strtoupper($i) ?></label>
                    <input type="text" name="tests[<?= $idx ?>][<?= $i ?>]" class="form-control mb-1" value="<?= htmlspecialchars($answers[array_search($i,['a','b','c','d'])] ?? '') ?>" required>
                <?php endforeach; ?>
                <label>Correct Answer</label>
                <select name="tests[<?= $idx ?>][correct_index]" class="form-select mb-1">
                    <?php for($i=0;$i<4;$i++): ?>
                        <option value="<?= $i ?>" <?= $t['correct_index']==$i?'selected':'' ?>><?= ['A','B','C','D'][$i] ?></option>
                    <?php endfor; ?>
                </select>
                <input type="hidden" name="tests[<?= $idx ?>][id]" value="<?= $t['id'] ?>">
                <button type="button" class="btn btn-sm btn-danger mt-1" onclick="this.closest('.test-item').remove()">Remove Test</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-secondary mb-3" onclick="addTest()">+ Add Test</button>

    <div>
        <button type="submit" class="btn btn-primary">Update Lesson</button>
    </div>
</form>
</main>

<!-- Quill JS -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
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

const descriptionQuill = new Quill('#descriptionEditor', { theme: 'snow', modules:{ toolbar: fullToolbar } });
const contentQuill = new Quill('#contentEditor', { theme: 'snow', modules:{ toolbar: fullToolbar } });

descriptionQuill.root.innerHTML = <?php echo json_encode(htmlspecialchars_decode($lesson['description'] ?? '')); ?>;
contentQuill.root.innerHTML = <?php echo json_encode(htmlspecialchars_decode($lesson['content'] ?? '')); ?>;

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
    localStorage.removeItem('lessonEditDraft_<?= $lesson_id ?>');
});

const draftKey = 'lessonEditDraft_<?= $lesson_id ?>';
function saveDraft() {
    const payload = {
        title: document.getElementById('lessonTitle').value,
        seo_title: document.querySelector('input[name="seo_title"]').value,
        meta_description: document.querySelector('input[name="meta_description"]').value,
        description: descriptionQuill.root.innerHTML,
        content: contentQuill.root.innerHTML,
        lesson_type: document.querySelector('select[name="lesson_type"]').value,
        video_url: document.querySelector('input[name="video_url"]').value
    };
    localStorage.setItem(draftKey, JSON.stringify(payload));
}

function restoreDraft() {
    try {
        const saved = localStorage.getItem(draftKey);
        if (!saved) return;
        const payload = JSON.parse(saved);
        if (payload.title) document.getElementById('lessonTitle').value = payload.title;
        if (payload.seo_title) document.querySelector('input[name="seo_title"]').value = payload.seo_title;
        if (payload.meta_description) document.querySelector('input[name="meta_description"]').value = payload.meta_description;
        if (payload.description) descriptionQuill.root.innerHTML = payload.description;
        if (payload.content) contentQuill.root.innerHTML = payload.content;
        if (payload.lesson_type) document.querySelector('select[name="lesson_type"]').value = payload.lesson_type;
        if (payload.video_url) document.querySelector('input[name="video_url"]').value = payload.video_url;
    } catch (e) {}
}

['input','change'].forEach((evt) => {
    document.getElementById('lessonForm').addEventListener(evt, saveDraft);
});
document.addEventListener('DOMContentLoaded', restoreDraft);

// Test management
let testIndex = <?= count($tests) ?>;
function addTest() {
    const container = document.getElementById('testsContainer');
    const html = `
    <div class="test-item mb-2 p-2 border" data-idx="${testIndex}">
        <label>Question</label>
        <input type="text" name="tests[${testIndex}][question]" class="form-control mb-1" required>
        ${['A','B','C','D'].map((l,i) => `
            <label>Answer ${l}</label>
            <input type="text" name="tests[${testIndex}][${['a','b','c','d'][i]}]" class="form-control mb-1" required>
        `).join('')}
        <label>Correct Answer</label>
        <select name="tests[${testIndex}][correct_index]" class="form-select mb-1">
            <option value="0">A</option>
            <option value="1">B</option>
            <option value="2">C</option>
            <option value="3">D</option>
        </select>
        <button type="button" class="btn btn-sm btn-danger mt-1" onclick="this.closest('.test-item').remove()">Remove Test</button>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
    testIndex++;
}
</script>

<?php include __DIR__ . '/inc/script.php'; ?>
