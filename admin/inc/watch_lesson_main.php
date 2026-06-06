<div class="col-lg-9">
<style>
#readingProgress {
    position: fixed;
    top: 0; left: 0;
    height: 4px;
    background: #007bff;
    width: 0%;
    z-index: 99999;
}
</style>
<div id="readingProgress"></div>

<script>
document.addEventListener("scroll", function () {
    let scrollTop = document.documentElement.scrollTop;
    let height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    let progress = (scrollTop / height) * 100;
    document.getElementById("readingProgress").style.width = progress + "%";
});
</script>

<div class="bg-light p-4 rounded shadow-sm">

<?php $progress_percent = $total_lessons ? (int) round(($completed_lessons / $total_lessons) * 100) : 0; ?>
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>Progress: <strong><?= $completed_lessons ?>/<?= $total_lessons ?> (<?= $progress_percent ?>%)</strong></div>
        <div class="small text-muted">Lesson: <?= htmlspecialchars($lesson['title']) ?></div>
    </div>
    <div class="progress" style="height:8px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress_percent ?>%"></div>
    </div>
</div>

    <h3><?= htmlspecialchars($lesson['title']) ?></h3>
    <p class="text-muted">Instructor: <?= htmlspecialchars($lesson['instructor_name']) ?></p>

<?php if (!$hasVideo && $hasText): ?>
    <div class="alert alert-info">This is a text-only lesson.</div>
<?php endif; ?>

<?php if ($hasVideo): ?>
    <?php if (!empty($ytEmbedUrl)): ?>
        <div class="ratio ratio-16x9 mb-4">
            <iframe id="youtubePlayer" src="<?= htmlspecialchars($ytEmbedUrl) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <div id="youtubeFallback" class="alert alert-warning d-none">
            This YouTube video may not be embeddable here. 
            <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($ytID) ?>" target="_blank" rel="noopener noreferrer">Open on YouTube</a> instead.
        </div>
    <?php elseif (!empty($video)): ?>
        <div class="ratio ratio-16x9 mb-4">
            <video class="w-100 h-100" controls>
                <source src="<?= htmlspecialchars($video) ?>">
                Your browser does not support the video tag.
            </video>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if(!empty($lesson['content'])): ?>
<div class="bg-white p-3 rounded shadow-sm mb-3">

    <h4 class="fw-bold mb-3">Lesson Notes</h4>

    <div id="toc" class="mb-3 p-3 border rounded bg-light"></div>

    <div id="lessonContent">
        <?= $lesson['content'] ?>
    </div>

    <?php if (!empty($progress['completed'])): ?>
        <div class="alert alert-success">This lesson is already completed.</div>
    <?php else: ?>
        <button id="textMarkComplete" class="btn btn-success mt-3">Mark Lesson Complete</button>
    <?php endif; ?>

    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const content = document.getElementById("lessonContent");
    const toc = document.getElementById("toc");

    if (!content) return;

    let headings = content.querySelectorAll("h1, h2, h3");
    if (headings.length === 0) return;

    let tocList = "<h5 class='fw-bold'>📘 Table of Contents</h5><ul>";
    headings.forEach((h, i) => {
        let id = "sec_" + i;
        h.id = id;
        tocList += `<li><a href="#${id}">${h.innerText}</a></li>`;
    });
    tocList += "</ul>";
    toc.innerHTML = tocList;

    headings.forEach((h) => {
        let wrapper = document.createElement("div");
        wrapper.classList.add("mb-4");

        let toggleBtn = document.createElement("button");
        toggleBtn.classList.add("btn", "btn-sm", "btn-outline-primary", "mb-2");
        toggleBtn.innerText = "Toggle Section";

        let sectionBlock = document.createElement("div");
        sectionBlock.classList.add("border", "rounded", "p-3");

        sectionBlock.appendChild(h.cloneNode(true));

        let next = h.nextElementSibling;
        while (next && !["H1","H2","H3"].includes(next.tagName)) {
            let toMove = next;
            next = next.nextElementSibling;
            sectionBlock.appendChild(toMove);
        }

        wrapper.appendChild(toggleBtn);
        wrapper.appendChild(sectionBlock);
        toggleBtn.addEventListener("click", () => {
            sectionBlock.classList.toggle("d-none");
        });

        content.insertBefore(wrapper, h);
    });
});
</script>
<?php endif; ?>


<!-- NAVIGATION + CERTIFICATE -->
<div class="d-flex justify-content-between align-items-center mt-4">

    <?php if($prev_id): ?>
        <a class="btn btn-outline-secondary" href="watch_lesson.php?id=<?= $prev_id ?>">&laquo; Previous</a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>

    <div>
    <?php if ($next_id): ?>
        <a class="btn btn-primary" href="<?= $nextLink ?>">Next &raquo;</a>

    <?php else: ?>
        <?php if ($all_completed): ?>
        <div class="p-3 border rounded bg-light text-center">
            <h4 class="fw-bold mb-3">🎉 Course Completed – Certificate</h4>

            <?php if (!$certificate): ?>
                <a href="cert_request.php?course_id=<?= $course_id ?>" class="btn btn-success">
                    Request Certificate
                </a>

            <?php else: ?>
                <p class="mb-2">Certificate Status: <strong><?= ucfirst($certificate['status']) ?></strong></p>

                <?php if ($certificate['status']==='rejected' && !empty($certificate['reason'])): ?>
                <div class="alert alert-danger">
                    <strong>Reason:</strong> <?= htmlspecialchars($certificate['reason']) ?>
                </div>
                <?php endif; ?>

                <?php if ($certificate['status']==='approved' && $certificate['certificate_url']): ?>
                <a href="download_certificate.php?id=<?= $certificate['id'] ?>" class="btn btn-primary">
                    Download Certificate
                </a>
                <?php endif; ?>

                <?php if ($certificate['status']==='pending'): ?>
                <div class="alert alert-info mt-2">
                    Your certificate request is being reviewed.
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>
            <button class="btn btn-secondary" disabled>Complete all lessons to unlock Certificate</button>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

</div>
</div>
