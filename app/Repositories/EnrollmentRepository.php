<?php
/**
 * EnrollmentRepository — All enrollment DB queries.
 */
require_once __DIR__ . '/BaseRepository.php';

class EnrollmentRepository extends BaseRepository {

    /**
     * Check if user is enrolled in course.
     */
    public function isEnrolled($user_id, $course_id) {
        return (bool) $this->fetchColumn(
            "SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1",
            [$user_id, $course_id]
        );
    }

    /**
     * Enroll user in course.
     */
    public function enroll($user_id, $course_id) {
        $this->execute(
            "INSERT INTO enrollments (user_id, course_id, enrolled_at, status)
             VALUES (?, ?, NOW(), 'active')",
            [$user_id, $course_id]
        );
        return $this->lastInsertId();
    }

    /**
     * Get user's enrolled courses.
     */
    public function getUserEnrolledCourses($user_id) {
        return $this->fetchAll(
            "SELECT c.*, u.name AS instructor_name, u.profile_picture
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             JOIN users u ON c.instructor_id = u.id
             WHERE e.user_id = ? AND e.status = 'active'
             ORDER BY e.enrolled_at DESC",
            [$user_id]
        );
    }

    /**
     * Get enrollment record.
     */
    public function getEnrollment($user_id, $course_id) {
        return $this->fetchOne(
            "SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1",
            [$user_id, $course_id]
        );
    }

    /**
     * Get enrollments for a course.
     */
    public function getCourseEnrollments($course_id) {
        return $this->fetchAll(
            "SELECT e.*, u.name, u.email FROM enrollments e
             JOIN users u ON e.user_id = u.id
             WHERE e.course_id = ? AND e.status = 'active'
             ORDER BY e.enrolled_at DESC",
            [$course_id]
        );
    }

    /**
     * Count course enrollments.
     */
    public function countCourseEnrollments($course_id) {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'",
            [$course_id]
        );
    }
}
