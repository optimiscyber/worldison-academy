<?php
/**
 * LessonService — Business logic for lessons.
 * No SQL, no HTML. Uses repositories.
 */
require_once __DIR__ . '/../Repositories/LessonRepository.php';
require_once __DIR__ . '/../Repositories/EnrollmentRepository.php';
require_once __DIR__ . '/../Repositories/ProgressRepository.php';
require_once __DIR__ . '/../Repositories/CourseRepository.php';

class LessonService {
    private $lessonRepo;
    private $enrollmentRepo;
    private $progressRepo;
    private $courseRepo;

    public function __construct($pdo) {
        $this->lessonRepo = new LessonRepository($pdo);
        $this->enrollmentRepo = new EnrollmentRepository($pdo);
        $this->progressRepo = new ProgressRepository($pdo);
        $this->courseRepo = new CourseRepository($pdo);
    }

    /**
     * Get lesson for authenticated user (with access checks).
     * Returns: lesson + progress + adjacent lesson IDs + attachments.
     */
    public function getLessonForUser($lesson_id, $user_id) {
        $lesson = $this->lessonRepo->getLessonById($lesson_id);
        if (!$lesson) return null;

        $course_id = $lesson['course_id'];

        // Access check: user must be enrolled (or course is free)
        if ($lesson['type'] === 'paid' && !$this->enrollmentRepo->isEnrolled($user_id, $course_id)) {
            return null; // Access denied
        }

        // Get or create progress
        $progress = $this->progressRepo->getOrCreateProgress($user_id, $lesson_id, $course_id);
        $lesson['progress'] = $progress;

        // Adjacent lessons
        $adjacent = $this->lessonRepo->getAdjacentLessonIds($course_id, $lesson_id);
        $lesson['prev_id'] = $adjacent['prev'];
        $lesson['next_id'] = $adjacent['next'];

        // Check for test
        $lesson['hasTest'] = $this->lessonRepo->hasLessonTest($lesson_id);

        // Course progress
        $total_lessons = $this->courseRepo->getTotalLessonCount($course_id);
        $completed_lessons = $this->progressRepo->getCompletedLessonCount($user_id, $course_id);
        $lesson['course_progress'] = [
            'total' => $total_lessons,
            'completed' => $completed_lessons,
            'percent' => $this->progressRepo->getCourseProgressPercent($user_id, $course_id, $total_lessons),
        ];

        // Attachments
        $lesson['attachments'] = $this->lessonRepo->getLessonAttachments($lesson_id);

        return $lesson;
    }

    /**
     * Get lessons for course (for sidebar/listing).
     */
    public function getCourseLessons($course_id, $user_id = null) {
        $lessons = $this->lessonRepo->getLessonsByCourseId($course_id);

        if ($user_id) {
            $lesson_ids = array_column($lessons, 'id');
            $progress_map = [];
            if (!empty($lesson_ids)) {
                $progress = $this->progressRepo->getLessonsProgress($user_id, $lesson_ids);
                foreach ($progress as $p) {
                    $progress_map[(int)$p['lesson_id']] = (int)$p['completed'];
                }
            }

            foreach ($lessons as &$l) {
                $l['completed'] = $progress_map[(int)$l['id']] ?? 0;
            }
        }

        return $lessons;
    }

    /**
     * Mark lesson complete and return updated course progress.
     */
    public function markLessonComplete($lesson_id, $user_id) {
        $lesson = $this->lessonRepo->getLessonById($lesson_id);
        if (!$lesson) return null;

        $this->progressRepo->markComplete($user_id, $lesson_id);

        $course_id = $lesson['course_id'];
        $total = $this->courseRepo->getTotalLessonCount($course_id);
        $completed = $this->progressRepo->getCompletedLessonCount($user_id, $course_id);
        $course_completed = $this->progressRepo->isCoursesCompleted($user_id, $course_id, $total);

        return [
            'completed' => true,
            'completed_lessons' => $completed,
            'total_lessons' => $total,
            'percent' => $this->progressRepo->getCourseProgressPercent($user_id, $course_id, $total),
            'course_completed' => $course_completed,
        ];
    }
}
