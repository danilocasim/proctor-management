<?php
require '../includes/auth.php';
require '../includes/db.php';

// Fetch all courses for dropdown
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC")->fetchAll();

// Fetch all distinct year levels from sections for the year dropdown
$years = $pdo->query("SELECT DISTINCT year_level FROM sections ORDER BY year_level ASC")->fetchAll(PDO::FETCH_COLUMN);

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

// Handle add assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assessment']) && $is_admin) {
    $assessment_type = trim($_POST['assessment_type']);
    $schedule_date = $_POST['schedule_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = trim($_POST['status']);
    $course_id = intval($_POST['course_id']);
    $year = trim($_POST['year']);
    $semester = trim($_POST['semester']);
    $stmt = $pdo->prepare("INSERT INTO assessments (assessment_type, schedule_date, start_time, end_time, status, course_id, year, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$assessment_type, $schedule_date, $start_time, $end_time, $status, $course_id, $year, $semester]);
    header('Location: manage_assessments.php');
    exit;
}

// Handle delete assessment
if (isset($_GET['delete']) && $is_admin) {
    $assessment_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM assessments WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    header('Location: manage_assessments.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids']) && $is_admin) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $stmt = $pdo->prepare("DELETE FROM assessments WHERE assessment_id IN ($placeholders)");
    $stmt->execute($ids);
    header('Location: manage_assessments.php');
    exit;
}

// Fetch all assessments with course info
$stmt = $pdo->query("SELECT a.*, c.course_name FROM assessments a LEFT JOIN courses c ON a.course_id = c.course_id ORDER BY a.assessment_id ASC");
$assessments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Assessments - Proctor Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="Logo">
            <span class="system-title">Proctor Management System</span>
        </div>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_rooms.php">Rooms</a></li>
            <li><a href="manage_courses.php">Courses</a></li>
            <li><a href="manage_sections.php">Sections</a></li>
            <li><a href="manage_assessments.php">Assessments</a></li>
            <li><a href="manage_schedules.php">Exam Schedules</a></li>
            <li><a href="../includes/logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="container">
        <h2>Manage Assessments</h2>
        <?php if ($is_admin): ?>
        <form method="post">
            <label>Assessment Type:</label>
            <input type="text" name="assessment_type" required>
            <label>Course:</label>
            <select name="course_id" required>
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['course_id']; ?>">
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Year:</label>
            <select name="year" required>
                <option value="">-- Select Year --</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year; ?>"><?php echo htmlspecialchars($year); ?></option>
                <?php endforeach; ?>
            </select>
            <label>Semester:</label>
            <input type="text" name="semester" required>
            <label>Date:</label>
            <input type="date" name="schedule_date" required>
            <label>Start Time:</label>
            <input type="time" name="start_time" required>
            <label>End Time:</label>
            <input type="time" name="end_time" required>
            <label>Status:</label>
            <input type="text" name="status">
            <button type="submit" name="add_assessment">Add Assessment</button>
        </form>
        <?php endif; ?>
        <h3>Assessment List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Semester</th>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <?php if ($is_admin): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessments as $assessment): ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $assessment['assessment_id']; ?>"></td>
                    <td><?php echo htmlspecialchars($assessment['assessment_id']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['assessment_type']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['year']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['semester']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['schedule_date']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($assessment['status']); ?></td>
                    <?php if ($is_admin): ?>
                    <td data-label="Action">
                        <a href="?delete=<?php echo $assessment['assessment_id']; ?>" class="action-link" onclick="return confirm('Delete this assessment?');">Delete</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($is_admin): ?>
        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected assessments?');">Delete Selected</button>
        <?php endif; ?>
        </form>
        <script>
        // Select/Deselect all checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            var selectAll = document.getElementById('selectAll');
            var checkboxes = document.querySelectorAll('input[name="delete_ids[]"]');
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
        </script>
    </div>
</body>
</html>