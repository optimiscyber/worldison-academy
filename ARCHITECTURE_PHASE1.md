# LMS Architecture Refactor – Phase 1 Complete

## ✅ What Was Created

### Directory Structure
```
/app/
  /Repositories/
    BaseRepository.php
    CourseRepository.php
    LessonRepository.php
    EnrollmentRepository.php
    ProgressRepository.php
    UserRepository.php
  /Services/
    CourseService.php
    LessonService.php
    EnrollmentService.php
/api/
  _bootstrap.php
  course.php
  lesson.php
  progress.php
  enroll.php
/public/
  (placeholder for Phase 3)
```

### Repositories (Data Access Layer)
Each repository inherits from `BaseRepository` and encapsulates ALL SQL queries for a domain.

- **CourseRepository** — All course queries (getCourseById, getPublishedCourses, getTotalLessonCount, etc.)
- **LessonRepository** — All lesson queries (getLessonById, getLessonsByCourseId, hasLessonTest, etc.)
- **EnrollmentRepository** — All enrollment queries (isEnrolled, enroll, getUserEnrolledCourses, etc.)
- **ProgressRepository** — All progress queries (getOrCreateProgress, markComplete, getCompletedLessonCount, etc.)
- **UserRepository** — User queries (getUserById, updateProfilePicture, etc.)

### Services (Business Logic Layer)
Each service uses repositories (NO SQL, NO HTML) to implement business rules.

- **CourseService** — getCourseDetail (with enrollment + progress), getPublishedCourses, canUserAccessCourse
- **LessonService** — getLessonForUser (with access checks), getCourseLessons, markLessonComplete
- **EnrollmentService** — enrollFree, isEnrolled, getUserEnrolledCourses

### API Endpoints (/api/)
Stateless HTTP endpoints using services. All respond with JSON.

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/course.php?id=123` | GET | No | Get course details |
| `/api/lesson.php?id=456` | GET | Yes | Get lesson with progress |
| `/api/progress.php` | POST | Yes | Mark lesson complete |
| `/api/enroll.php` | POST | Yes | Enroll in free course |

---

## 📋 Phase 2: Using the API from Existing Pages (NEXT STEP)

Update `admin/ajax/mark_complete.php` to use the new API:

```php
<?php
session_start();
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../app/Services/LessonService.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$lesson_id = (int) ($_POST['lesson_id'] ?? 0);

if (!$lesson_id) {
    json_response(['ok' => false, 'error' => 'Lesson ID required'], 400);
}

$lessonService = new LessonService($pdo);
$result = $lessonService->markLessonComplete($lesson_id, $user_id);

if ($result) {
    echo json_encode(['ok' => true, ...$result]);
} else {
    echo json_encode(['ok' => false, 'error' => 'Failed']);
}
```

Update `course.php` to use `CourseService`:

```php
<?php
session_start();
require_once 'inc/db.php';
require_once 'app/Services/CourseService.php';
require_once 'app/Services/LessonService.php';

$course_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'] ?? null;

$courseService = new CourseService($pdo);
$lessonService = new LessonService($pdo);

$course = $courseService->getCourseDetail($course_id, $user_id);
$lessons = $lessonService->getCourseLessons($course_id, $user_id);

// Render HTML using $course and $lessons
?>
```

---

## 🔄 Migration Checklist

### Step 1: Validate Phase 1
- [ ] Run `php -l app/Repositories/*.php` ✅
- [ ] Run `php -l app/Services/*.php` ✅
- [ ] Run `php -l api/*.php` ✅
- [ ] Test API endpoints manually (see testing section below)

### Step 2: Update AJAX Endpoints
- [ ] `admin/ajax/mark_complete.php` → use LessonService
- [ ] `admin/ajax/enroll.php` (if exists) → use EnrollmentService

### Step 3: Refactor Existing Pages (Incremental)
- [ ] `course.php` → use CourseService + LessonService
- [ ] `courses.php` → use CourseService
- [ ] `index.php` → use CourseService
- [ ] `admin/watch_lesson.php` → use LessonService + ProgressService
- [ ] `admin/course-details.php` → use CourseService + LessonService
- [ ] Old pages stay functional; update only the queries they call

### Step 4: Create Public Wrappers (Phase 3)
- [ ] `/public/course.php` → cleaner public interface
- [ ] `/public/watch-lesson.php` → no /admin/ redirect
- [ ] `/public/enroll.php` → free enroll flow

---

## 🧪 Testing

### Test API Endpoints

**1. Test course endpoint (no auth needed):**
```bash
curl http://localhost/api/course.php?id=1
```

Expected response:
```json
{
  "ok": true,
  "data": {
    "id": 1,
    "title": "PHP Basics",
    "description": "...",
    "instructor_name": "John Doe",
    "type": "free",
    "price": 0,
    ...
  }
}
```

**2. Test lesson endpoint (requires auth):**
```bash
# First login and get session cookie, then:
curl -b cookies.txt http://localhost/api/lesson.php?id=5
```

Expected response:
```json
{
  "ok": true,
  "data": {
    "id": 5,
    "title": "Lesson Title",
    "content": "...",
    "video_url": "...",
    "progress": { "completed": 0 },
    "prev_id": 4,
    "next_id": 6,
    "hasTest": false,
    "course_progress": {
      "total": 10,
      "completed": 3,
      "percent": 30
    },
    "attachments": [...]
  }
}
```

**3. Test progress endpoint:**
```bash
curl -X POST -b cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"lesson_id": 5}' \
  http://localhost/api/progress.php
```

Expected response:
```json
{
  "ok": true,
  "data": {
    "completed": true,
    "completed_lessons": 4,
    "total_lessons": 10,
    "percent": 40,
    "course_completed": false
  }
}
```

**4. Test enroll endpoint:**
```bash
curl -X POST -b cookies.txt \
  -H "Content-Type: application/json" \
  -d '{"course_id": 2}' \
  http://localhost/api/enroll.php
```

---

## 🛠️ Now What?

### Option A: Quick Win (1-2 hours)
1. Update `admin/ajax/mark_complete.php` to use `LessonService`
2. Update `course.php` to use `CourseService`
3. Test existing flows still work

### Option B: Full Refactor (4-6 hours)
1. Update all AJAX endpoints
2. Refactor `course.php`, `courses.php`, `index.php`
3. Refactor `admin/watch_lesson.php`
4. Create `/public/` wrappers

### Option C: Gradual (ongoing)
1. Leave old pages as-is (backward compat)
2. New features use services + API
3. Refactor pages opportunistically

---

## ✨ Benefits After Phase 1+2

1. **Testable** — Services are pure functions, no DB/session coupling
2. **Reusable** — CourseService used by web UI, mobile API, CLI, etc.
3. **Maintainable** — SQL in repositories, logic in services, no duplication
4. **Scalable** — Clear separation of concerns; easy to add caching, logging, etc.
5. **Flexible** — Can swap UI (PHP templates → React) without touching services
6. **Safe** — All old pages keep working; new code coexists with old code

---

## 📝 Key Principles

- **No SQL in services** — repositories own all queries
- **No HTML in services** — services return pure data
- **No external dependencies** — services only use repositories + PHP built-ins
- **No breaking changes** — old pages remain functional
- **Incremental** — one page refactored at a time
- **Backward compatible** — old code and new code live together
