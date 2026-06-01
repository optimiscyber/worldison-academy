<?php
/**
 * CourseRepository — All course DB queries.
 */
require_once __DIR__ . '/BaseRepository.php';

class CourseRepository extends BaseRepository {

    /**
     * Get published course by ID with instructor info.
     */
    public function getCourseById($course_id) {
        return $this->fetchOne(
            "SELECT c.*, u.name AS instructor_name, u.profile_picture, u.bio
             FROM courses c
             JOIN users u ON c.instructor_id = u.id
             WHERE c.id = ? AND c.status = 'published' LIMIT 1",
            [$course_id]
        );
    }

    /**
     * Get all published courses with pagination.
     */
    public function getPublishedCourses($limit = 20, $offset = 0) {
        return $this->fetchAll(
            "SELECT c.*, u.name AS instructor_name, u.profile_picture, cat.name AS category_name
             FROM courses c
             JOIN users u ON c.instructor_id = u.id
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE c.status = 'published'
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Count published courses.
     */
    public function countPublishedCourses() {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM courses WHERE status = 'published'"
        );
    }

    /**
     * Get courses by category (published).
     */
    public function getCoursesByCategory($category_id, $limit = 20, $offset = 0) {
        return $this->fetchAll(
            "SELECT c.*, u.name AS instructor_name, u.profile_picture, cat.name AS category_name
             FROM courses c
             JOIN users u ON c.instructor_id = u.id
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE c.category_id = ? AND c.status = 'published'
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?",
            [$category_id, $limit, $offset]
        );
    }

    /**
     * Get courses by instructor ID.
     */
    public function getCoursesByInstructor($instructor_id) {
        return $this->fetchAll(
            "SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC",
            [$instructor_id]
        );
    }

    /**
     * Get course type (paid/free).
     */
    public function getCourseType($course_id) {
        return $this->fetchColumn(
            "SELECT type FROM courses WHERE id = ? LIMIT 1",
            [$course_id]
        );
    }

    /**
     * Get category name by course ID.
     */
    public function getCategoryByCourseId($course_id) {
        return $this->fetchColumn(
            "SELECT cat.name FROM categories cat
             JOIN courses c ON c.category_id = cat.id
             WHERE c.id = ? LIMIT 1",
            [$course_id]
        );
    }

    /**
     * Get total lesson count for a course.
     */
    public function getTotalLessonCount($course_id) {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM lessons WHERE course_id = ?",
            [$course_id]
        );
    }
}
