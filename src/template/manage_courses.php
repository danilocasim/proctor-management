<?php
require '../includes/auth.php';
require '../includes/db.php';

// Handle add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_name = trim($_POST['course_name']);
    $department = trim($_POST['department']);
    $stmt = $pdo->prepare("INSERT INTO courses (course_name, department) VALUES (?, ?)");
    $stmt->execute([$course_name, $department]);
    header('Location: manage_courses.php');
    exit;
}

// Handle delete course
if (isset($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    header('Location: manage_courses.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids'])) {
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
        <form method="post">
            <label>Course Name:</label>
            <input type="text" name="course_name" required>
            <label>Department:</label>
            <input type="text" name="department" required>
            <button type="submit" name="add_course">Add Course</button>
        </form>
        <h3>Course List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Course Name</th>
                    <th>Department</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $course['course_id']; ?>"></td>
                    <td><?php echo htmlspecialchars($course['course_id']); ?></td>
                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($course['department']); ?></td>
                    <td>
                        <a href="?delete=<?php echo $course['course_id']; ?>" onclick="return confirm('Delete this course?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected courses?');">Delete Selected</button>
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