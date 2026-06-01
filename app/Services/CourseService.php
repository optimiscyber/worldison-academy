<?php
/**
 * CourseService — Business logic for courses.
 * No SQL, no HTML. Uses repositories.
 */
require_once __DIR__ . '/../Repositories/CourseRepository.php';
require_once __DIR__ . '/../Repositories/EnrollmentRepository.php';
require_once __DIR__ . '/../Repositories/ProgressRepository.php';

class CourseService {
    private $courseRepo;
    private $enrollmentRepo;
    private $progressRepo;

    public function __construct($pdo) {
        $this->courseRepo = new CourseRepository($pdo);
        $this->enrollmentRepo = new EnrollmentRepository($pdo);
        $this->progressRepo = new ProgressRepository($pdo);
    }

    /**
     * Get course detail for public display or user dashboard.
     * Returns: course data + enrollment status + progress if user logged in.
     */
    public function getCourseDetail($course_id, $user_id = null) {
        $course = $this->courseRepo->getCourseById($course_id);
        if (!$course) return null;

        // Add enrollment and progress info if user provided
        if ($user_id) {
            $course['isEnrolled'] = $this->enrollmentRepo->isEnrolled($user_id, $course_id);
            $total = $this->courseRepo->getTotalLessonCount($course_id);
            $course['total_lessons'] = $total;
            if ($course['isEnrolled']) {
                $course['completed_lessons'] = $this->progressRepo->getCompletedLessonCount($user_id, $course_id);
                $course['progress_percent'] = $this->progressRepo->getCourseProgressPercent($user_id, $course_id, $total);
            }
        }

        return $course;
    }

    /**
     * Get published courses (paginated).
     */
    public function getPublishedCourses($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $courses = $this->courseRepo->getPublishedCourses($limit, $offset);
        $total = $this->courseRepo->countPublishedCourses();

        return [
            'courses' => $courses,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Check if user can access course (enrolled or free).
     */
    public function canUserAccessCourse($user_id, $course_id) {
        $course = $this->courseRepo->getCourseById($course_id);
        if (!$course) return false;

        if ($course['type'] === 'free') return true;
        return $this->enrollmentRepo->isEnrolled($user_id, $course_id);
    }

    /**
     * Get category name.
     */
    public function getCategoryName($course_id) {
        return $this->courseRepo->getCategoryByCourseId($course_id) ?: 'Uncategorized';
    }
}
