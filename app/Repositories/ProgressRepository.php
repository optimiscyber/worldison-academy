<?php
/**
 * ProgressRepository — All lesson progress DB queries.
 */
require_once __DIR__ . '/BaseRepository.php';

class ProgressRepository extends BaseRepository {

    /**
     * Get or create lesson progress for user.
     */
    public function getOrCreateProgress($user_id, $lesson_id, $course_id) {
        $progress = $this->fetchOne(
            "SELECT * FROM lesson_progress WHERE user_id = ? AND lesson_id = ? LIMIT 1",
            [$user_id, $lesson_id]
        );

        if (!$progress) {
            $this->execute(
                "INSERT INTO lesson_progress (user_id, lesson_id, course_id, completed, completed_at)
                 VALUES (?, ?, ?, 0, NULL)",
                [$user_id, $lesson_id, $course_id]
            );
            $progress = ['completed' => 0, 'completed_at' => null];
        }

        return $progress;
    }

    /**
     * Mark lesson as completed.
     */
    public function markComplete($user_id, $lesson_id) {
        $this->execute(
            "UPDATE lesson_progress SET completed = 1, completed_at = NOW()
             WHERE user_id = ? AND lesson_id = ?",
            [$user_id, $lesson_id]
        );
        return true;
    }

    /**
     * Get progress for user in course (all lessons).
     */
    public function getCourseProgress($user_id, $course_id) {
        return $this->fetchAll(
            "SELECT lesson_id, completed, completed_at FROM lesson_progress
             WHERE user_id = ? AND course_id = ?",
            [$user_id, $course_id]
        );
    }

    /**
     * Get completed lesson count for course.
     */
    public function getCompletedLessonCount($user_id, $course_id) {
        return (int) $this->fetchColumn(
            "SELECT COUNT(*) FROM lesson_progress
             WHERE user_id = ? AND course_id = ? AND completed = 1",
            [$user_id, $course_id]
        );
    }

    /**
     * Get total course progress percentage.
     */
    public function getCourseProgressPercent($user_id, $course_id, $total_lessons) {
        if ($total_lessons === 0) return 0;
        $completed = $this->getCompletedLessonCount($user_id, $course_id);
        return (int) round(($completed / $total_lessons) * 100);
    }

    /**
     * Check if user completed entire course.
     */
    public function isCoursesCompleted($user_id, $course_id, $total_lessons) {
        $completed = $this->getCompletedLessonCount($user_id, $course_id);
        return $completed >= $total_lessons;
    }

    /**
     * Get user's lesson progress for multiple lessons.
     */
    public function getLessonsProgress($user_id, $lesson_ids) {
        if (empty($lesson_ids)) return [];
        $in = implode(',', array_fill(0, count($lesson_ids), '?'));
        $params = array_merge([$user_id], $lesson_ids);
        return $this->fetchAll(
            "SELECT lesson_id, completed FROM lesson_progress
             WHERE user_id = ? AND lesson_id IN ($in)",
            $params
        );
    }
}
