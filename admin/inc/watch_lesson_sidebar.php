<div class="col-lg-3 mb-3">
  <div class="bg-light p-3 rounded shadow-sm">
    <?php if (!empty($attachments)): ?>
        <div class="mt-4 border-top pt-3">
            <h5 class="fw-bold">Lesson Attachments</h5>
            <ul class="list-group list-group-flush">
                <?php foreach ($attachments as $file): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($file['file_name']) ?></span>
                        <a href="<?= htmlspecialchars($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Download</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <h5 class="fw-bold mb-3"><?= htmlspecialchars($lesson['course_title']) ?></h5>

    <ul class="list-group">
      <?php foreach ($all_lessons as $index => $l):
          $locked = ($index > 0 && !$all_lessons[$index-1]['completed']);
      ?>
      <a class="list-group-item list-group-item-action <?= $l['id']==$lesson_id?'active':'' ?> <?= $locked?'disabled locked':'' ?>" 
         data-lesson-id="<?= (int)$l['id'] ?>"
         href="<?= $locked?'#':'watch_lesson.php?id='.$l['id'] ?>">
         <div class="d-flex justify-content-between align-items-center w-100">
             <div class="lesson-title"><?= htmlspecialchars($l['title']) ?></div>
             <div class="lesson-meta">
                 <?php if ($l['completed']): ?>
                     <span class="badge bg-success">Completed</span>
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
