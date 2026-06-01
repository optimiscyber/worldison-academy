<?php
/**
 * EnrollmentService — Business logic for enrollment.
 * No SQL, no HTML. Uses repositories.
 */
require_once __DIR__ . '/../Repositories/EnrollmentRepository.php';
require_once __DIR__ . '/../Repositories/CourseRepository.php';

class EnrollmentService {
    private $enrollmentRepo;
    private $courseRepo;

    public function __construct($pdo) {
        $this->enrollmentRepo = new EnrollmentRepository($pdo);
        $this->courseRepo = new CourseRepository($pdo);
    }

    /**
     * Enroll user in course (only for free courses).
     */
    public function enrollFree($user_id, $course_id) {
        // Check course type
        $type = $this->courseRepo->getCourseType($course_id);
        if ($type !== 'free') {
            return ['success' => false, 'error' => 'This course is not free.'];
        }

        // Check already enrolled
        if ($this->enrollmentRepo->isEnrolled($user_id, $course_id)) {
            return ['success' => false, 'error' => 'Already enrolled.'];
        }

        // Enroll
        $this->enrollmentRepo->enroll($user_id, $course_id);
        return ['success' => true, 'message' => 'Enrolled successfully.'];
    }

    /**
     * Check if user is enrolled.
     */
    public function isEnrolled($user_id, $course_id) {
        return $this->enrollmentRepo->isEnrolled($user_id, $course_id);
    }

    /**
     * Get user's enrolled courses.
     */
    public function getUserEnrolledCourses($user_id) {
        return $this->enrollmentRepo->getUserEnrolledCourses($user_id);
    }
}
