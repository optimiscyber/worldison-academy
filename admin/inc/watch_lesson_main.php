<div class="col-lg-9">
<style>
:root {
    --lesson-bg: #f8fafc;
    --lesson-surface: #ffffff;
    --lesson-border: #dbeafe;
    --lesson-text: #0f172a;
    --lesson-muted: #64748b;
}
body[data-theme="dark"] {
    --lesson-bg: #020617;
    --lesson-surface: #0f172a;
    --lesson-border: #334155;
    --lesson-text: #f8fafc;
    --lesson-muted: #cbd5e1;
}
#readingProgress { position: fixed; top: 0; left: 0; height: 4px; background: linear-gradient(90deg,#2563eb,#7c3aed); width: 0%; z-index: 99999; }
:root { --lesson-content-width: 920px; --lesson-content-font-size: 17px; }
.lesson-shell { background: var(--lesson-surface); border: 1px solid var(--lesson-border); color: var(--lesson-text); transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
.lesson-shell .lesson-content img, .lesson-shell .lesson-content video, .lesson-shell .lesson-content iframe { max-width: 100%; height: auto; border-radius: .5rem; }
.lesson-shell .lesson-content p, .lesson-shell .lesson-content li { line-height: 1.8; overflow-wrap: anywhere; }
.lesson-shell .lesson-content table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.lesson-shell .lesson-content th, .lesson-shell .lesson-content td { border: 1px solid var(--lesson-border); padding: .6rem; }
.lesson-shell .lesson-content blockquote { border-left: 4px solid #2563eb; padding-left: 1rem; color: var(--lesson-muted); margin: 1rem 0; }
.lesson-shell .lesson-content .alert-info { background: #eff6ff; border-left: 4px solid #2563eb; color: #1d4ed8; }
.lesson-shell .lesson-content-inner { font-size: var(--lesson-content-font-size); max-width: var(--lesson-content-width); width: 100%; margin: 0 auto; }
.lesson-shell .bg-light { background-color: var(--lesson-surface) !important; color: var(--lesson-text); }
.lesson-shell .border, .lesson-shell .form-control, .lesson-shell .form-select { border-color: var(--lesson-border) !important; }
body[data-theme="dark"] .lesson-shell .lesson-content .alert-info { background: rgba(37,99,235,0.18); color: #bfdbfe; }
body[data-theme="dark"] .lesson-shell .text-muted { color: var(--lesson-muted) !important; }
</style>
<div id="readingProgress"></div>

<div class="bg-light p-4 rounded shadow-sm lesson-shell">

<?php $progress_percent = $total_lessons ? (int) round(($completed_lessons / $total_lessons) * 100) : 0; ?>
<?php $reading_time = !empty($lesson['reading_time']) ? (int) $lesson['reading_time'] : max(1, (int) ceil((preg_match_all('/\p{L}+/u', strip_tags($lesson['content'] ?? ''), $matches) ? count($matches[0]) : 0) / 180)); ?>
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div>Progress: <strong><?= $completed_lessons ?>/<?= $total_lessons ?> (<?= $progress_percent ?>%)</strong></div>
        <div class="small text-muted">Lesson: <?= htmlspecialchars($lesson['title']) ?></div>
    </div>
    <div class="progress" style="height:8px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress_percent ?>%"></div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h3 class="mb-1"><?= htmlspecialchars($lesson['title']) ?></h3>
        <p class="text-muted mb-0">Instructor: <?= htmlspecialchars($lesson['instructor_name']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge bg-primary-subtle text-primary">≈ <?= $reading_time ?> min read</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bookmarkLesson">🔖 Bookmark</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleTheme">🌙 Dark mode</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label small text-muted">Font size</label>
        <input type="range" min="15" max="22" step="1" value="17" id="fontSizeRange" class="form-range">
    </div>
    <div class="col-md-6">
        <label class="form-label small text-muted">Reading width</label>
        <input type="range" min="720" max="1200" step="40" value="920" id="widthRange" class="form-range">
    </div>
</div>

<?php if (!$hasVideo && $hasText): ?>
    <div class="alert alert-info">This is a text-first lesson designed for focused reading and review.</div>
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
<article class="lesson-content p-3 rounded shadow-sm mb-3" style="max-width:100%;">
    <header class="mb-3">
        <h4 class="fw-bold mb-2">Lesson Notes</h4>
        <p class="text-muted mb-0">Readable, structured lessons with quick navigation and mobile-friendly spacing.</p>
    </header>

    <div id="toc" class="mb-3 p-3 border rounded bg-light"></div>

    <div id="lessonContent" class="lesson-content-inner" style="font-size:17px; max-width:920px; margin:0 auto;">
        <?= $lesson['content'] ?>
    </div>

    <div class="mt-4">
        <label class="form-label">Notes</label>
        <textarea id="lessonNotes" class="form-control" rows="4" placeholder="Capture ideas, questions, or key takeaways from this lesson."></textarea>
    </div>

    <?php if (!empty($progress['completed'])): ?>
        <div class="alert alert-success mt-3">This lesson is already completed.</div>
    <?php else: ?>
        <button id="textMarkComplete" class="btn btn-success mt-3">Mark Lesson Complete</button>
    <?php endif; ?>
</article>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const content = document.getElementById('lessonContent');
    const toc = document.getElementById('toc');
    const notesBox = document.getElementById('lessonNotes');
    const bookmarkBtn = document.getElementById('bookmarkLesson');
    const themeBtn = document.getElementById('toggleTheme');
    const fontRange = document.getElementById('fontSizeRange');
    const widthRange = document.getElementById('widthRange');
    const lessonId = <?= (int) $lesson_id ?>;

    if (content) {
        const headings = content.querySelectorAll('h1, h2, h3');
        if (headings.length > 0) {
            let tocList = "<h5 class='fw-bold'>📘 Table of Contents</h5><ul class='list-unstyled'>";
            headings.forEach((h, i) => {
                const id = 'sec_' + i;
                h.id = id;
                tocList += `<li class='mb-2'><a href='#${id}'>${h.innerText}</a></li>`;
            });
            tocList += '</ul>';
            toc.innerHTML = tocList;

            headings.forEach((h) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'mb-4';
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'btn btn-sm btn-outline-primary mb-2';
                toggleBtn.type = 'button';
                toggleBtn.innerText = 'Collapse section';
                const sectionBlock = document.createElement('div');
                sectionBlock.className = 'border rounded p-3';
                sectionBlock.appendChild(h.cloneNode(true));
                let next = h.nextElementSibling;
                while (next && !['H1','H2','H3'].includes(next.tagName)) {
                    const toMove = next;
                    next = next.nextElementSibling;
                    sectionBlock.appendChild(toMove);
                }
                wrapper.appendChild(toggleBtn);
                wrapper.appendChild(sectionBlock);
                toggleBtn.addEventListener('click', () => {
                    sectionBlock.classList.toggle('d-none');
                    toggleBtn.innerText = sectionBlock.classList.contains('d-none') ? 'Expand section' : 'Collapse section';
                });
                content.insertBefore(wrapper, h);
            });
        }
    }

    const saveNote = () => {
        if (notesBox) localStorage.setItem('lesson-notes-' + lessonId, notesBox.value);
    };
    if (notesBox) {
        notesBox.value = localStorage.getItem('lesson-notes-' + lessonId) || '';
        notesBox.addEventListener('input', saveNote);
    }

    const bookmarkKey = 'lesson-bookmark-' + lessonId;
    const isBookmarked = localStorage.getItem(bookmarkKey) === '1';
    if (bookmarkBtn) {
        bookmarkBtn.textContent = isBookmarked ? '✓ Bookmarked' : '🔖 Bookmark';
        bookmarkBtn.addEventListener('click', () => {
            const now = localStorage.getItem(bookmarkKey) === '1' ? '0' : '1';
            localStorage.setItem(bookmarkKey, now);
            bookmarkBtn.textContent = now === '1' ? '✓ Bookmarked' : '🔖 Bookmark';
        });
    }

    const themeKey = 'lesson-theme';
    const applyTheme = (theme) => {
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        if (themeBtn) themeBtn.textContent = theme === 'dark' ? '☀️ Light mode' : '🌙 Dark mode';
        localStorage.setItem(themeKey, theme);
    };
    const savedTheme = localStorage.getItem(themeKey) || 'light';
    applyTheme(savedTheme);
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
        });
    }

    const fontKey = 'lesson-font-size';
    const widthKey = 'lesson-width';
    const applyPrefs = () => {
        const fontSize = parseInt(localStorage.getItem(fontKey) || '17', 10);
        const width = parseInt(localStorage.getItem(widthKey) || '920', 10);
        const safeFontSize = Math.min(22, Math.max(15, fontSize));
        const safeWidth = Math.min(1200, Math.max(720, width));
        document.documentElement.style.setProperty('--lesson-content-font-size', safeFontSize + 'px');
        document.documentElement.style.setProperty('--lesson-content-width', safeWidth + 'px');
        if (content) {
            content.style.fontSize = safeFontSize + 'px';
            content.style.maxWidth = safeWidth + 'px';
            content.style.width = '100%';
        }
        if (fontRange) fontRange.value = safeFontSize;
        if (widthRange) widthRange.value = safeWidth;
    };
    applyPrefs();
    if (fontRange) {
        fontRange.addEventListener('input', () => {
            const value = fontRange.value;
            document.documentElement.style.setProperty('--lesson-content-font-size', value + 'px');
            if (content) {
                content.style.fontSize = value + 'px';
            }
            localStorage.setItem(fontKey, value);
        });
    }
    if (widthRange) {
        widthRange.addEventListener('input', () => {
            const value = widthRange.value;
            document.documentElement.style.setProperty('--lesson-content-width', value + 'px');
            if (content) {
                content.style.maxWidth = value + 'px';
                content.style.width = '100%';
            }
            localStorage.setItem(widthKey, value);
        });
    }

    const readingBar = document.getElementById('readingProgress');
    document.addEventListener('scroll', () => {
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = height > 0 ? (scrollTop / height) * 100 : 0;
        if (readingBar) readingBar.style.width = progress + '%';
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
