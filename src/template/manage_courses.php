<?php
require '../includes/auth.php';
require '../includes/db.php';

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

// Handle add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course']) && $is_admin) {
    $course_name = trim($_POST['course_name']);
    $department = trim($_POST['department']);
    $stmt = $pdo->prepare("INSERT INTO courses (course_name, department) VALUES (?, ?)");
    $stmt->execute([$course_name, $department]);
    header('Location: manage_courses.php');
    exit;
}

// Handle delete course
if (isset($_GET['delete']) && $is_admin) {
    $course_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    header('Location: manage_courses.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids']) && $is_admin) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id IN ($placeholders)");
    $stmt->execute($ids);
    header('Location: manage_courses.php');
    exit;
}

// Fetch all courses
$stmt = $pdo->query("SELECT * FROM courses ORDER BY course_id ASC");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Courses - Proctor Management System</title>
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
        <h2>Manage Courses</h2>
        <?php if ($is_admin): ?>
        <form method="post">
            <label>Course Name:</label>
            <input type="text" name="course_name" required>
            <label>Department:</label>
            <input type="text" name="department" required>
            <button type="submit" name="add_course">Add Course</button>
        </form>
        <?php endif; ?>
        <h3>Course List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <?php if ($is_admin): ?>
                    <th><input type="checkbox" id="selectAll"></th>
                    <?php endif; ?>
                    <th>ID</th>
                    <th>Course Name</th>
                    <th>Department</th>
                    <?php if ($is_admin): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <?php if ($is_admin): ?>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $course['course_id']; ?>"></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($course['course_id']); ?></td>
                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($course['department']); ?></td>
                    <?php if ($is_admin): ?>
                    <td data-label="Action">
                        <a href="?delete=<?php echo $course['course_id']; ?>" class="action-link" onclick="return confirm('Delete this course?');">Delete</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($is_admin): ?>
        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected courses?');">Delete Selected</button>
        <?php endif; ?>
        </form>
        <script>
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