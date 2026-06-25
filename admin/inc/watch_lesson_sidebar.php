<div class="col-lg-3 mb-3">
  <div class="bg-light p-3 rounded shadow-sm position-sticky top-0" style="max-height: calc(100vh - 2rem); overflow-y:auto;">
    <div class="mb-3">
      <h5 class="fw-bold mb-2"><?= htmlspecialchars($lesson['course_title'] ?? 'Course Contents') ?></h5>
      <p class="small text-muted mb-2">Follow the lesson sequence and keep going through the course.</p>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-muted">Progress</span>
        <span class="badge bg-primary-subtle text-primary"><?= (int) $completed_lessons ?>/<?= (int) $total_lessons ?></span>
      </div>
      <div class="progress" style="height:8px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?= !empty($total_lessons) ? round(($completed_lessons / $total_lessons) * 100) : 0 ?>%"></div>
      </div>
      <a href="courses-view.php?id=<?= (int) ($course_id ?? 0) ?>" class="btn btn-outline-secondary btn-sm mt-3 w-100">View course overview</a>
    </div>

    <?php if (!empty($attachments)): ?>
        <div class="mt-3 border-top pt-3">
            <h6 class="fw-bold">Lesson Attachments</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($attachments as $file): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                        <span class="small"><?= htmlspecialchars($file['file_name'] ?? 'Attachment') ?></span>
                        <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Download</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h6 class="fw-bold mt-3 mb-2">Course lessons</h6>
    <ul class="list-group">
      <?php foreach ($all_lessons as $index => $l):
          $locked = ($index > 0 && !$all_lessons[$index-1]['completed']);
      ?>
      <a class="list-group-item list-group-item-action <?= $l['id']==$lesson_id?'active':'' ?> <?= $locked?'disabled locked':'' ?>"
         data-lesson-id="<?= (int)$l['id'] ?>"
         href="<?= $locked?'#':'watch_lesson.php?id='.$l['id'] ?>"
         aria-current="<?= $l['id']==$lesson_id ? 'page' : 'false' ?>">
         <div class="d-flex justify-content-between align-items-start w-100 gap-2">
             <div class="lesson-title small"><?= htmlspecialchars($l['title']) ?></div>
             <div class="lesson-meta">
                 <?php if ($l['completed']): ?>
                     <span class="badge bg-success">Done</span>
                 <?php elseif ($locked): ?>
                     <span class="badge bg-secondary">Locked</span>
                 <?php endif; ?>
             </div>
         </div>
      </a>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
