<?php
/**
 * LessonRepository — All lesson DB queries.
 */
require_once __DIR__ . '/BaseRepository.php';

class LessonRepository extends BaseRepository {

    /**
     * Get lesson by ID with course info.
     */
    public function getLessonById($lesson_id) {
        return $this->fetchOne(
            "SELECT l.*, c.title AS course_title, c.id AS course_id, c.type, u.name AS instructor_name
             FROM lessons l
             JOIN courses c ON l.course_id = c.id
             JOIN users u ON c.instructor_id = u.id
             WHERE l.id = ? LIMIT 1",
            [$lesson_id]
        );
    }

    /**
     * Get all lessons for a course, ordered by lesson order.
     */
    public function getLessonsByCourseId($course_id) {
        return $this->fetchAll(
            "SELECT id, title, order_no, duration, status
             FROM lessons
             WHERE course_id = ? AND status = 'published'
             ORDER BY order_no ASC, id ASC",
            [$course_id]
        );
    }

    /**
     * Get lesson with user progress (for authenticated user).
     */
    public function getLessonWithProgress($lesson_id, $user_id) {
        $lesson = $this->getLessonById($lesson_id);
        if (!$lesson) return null;

        $progress = $this->fetchOne(
            "SELECT completed, completed_at FROM lesson_progress
             WHERE lesson_id = ? AND user_id = ? LIMIT 1",
            [$lesson_id, $user_id]
        );

        $lesson['progress'] = $progress ?: ['completed' => 0, 'completed_at' => null];
        return $lesson;
    }

    /**
     * Get attachments for a lesson.
     */
    public function getLessonAttachments($lesson_id) {
        return $this->fetchAll(
            "SELECT id, file_name, file_path, file_type FROM lesson_attachments
             WHERE lesson_id = ? ORDER BY created_at ASC",
            [$lesson_id]
        );
    }

    /**
     * Get lesson test (if exists).
     */
    public function hasLessonTest($lesson_id) {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM lesson_tests WHERE lesson_id = ? LIMIT 1",
            [$lesson_id]
        ) > 0;
    }

    /**
     * Get previous and next lesson IDs.
     */
    public function getAdjacentLessonIds($course_id, $current_lesson_id) {
        $lessons = $this->getLessonsByCourseId($course_id);
        $ids = array_column($lessons, 'id');
        $current_index = array_search($current_lesson_id, $ids);

        return [
            'prev' => $lessons[$current_index - 1]['id'] ?? null,
            'next' => $lessons[$current_index + 1]['id'] ?? null,
        ];
    }

    /**
     * Create lesson attachment record.
     */
    public function createAttachment($lesson_id, $file_name, $file_path, $file_type = 'application/octet-stream') {
        $this->execute(
            "INSERT INTO lesson_attachments (lesson_id, file_name, file_path, file_type, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$lesson_id, $file_name, $file_path, $file_type]
        );
        return $this->lastInsertId();
    }
}
