<?php
/**
 * DashboardService — Business-facing dashboard data.
 */
require_once __DIR__ . '/../Repositories/DashboardRepository.php';

class DashboardService {
    private $dashboardRepo;

    public function __construct($pdo) {
        $this->dashboardRepo = new DashboardRepository($pdo);
    }

    public function getAdminOverview() {
        $earningTotals = $this->dashboardRepo->sumInstructorEarnings();

        return [
            'total_users' => $this->dashboardRepo->countUsers(),
            'total_instructors' => $this->dashboardRepo->countUsersByRole('instructor'),
            'total_students' => $this->dashboardRepo->countUsersByRole('student'),
            'total_courses' => $this->dashboardRepo->countCourses(),
            'published_courses' => $this->dashboardRepo->countCourses('published'),
            'active_enrollments' => $this->dashboardRepo->getEnrollmentCount('active'),
            'total_revenue' => $this->dashboardRepo->sumTransactionsByStatus('success'),
            'platform_earnings' => $earningTotals['platform_total'] ?? 0,
            'instructor_earnings' => $earningTotals['instructor_total'] ?? 0,
            'recent_enrollments' => $this->dashboardRepo->getRecentEnrollments(10),
            'top_courses' => $this->dashboardRepo->getTopCoursesByEnrollments(6),
        ];
    }

    public function getInstructorOverview($instructor_id) {
        return [
            'courses_count' => $this->dashboardRepo->countInstructorCourses($instructor_id),
            'students_count' => $this->dashboardRepo->countInstructorStudents($instructor_id),
            'earnings_summary' => $this->dashboardRepo->getInstructorEarningsSummary($instructor_id),
            'earnings_this_month' => $this->dashboardRepo->sumInstructorEarningsThisMonth($instructor_id),
        ];
    }

    public function getStudentOverview($student_id) {
        return [
            'in_progress_courses' => $this->dashboardRepo->getStudentInProgressCourses($student_id, 10),
            'certificates' => $this->dashboardRepo->getStudentCertificates($student_id, 10),
        ];
    }

    public function getMonthlyChartData($months = 6) {
        $labels = [];
        $revenue = [];
        $enrollments = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = new DateTime("-{$i} months");
            $yearMonth = $date->format('Y-m');
            $labels[] = $date->format('M Y');
            $revenue[] = $this->dashboardRepo->getMonthlyRevenue($yearMonth);
            $enrollments[] = $this->dashboardRepo->getMonthlyEnrollments($yearMonth);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'enrollments' => $enrollments,
        ];
    }
}
