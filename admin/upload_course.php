<?php
// admin/upload_course.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '210M');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
session_start();
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

// Allowed roles
$allowed = ['instructor','admin','ceo'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    die("Access denied.");
}
$instructor_id = $_SESSION['user_id'];

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 210 * 1024 * 1024) {
        $errors[] = 'Upload request too large. Maximum allowed size is 200MB.';
    }

    // verify CSRF token
    csrf_verify();

    // Main course data
    $title = trim($_POST['title'] ?? '');
    $category_id = $_POST['category'] ?: null;
    $type = $_POST['type'] ?? 'free';
    $price = ($type === 'paid') ? floatval($_POST['price'] ?? 0) : 0.00;
    $description = trim($_POST['description'] ?? '');

    if ($title === '') $errors[] = "Course title is required";

    // Upload Thumbnail (validate MIME, extension and size)
    $thumbnail = null;
    if (!empty($_FILES['thumbnail']['name'])) {
        $img = $_FILES['thumbnail'];
        if ($img['error'] === UPLOAD_ERR_OK && $img['size'] <= 5 * 1024 * 1024) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $img['tmp_name']);
            finfo_close($finfo);
            $allowedThumb = ['image/jpeg', 'image/png', 'image/webp'];
            $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            $blocked = ['php', 'phtml', 'phar', 'js', 'exe', 'sh'];
            if (in_array($mime, $allowedThumb) && !in_array($ext, $blocked)) {
                $thumbDir = __DIR__ . '/../assets/uploads/thumbnails/';
                if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
                $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = $thumbDir . $safeName;
                if (move_uploaded_file($img['tmp_name'], $dest)) {
                    $thumbnail = '../assets/uploads/thumbnails/' . $safeName;
                    $createdFiles[] = $dest;
                } else {
                    $errors[] = 'Failed to move thumbnail file.';
                }
            } else {
                $errors[] = 'Invalid thumbnail file type.';
            }
        } else {
            $errors[] = 'Thumbnail upload error or file too large.';
        }
    }

    if (empty($errors)) {
        $createdFiles = [];
        $pdo->beginTransaction();

        try {
            // Price approval logic
            $price_status = ($type === 'paid' && $_SESSION['role'] === 'instructor') ? 'pending' : 'approved';

            // Insert course
            $stmt = $pdo->prepare("
                INSERT INTO courses (instructor_id, category_id, title, description, type, price, price_status, thumbnail, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
            ");
            $stmt->execute([$instructor_id, $category_id, $title, $description, $type, $price, $price_status, $thumbnail]);
            $course_id = $pdo->lastInsertId();

            // Outlines + Lessons
        if (isset($_POST['outlines']) && is_array($_POST['outlines'])) {

            foreach ($_POST['outlines'] as $o_index => $outline) {

                $o_title = trim($outline['title'] ?? '');
                $o_desc  = trim($outline['description'] ?? '');

                if ($o_title === '') continue;

                // Insert outline
                $insOutline = $pdo->prepare("
                    INSERT INTO course_outline (course_id, title, description, order_no, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insOutline->execute([$course_id, $o_title, $o_desc, $o_index]);
                $outline_id = $pdo->lastInsertId();

                // Process lessons
                if (!empty($_POST['lessons'][$o_index]) && is_array($_POST['lessons'][$o_index])) {

                    foreach ($_POST['lessons'][$o_index] as $l_index => $lesson) {

                        $l_title = trim($lesson['title'] ?? '');
                        if ($l_title === '') continue;

                        $l_description = trim($lesson['description'] ?? '');
                        $l_content     = trim($lesson['content'] ?? '');
                        $youtube_url   = trim($lesson['video_url'] ?? '');
                        $lesson_type   = in_array($lesson['lesson_type'] ?? '', ['text','video','mixed']) ? $lesson['lesson_type'] : 'mixed';
                        $order_no      = intval($lesson['order_no'] ?? $l_index);

                        /*
                         ---------------------------------------------------
                         VIDEO HANDLING: (Uploaded file overrides YouTube)
                         ---------------------------------------------------
                        */
                        $final_video_url = null;

                        // Uploaded File
                        if (!empty($_FILES['lesson_videos']['name'][$o_index][$l_index])) {
                          $mediaDir = __DIR__ . '/../assets/uploads/media/';
                          if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

                          $videoFile = [
                            'name' => $_FILES['lesson_videos']['name'][$o_index][$l_index],
                            'tmp_name' => $_FILES['lesson_videos']['tmp_name'][$o_index][$l_index],
                            'error' => $_FILES['lesson_videos']['error'][$o_index][$l_index],
                            'size' => $_FILES['lesson_videos']['size'][$o_index][$l_index]
                          ];

                          if ($videoFile['error'] === UPLOAD_ERR_OK && $videoFile['size'] <= 200 * 1024 * 1024) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $videoFile['tmp_name']);
                            finfo_close($finfo);
                            $allowedVideo = ['video/mp4','video/webm','video/ogg','application/octet-stream'];
                            $ext = strtolower(pathinfo($videoFile['name'], PATHINFO_EXTENSION));
                            $blocked = ['php','phtml','phar','js','exe','sh'];
                            if (!in_array($ext, $blocked) && in_array($mime, $allowedVideo)) {
                              $safe = bin2hex(random_bytes(8)) . '.' . $ext;
                              $dest = $mediaDir . $safe;
                              if (move_uploaded_file($videoFile['tmp_name'], $dest)) {
                                $final_video_url = '../assets/uploads/media/' . $safe;
                                $createdFiles[] = $dest;
                              }
                            }
                          }
                        }
                        // YouTube or External URL
                        elseif (!empty($youtube_url)) {
                            $final_video_url = $youtube_url;
                        }

                        /*
                         ---------------------------
                         INSERT LESSON
                         ---------------------------
                        */
                        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $l_title));

                        $insLesson = $pdo->prepare("
                        INSERT INTO lessons (
                            course_id,
                            outline_id,
                            title,
                            slug,
                            content,
                            video_url,
                            description,
                            duration,
                            lesson_type,
                            order_no,
                            status,
                            created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW()
                        )
                    ");
                    
                    $insLesson->execute([
                        $course_id,
                        $outline_id,
                        $l_title,
                        $slug,
                        $l_content,
                        $final_video_url,
                        $l_description,
                        null,               // duration still null
                        $lesson_type,
                        $order_no
                    ]);
                    
                    $lesson_id = $pdo->lastInsertId();
                    
                    /* -------------------------------
                      INSERT TEST QUESTIONS
                    --------------------------------*/
                    if (!empty($_POST['tests'][$o_index][$l_index])) {

                      foreach ($_POST['tests'][$o_index][$l_index] as $t) {

                          $question = $t['question'] ?? null;
                          $answers = $t['answers'] ?? null;
                          $correct_index = $t['correct_index'] ?? null;

                          if (!$question || !$answers) continue;

                          $stmt = $pdo->prepare("
                              INSERT INTO lesson_tests (lesson_id, question, answers, correct_index)
                              VALUES (?, ?, ?, ?)
                          ");

                          $stmt->execute([
                              $lesson_id,
                              $question,
                              $answers,
                              $correct_index
                          ]);
                      }
                    }

                    
                        /*
                         -------------------------------
                         ATTACHMENT FILE HANDLING
                         -------------------------------
                        */
                        if (!empty($_FILES['lesson_attachments']['name'][$o_index][$l_index])) {

                            $attDir = __DIR__ . '/../assets/uploads/attachments/';
                            if (!is_dir($attDir)) mkdir($attDir, 0777, true);

                            $original = $_FILES['lesson_attachments']['name'][$o_index][$l_index];
                            $safe = time() . "_" . preg_replace('/[^a-zA-Z0-9\-_\.]/','', basename($original));
                            $tmp  = $_FILES['lesson_attachments']['tmp_name'][$o_index][$l_index];
                            $dest = $attDir . $safe;

                            if (move_uploaded_file($tmp, $dest)) {
                                $createdFiles[] = $dest;

                                // Insert into lesson_attachments table
                                $insAtt = $pdo->prepare("
                                    INSERT INTO lesson_attachments (lesson_id, file_name, file_path, file_type)
                                    VALUES (?, ?, ?, ?)
                                ");

                                $insAtt->execute([
                                    $lesson_id,
                                    basename($safe),
                                    '../assets/uploads/attachments/' . $safe,
                                    pathinfo($safe, PATHINFO_EXTENSION)
                                ]);
                            }
                        }

                    } // end lessons loop
                }

            } // end outlines loop
        }

            // Redirect to edit page
            $pdo->commit();
            header("Location: edit_course.php?id=" . $course_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            foreach ($createdFiles as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            $errors[] = 'Unable to save course. Please try again.';
            error_log('Course upload failed: ' . $e->getMessage());
        }
    }
}

// Page UI
$pageTitle = 'Upload New Course - LMS';
include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
include __DIR__ . '/inc/navbar.php';
?>


<main class="container">
  <div class="container-fluid p-4">
    <h1 class="h3 mb-4">Upload New Course & Lessons</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul><?php foreach($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="courseForm">
      <?php echo csrf_input(); ?>
      <div class="card mb-3 p-3">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Course Title</label>
            <input name="title" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
              <option value="">-- Select Category --</option>
              <?php foreach($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type" id="courseType" class="form-select" onchange="togglePrice()">
              <option value="free">Free</option>
              <option value="paid">Paid</option>
            </select>
          </div>
          <div class="col-md-4" id="priceField" style="display:none;">
            <label class="form-label">Price (₦)</label>
            <input name="price" type="number" step="0.01" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Thumbnail</label>
            <input name="thumbnail" type="file" accept="image/*" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <div id="courseDescriptionEditor" style="height:150px;"></div>
            <textarea name="description" id="courseDescription" style="display:none;"></textarea>

          </div>
        </div>
      </div>

      <!-- Outlines + lessons area -->
      <div id="outlinesContainer"></div>
      <div class="mb-3">
        <button type="button" class="btn btn-secondary" onclick="addOutline()">+ Add Module / Outline</button>
      </div>

      <div>
        <button type="submit" class="btn btn-primary">Create Course</button>
      </div>
    </form>
  </div>
</main>

<style>
.module-block { border:1px dashed #ddd; padding:12px; margin-bottom:12px; border-radius:6px; background:#fff; }
.lesson-sub { border:1px solid #eee; padding:10px; margin:6px 0; border-radius:6px; background:#fafafa; }
</style>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
const quillToolbarOptions = [
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

let outlineIndex = 0;
const lessonEditors = [];
window.courseDescriptionEditor = null;
let courseDescriptionEditor = null;

function initQuillEditors() {
    if (!courseDescriptionEditor) {
        window.courseDescriptionEditor = courseDescriptionEditor = new Quill('#courseDescriptionEditor', {
            theme: 'snow',
            modules: { toolbar: quillToolbarOptions }
        });
    }

    document.querySelectorAll('.quill-editor').forEach(el => {
        if (el.dataset.quillInited === '1') return;
        const targetName = el.dataset.target;
        const textarea = document.getElementsByName(targetName)[0];
        if (!textarea) return;

        const quill = new Quill(el, {
            theme: 'snow',
            modules: { toolbar: quillToolbarOptions }
        });

        if (textarea.value) {
            quill.root.innerHTML = textarea.value;
        }

        el.dataset.quillInited = '1';
        el.__quill = quill;
        lessonEditors.push({ quill, textarea });
    });
}

function syncQuillContent() {
    if (courseDescriptionEditor) {
        const courseDescription = document.getElementById('courseDescription');
        courseDescription.value = courseDescriptionEditor.root.innerHTML;
    }

    lessonEditors.forEach(({ quill, textarea }) => {
        if (textarea) {
            textarea.value = quill.root.innerHTML;
        }
    });
}

function togglePrice(){
  document.getElementById('priceField').style.display = document.getElementById('courseType').value === 'paid' ? 'block' : 'none';
}

function addOutline(){
  const c = document.getElementById('outlinesContainer');
  const idx = outlineIndex++;

  const html = `
  <div class="module-block" data-idx="${idx}">
    <div class="d-flex justify-content-between">
      <h5>Module <span class="ms-2">#${idx+1}</span></h5>
      <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.module-block').remove()">Remove Module</button>
    </div>

    <div class="mb-2">
      <input type="text" name="outlines[${idx}][title]" placeholder="Module title" class="form-control" required>
    </div>

    <div class="mb-2">
      <textarea name="outlines[${idx}][description]" placeholder="Module description" class="form-control"></textarea>
    </div>

    <div class="mb-2">
      <label>Lessons for this module</label>
      <div id="lessons-for-module-${idx}"></div>

      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addLesson(${idx})">
        + Add Lesson
      </button>
    </div>
  </div>`;

  c.insertAdjacentHTML('beforeend', html);
}



function addLesson(outlineIdx){
  const container = document.getElementById('lessons-for-module-' + outlineIdx);
  const lidx = container.querySelectorAll('.lesson-sub').length;

  const html = `
  <div class="lesson-sub" data-outline="${outlineIdx}" data-lesson="${lidx}">
    <div class="d-flex justify-content-between">
      <strong>Lesson</strong>
      <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.lesson-sub').remove()">Remove</button>
    </div>

    <div class="mb-2">
      <input type="text" name="lessons[${outlineIdx}][${lidx}][title]" placeholder="Lesson title" class="form-control" required>
    </div>

    <div class="mb-2">
      <textarea name="lessons[${outlineIdx}][${lidx}][description]" placeholder="Short description" class="form-control"></textarea>
    </div>

    <div class="mb-2">
      <div class="quill-editor" 
      data-target="lessons[${outlineIdx}][${lidx}][content]" 
      style="height:150px; background:#fff;"></div>

      <textarea name="lessons[${outlineIdx}][${lidx}][content]" 
            style="display:none;"></textarea>
    </div>

    <div class="mb-2">
      <input type="url" name="lessons[${outlineIdx}][${lidx}][video_url]" placeholder="Video URL" class="form-control">
    </div>

    <div class="mb-2">
      <label>Upload Video</label>
      <input type="file" name="lesson_videos[${outlineIdx}][${lidx}]" class="form-control" accept="video/*">
    </div>

    <div class="mb-2">
      <label>Attach file</label>
      <input type="file" name="lesson_attachments[${outlineIdx}][${lidx}]" class="form-control" accept=".pdf,.doc,.zip,.pptx,.mp4">
    </div>

    <div class="mb-2">
      <select name="lessons[${outlineIdx}][${lidx}][lesson_type]" class="form-select">
        <option value="mixed">Mixed (text + video)</option>
        <option value="text">Text</option>
        <option value="video">Video</option>
      </select>
    </div>

    <!-- TEST SECTION -->
    <div class="mt-3">
      <button type="button" class="btn btn-sm btn-warning" onclick="openTestModal(${outlineIdx}, ${lidx})">
        ➕ Add Test Question
      </button>
      <div class="test-list mt-2"></div>
    </div>

    <input type="hidden" name="lessons[${outlineIdx}][${lidx}][order_no]" value="${lidx}">
  </div>`;

  container.insertAdjacentHTML('beforeend', html);
  initQuillEditors();
}

// Initialize editors once DOM is ready and sync on submit
window.addEventListener('DOMContentLoaded', () => {
    initQuillEditors();
    const courseForm = document.getElementById('courseForm');
    if (courseForm) {
        courseForm.addEventListener('submit', syncQuillContent);
    }
});

// ===============================
// TEST MODAL
// ===============================

let currentOutline = null;
let currentLesson = null;

function openTestModal(oid, lid){
  currentOutline = oid;
  currentLesson = lid;
  document.getElementById("testModal").style.display = "block";
}

function closeTestModal(){
  document.getElementById("testModal").style.display = "none";
}

// Save test into hidden inputs
function saveTest(){
  const q = document.getElementById("test_question").value;
  const a1 = document.getElementById("ans1").value;
  const a2 = document.getElementById("ans2").value;
  const a3 = document.getElementById("ans3").value;
  const a4 = document.getElementById("ans4").value;
  const correct = document.querySelector("input[name='correct_answer']:checked").value;

  const answers = JSON.stringify([a1,a2,a3,a4]);
  const correctIndex = parseInt(correct);

  const targetLesson = document.querySelector(
    `.lesson-sub[data-outline='${currentOutline}'][data-lesson='${currentLesson}'] .test-list`
  );

  const testCount = targetLesson.children.length;

  const html = `
    <div class="alert alert-info p-2 mb-1">
      <strong>Q:</strong> ${q}
      <br><small>Answers saved</small>

      <input type="hidden" name="tests[${currentOutline}][${currentLesson}][${testCount}][question]" value="${q}">
      <input type="hidden" name="tests[${currentOutline}][${currentLesson}][${testCount}][answers]" value='${answers}'>
      <input type="hidden" name="tests[${currentOutline}][${currentLesson}][${testCount}][correct_index]" value="${correctIndex}">
    </div>
  `;

  targetLesson.insertAdjacentHTML("beforeend", html);

  closeTestModal();
  document.getElementById("testForm").reset();
}

</script>

<!-- TEST MODAL -->
<div id="testModal" 
     style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
     background:rgba(0,0,0,0.5); z-index:9999; padding-top:80px;">
  
  <div style="max-width:500px; margin:auto; background:#fff; padding:20px; border-radius:6px;">
    <h4>Add Test Question</h4>

    <form id="testForm" onsubmit="event.preventDefault(); saveTest();">

      <label>Question</label>
      <textarea id="test_question" class="form-control mb-2" required></textarea>

      <label>Answers (A–D)</label>
      <input id="ans1" class="form-control mb-1" placeholder="Answer A" required>
      <input id="ans2" class="form-control mb-1" placeholder="Answer B" required>
      <input id="ans3" class="form-control mb-1" placeholder="Answer C" required>
      <input id="ans4" class="form-control mb-2" placeholder="Answer D" required>

      <label>Correct Answer</label><br>
      <label><input type="radio" name="correct_answer" value="0" required> A</label>
      <label><input type="radio" name="correct_answer" value="1"> B</label>
      <label><input type="radio" name="correct_answer" value="2"> C</label>
      <label><input type="radio" name="correct_answer" value="3"> D</label>

      <div class="mt-3">
        <button class="btn btn-primary" type="submit">Save Test</button>
        <button class="btn btn-secondary" type="button" onclick="closeTestModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/inc/script.php'; ?>