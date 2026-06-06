<?php
/**
 * DashboardRepository — Central dashboard query layer.
 */
require_once __DIR__ . '/BaseRepository.php';

class DashboardRepository extends BaseRepository {

    public function countUsers() {
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM users');
    }

    public function countUsersByRole($role) {
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }

    public function countCourses($status = null) {
        if ($status === null) {
            return (int) $this->fetchColumn('SELECT COUNT(*) FROM courses');
        }
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM courses WHERE status = ?', [$status]);
    }

    public function sumTransactionsByStatus($status = 'success') {
        return (float) $this->fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = ?',
            [$status]
        );
    }

    public function sumInstructorEarnings($status = null) {
        if ($status === null) {
            $row = $this->fetchOne(
                'SELECT COALESCE(SUM(platform_share),0) AS platform_total, COALESCE(SUM(instructor_share),0) AS instructor_total FROM instructor_earnings'
            );
            return [
                'platform_total' => (float) ($row['platform_total'] ?? 0),
                'instructor_total' => (float) ($row['instructor_total'] ?? 0),
            ];
        }

        return (float) $this->fetchColumn(
            'SELECT COALESCE(SUM(instructor_share),0) FROM instructor_earnings WHERE status = ?',
            [$status]
        );
    }

    public function getEnrollmentCount($status = 'active') {
        return (int) $this->fetchColumn(
            'SELECT COUNT(*) FROM enrollments WHERE status = ? OR status IS NULL',
            [$status]
        );
    }

    public function getRecentEnrollments($limit = 10) {
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            'SELECT e.enrolled_at, u.name AS student_name, c.title AS course_title
             FROM enrollments e
             JOIN users u ON e.user_id = u.id
             JOIN courses c ON e.course_id = c.id
             ORDER BY e.enrolled_at DESC
             LIMIT ' . $limit
        );
    }

    public function getTopCoursesByEnrollments($limit = 6) {
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            'SELECT c.id, c.title, c.thumbnail, u.name AS instructor_name, COUNT(e.id) AS enroll_count
             FROM courses c
             LEFT JOIN enrollments e ON e.course_id = c.id
             LEFT JOIN users u ON c.instructor_id = u.id
             GROUP BY c.id
             ORDER BY enroll_count DESC
             LIMIT ' . $limit
        );
    }

    public function getInstructorEarningsSummary($instructor_id) {
        if (!$instructor_id) {
            return ['total_earned' => 0, 'pending' => 0, 'paid' => 0];
        }
        return $this->fetchOne(
            'SELECT
                COALESCE(SUM(instructor_share),0) AS total_earned,
                COALESCE(SUM(CASE WHEN status = ? THEN instructor_share ELSE 0 END),0) AS pending,
                COALESCE(SUM(CASE WHEN status = ? THEN instructor_share ELSE 0 END),0) AS paid
             FROM instructor_earnings
             WHERE instructor_id = ?',
            ['pending', 'paid', $instructor_id]
        ) ?: ['total_earned' => 0, 'pending' => 0, 'paid' => 0];
    }

    public function countInstructorCourses($instructor_id) {
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM courses WHERE instructor_id = ?', [$instructor_id]);
    }

    public function countInstructorStudents($instructor_id) {
        return (int) $this->fetchColumn(
            'SELECT COUNT(DISTINCT e.user_id)
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             WHERE c.instructor_id = ? AND (e.status = ? OR e.status IS NULL)',
            [$instructor_id, 'active']
        );
    }

    public function sumInstructorEarningsThisMonth($instructor_id) {
        return (float) $this->fetchColumn(
            'SELECT COALESCE(SUM(instructor_share),0) FROM instructor_earnings
             WHERE instructor_id = ? AND DATE_FORMAT(created_at, "%Y-%m") = DATE_FORMAT(CURRENT_DATE(), "%Y-%m")',
            [$instructor_id]
        );
    }

    public function getStudentInProgressCourses($user_id, $limit = 10) {
        return $this->fetchAll(
            'SELECT c.id, c.title, c.thumbnail,
                COUNT(l.id) AS total_lessons,
                SUM(COALESCE(lp.completed,0)) AS lessons_completed
             FROM enrollments e
             JOIN courses c ON e.course_id = c.id
             LEFT JOIN lessons l ON l.course_id = c.id
             LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = e.user_id
             WHERE e.user_id = ? AND (e.status = ? OR e.status IS NULL)
             GROUP BY c.id
             HAVING total_lessons > 0
             ORDER BY e.enrolled_at DESC
             LIMIT ' . max(1, (int) $limit),
            [$user_id, 'active']
        );
    }

    public function getStudentCertificates($user_id, $limit = 10) {
        $limit = max(1, (int) $limit);
        return $this->fetchAll(
            'SELECT cert.id, cert.certificate_url, cert.created_at, c.title AS course_title
             FROM certificates cert
             LEFT JOIN courses c ON cert.course_id = c.id
             WHERE cert.user_id = ?
             ORDER BY cert.created_at DESC
             LIMIT ' . $limit,
            [$user_id]
        );
    }

    public function getMonthlyRevenue($yearMonth) {
        return (float) $this->fetchColumn(
            'SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = ? AND DATE_FORMAT(created_at, "%Y-%m") = ?',
            ['success', $yearMonth]
        );
    }

    public function getMonthlyEnrollments($yearMonth) {
        return (int) $this->fetchColumn(
            'SELECT COUNT(*) FROM enrollments WHERE DATE_FORMAT(enrolled_at, "%Y-%m") = ?',
            [$yearMonth]
        );
    }
}
