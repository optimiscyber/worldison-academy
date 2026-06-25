<?php
/**
 * One-time deployment migration for lesson text support and progress indexes.
 * Run with: php8.3 migrations/001_lesson_text_support.php
 */

require_once __DIR__ . '/../inc/db.php';

$lessonColumns = $pdo->query("SHOW COLUMNS FROM lessons")->fetchAll(PDO::FETCH_ASSOC);
$lessonColumnNames = array_column($lessonColumns, 'Field');

if (!in_array('reading_time', $lessonColumnNames, true)) {
    $pdo->exec("ALTER TABLE lessons ADD COLUMN reading_time INT NOT NULL DEFAULT 0");
    echo "Applied: ALTER TABLE lessons ADD COLUMN reading_time INT NOT NULL DEFAULT 0\n";
} else {
    echo "Skipped: reading_time already exists\n";
}

if (!in_array('content_format', $lessonColumnNames, true)) {
    $pdo->exec("ALTER TABLE lessons ADD COLUMN content_format VARCHAR(20) NOT NULL DEFAULT 'html'");
    echo "Applied: ALTER TABLE lessons ADD COLUMN content_format VARCHAR(20) NOT NULL DEFAULT 'html'\n";
} else {
    echo "Skipped: content_format already exists\n";
}

$lessonIndexes = $pdo->query("SHOW INDEX FROM lessons")->fetchAll(PDO::FETCH_ASSOC);
$lessonIndexNames = array_column($lessonIndexes, 'Key_name');

if (!in_array('idx_lessons_course_order', $lessonIndexNames, true)) {
    $pdo->exec("ALTER TABLE lessons ADD INDEX idx_lessons_course_order (course_id, status, order_no, id)");
    echo "Applied: ALTER TABLE lessons ADD INDEX idx_lessons_course_order (course_id, status, order_no, id)\n";
} else {
    echo "Skipped: idx_lessons_course_order already exists\n";
}

$progressIndexes = $pdo->query("SHOW INDEX FROM lesson_progress")->fetchAll(PDO::FETCH_ASSOC);
$progressIndexNames = array_column($progressIndexes, 'Key_name');

if (!in_array('idx_lesson_progress_user_lesson', $progressIndexNames, true)) {
    $pdo->exec("ALTER TABLE lesson_progress ADD INDEX idx_lesson_progress_user_lesson (user_id, lesson_id)");
    echo "Applied: ALTER TABLE lesson_progress ADD INDEX idx_lesson_progress_user_lesson (user_id, lesson_id)\n";
} else {
    echo "Skipped: idx_lesson_progress_user_lesson already exists\n";
}

if (!in_array('idx_lesson_progress_course_user', $progressIndexNames, true)) {
    $pdo->exec("ALTER TABLE lesson_progress ADD INDEX idx_lesson_progress_course_user (course_id, user_id, completed)");
    echo "Applied: ALTER TABLE lesson_progress ADD INDEX idx_lesson_progress_course_user (course_id, user_id, completed)\n";
} else {
    echo "Skipped: idx_lesson_progress_course_user already exists\n";
}
