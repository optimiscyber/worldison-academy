<script>
(function(){
    const lessonId = <?= json_encode($lesson_id) ?>;
    const markUrl = '/api/progress.php';
    const completionNoticeKey = 'lesson-complete-alerted-' + lessonId;
    let isSubmitting = false;
    let completionHandled = sessionStorage.getItem(completionNoticeKey) === '1';

    async function markCompleted() {
        if (isSubmitting || completionHandled) {
            return;
        }

        isSubmitting = true;

        try {
            const response = await fetch(markUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ lesson_id: lessonId })
            });

            let parsed;
            try {
                parsed = await response.json();
            } catch (jsonErr) {
                throw new Error('Invalid response from server');
            }

            // API returns { ok: true, data: { ... } } — normalize to `data` variable
            const data = (parsed && parsed.data) ? parsed.data : parsed;

            if (!response.ok || parsed.ok === false) {
                const errorText = (parsed.error || (data && data.error) || response.statusText || 'Unknown error');
                throw new Error(errorText);
            }

            const textBtn = document.getElementById('textMarkComplete');
            if (textBtn) { textBtn.disabled = true; textBtn.innerText = 'Marked'; }
            const ytBtn = document.getElementById('markCompleteBtn');
            if (ytBtn) { ytBtn.disabled = true; ytBtn.innerText = 'Marked'; }

            try {
                const item = document.querySelector(`[data-lesson-id='${lessonId}']`);
                if (item) {
                    item.classList.remove('locked','disabled');
                    const meta = item.querySelector('.lesson-meta');
                    if (meta) meta.innerHTML = '<span class="badge bg-success">Completed</span>';
                }

                const nextId = <?= json_encode($next_id ?? null) ?>;
                if (nextId) {
                    const nextItem = document.querySelector(`[data-lesson-id='${nextId}']`);
                    if (nextItem) {
                        nextItem.classList.remove('disabled','locked');
                        nextItem.href = 'watch_lesson.php?id=' + nextId;
                    }
                }

                if (data.completed_lessons !== undefined && data.total_lessons !== undefined) {
                    const percent = data.total_lessons ? Math.round((data.completed_lessons / data.total_lessons) * 100) : 0;
                    const bar = document.querySelector('.progress .progress-bar');
                    if (bar) bar.style.width = percent + '%';
                }

                if (data.course_completed) {
                    completionHandled = true;
                    sessionStorage.setItem(completionNoticeKey, '1');
                    alert('🎉 Congrats — you completed all lessons for this course!');
                    location.reload();
                }
            } catch (e) { console.warn(e); }

        } catch(err) {
            console.error(err);
            alert('Failed to mark lesson complete: ' + (err.message || 'Unknown error'));
        } finally {
            if (!completionHandled) {
                isSubmitting = false;
            }
        }
    }

    const textBtn = document.getElementById('textMarkComplete');
    if (textBtn) {
        textBtn.addEventListener('click', markCompleted);
    }

    const videoElement = document.querySelector('video');
    if (videoElement) {
        videoElement.addEventListener('ended', markCompleted, {once:true});
    }

    const ytIframe = document.getElementById('youtubePlayer');
    const markButtonId = 'markCompleteBtn';
    const videoWrapper = document.querySelector('.ratio');

    function addMarkCompleteButton() {
        if (document.getElementById(markButtonId)) {
            return;
        }
        const container = document.createElement('div');
        container.className = 'mt-3';
        container.innerHTML = '<button id="' + markButtonId + '" class="btn btn-success btn-sm">Mark Lesson Complete</button>';
        if (videoWrapper) {
            videoWrapper.parentNode.insertBefore(container, videoWrapper.nextSibling);
        } else {
            document.body.appendChild(container);
        }
        const button = document.getElementById(markButtonId);
        if (button) {
            button.addEventListener('click', markCompleted);
        }
    }

    if (videoWrapper) {
        addMarkCompleteButton();
    }

    if (ytIframe) {
        ytIframe.addEventListener('error', function() {
            const fallback = document.getElementById('youtubeFallback');
            if (fallback) {
                fallback.classList.remove('d-none');
            }
        });
    }

    if (!videoElement && !<?= json_encode($ytID) ?>) {
        let done = false;
        window.addEventListener('scroll', function() {
            const scrollTop = document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const progress = scrollTop / scrollHeight;

            if (!done && progress > 0.7) {
                done = true;
                markCompleted();
            }
        });
    }

})();
</script>

<?php include __DIR__.'/script.php'; ?>
