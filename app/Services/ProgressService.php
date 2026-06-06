<?php
/**
 * ProgressService — Business logic for marking and querying progress.
 */
require_once __DIR__ . '/../Repositories/ProgressRepository.php';
require_once __DIR__ . '/../Repositories/LessonRepository.php';
require_once __DIR__ . '/../Repositories/CourseRepository.php';

class ProgressService {
    private $progressRepo;
    private $lessonRepo;
    private $courseRepo;

    public function __construct($pdo) {
        $this->progressRepo = new ProgressRepository($pdo);
        $this->lessonRepo = new LessonRepository($pdo);
        $this->courseRepo = new CourseRepository($pdo);
    }

    /**
     * Mark a lesson complete for a user and return course progress summary.
     */
    public function markLessonComplete($lesson_id, $user_id) {
        $lesson = $this->lessonRepo->getLessonById($lesson_id);
        if (!$lesson) return null;

        $course_id = (int)$lesson['course_id'];

        // Ensure progress row exists then mark complete
        $this->progressRepo->getOrCreateProgress($user_id, $lesson_id, $course_id);
        $this->progressRepo->markComplete($user_id, $lesson_id);

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
