# Migration Map — worldison-academy

This document lists candidate SQL queries and where to migrate them into repository classes and service methods.

## CourseRepository
- Move SQL from:
  - admin/upload_course.php — INSERT INTO `courses`, `course_outline`
  - admin/edit_course.php — SELECT/UPDATE `courses`, SELECT `lessons`
  - admin/delete_course.php — SELECT/DELETE `courses`
  - admin/manage_courses.php — SELECT `courses`
  - admin/course-details.php — course joins
  - index.php, courses.php, courses-view.php — public course listings
  - admin/approve_price.php — SELECT/UPDATE `courses`
  - admin/generate_certificate.php — SELECT `title` FROM `courses`

- Suggested methods:
  - getCourseById($id)
  - getCourses($filters = [])
  - createCourse($data)
  - updateCourse($id, $data)
  - deleteCourse($id)
  - getTotalLessonCount($course_id)

## LessonRepository
- Move SQL from:
  - admin/upload_course.php — INSERT INTO `lessons`, `lesson_tests`, `lesson_attachments`
  - admin/add_lesson.php, admin/edit_lesson.php — lesson SELECT/UPDATE
  - admin/delete_lesson.php — SELECT video_url, DELETE `lessons`
  - admin/watch_lesson.php — SELECTs retrieving lesson details
  - admin/manage_courses.php / admin/course-details.php — lesson lists

- Suggested methods:
  - getLessonById($id)
  - getLessonsByCourseId($course_id)
  - getLessonAttachments($lesson_id)
  - hasLessonTest($lesson_id)
  - insertLesson($data)
  - deleteLesson($id)
  - getAdjacentLessonIds($course_id, $lesson_id)

## ProgressRepository
- Move SQL from:
  - admin/ajax/mark_complete.php — INSERT/UPDATE into `lesson_progress`
  - admin/watch_lesson.php — legacy inserts and SELECT COUNT from `lesson_progress`
  - admin/course-details.php, admin/view_courses.php, admin/dashboard.php — progress counts

- Suggested methods:
  - getOrCreateProgress($user_id, $lesson_id, $course_id)
  - getProgressByLesson($user_id, $lesson_id)
  - markComplete($user_id, $lesson_id)
  - getCompletedLessonCount($user_id, $course_id)
  - getCourseProgressPercent($user_id, $course_id, $total_lessons)
  - isCourseCompleted($user_id, $course_id, $total_lessons)

## EnrollmentRepository
- Move SQL from:
  - admin/watch_lesson.php — SELECT 1 FROM `enrollments`
  - admin/enroll.php, admin/enrolled_courses.php, admin/dashboard.php
  - payment/* files that touch `enrollments`

- Suggested methods:
  - isEnrolled($user_id, $course_id)
  - createEnrollment($user_id, $course_id)
  - getEnrollmentsForCourse($course_id)

## UserRepository
- Move SQL from:
  - inc/auth.php, admin/signup.php, admin/manage_users.php, admin/profile.php

- Suggested methods:
  - findById($id)
  - findByEmail($email)
  - createUser($data)
  - updateProfilePicture($id, $path)

## DashboardRepository / DashboardService
- Move SQL from:
  - admin/dashboard.php — metrics, enrollments, charts, certificates, and instructor/student summaries

- Suggested methods:
  - countUsers()
  - countUsersByRole($role)
  - countCourses($status = null)
  - sumTransactionsByStatus($status = 'success')
  - sumInstructorEarnings($status = null)
  - getRecentEnrollments($limit = 10)
  - getTopCoursesByEnrollments($limit = 6)
  - getInstructorEarningsSummary($instructor_id)
  - getStudentInProgressCourses($user_id, $limit = 10)
  - getStudentCertificates($user_id, $limit = 10)
  - getMonthlyRevenue($yearMonth)
  - getMonthlyEnrollments($yearMonth)

## API endpoints (short-term)
- /api/progress.php — mark lesson complete (replaces admin/ajax/mark_complete.php)
- /api/course.php — small read-only course endpoints (optional)

---

Created by Copilot assistant — ready for implementation of the services and API endpoints.
