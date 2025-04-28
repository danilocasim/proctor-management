<?php
require '../includes/auth.php';
require '../includes/db.php';

// Fetch courses for the dropdown
$courses_stmt = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC");
$courses = $courses_stmt->fetchAll();

// Handle add section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $section_number = trim($_POST['section_number']);
    $year_level = trim($_POST['year_level']);
    $course_id = intval($_POST['course_id']);
    $stmt = $pdo->prepare("INSERT INTO sections (section_number, year_level, course_id) VALUES (?, ?, ?)");
    $stmt->execute([$section_number, $year_level, $course_id]);
    header('Location: manage_sections.php');
    exit;
}

// Handle delete section
if (isset($_GET['delete'])) {
    $section_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM sections WHERE section_id = ?");
    $stmt->execute([$section_id]);
    header('Location: manage_sections.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids'])) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $stmt = $pdo->prepare("DELETE FROM sections WHERE section_id IN ($placeholders)");
    $stmt->execute($ids);
    header('Location: manage_sections.php');
    exit;
}

// Fetch all sections with course info
$stmt = $pdo->query("SELECT s.*, c.course_name FROM sections s LEFT JOIN courses c ON s.course_id = c.course_id ORDER BY s.section_id ASC");
$sections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Sections - Proctor Management System</title>
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
        <h2>Manage Sections</h2>
        <form method="post">
            <label>Section Number:</label>
            <input type="text" name="section_number" required>
            <label>Year Level:</label>
            <input type="text" name="year_level" required>
            <label>Course:</label>
            <select name="course_id" required>
                <option value="">-- Select Course --</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['course_id']; ?>">
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="add_section">Add Section</button>
        </form>
        <h3>Section List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Section</th>
                    <th>Year Level</th>
                    <th>Course</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $section): ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $section['section_id']; ?>"></td>
                    <td><?php echo htmlspecialchars($section['section_id']); ?></td>
                    <td><?php echo htmlspecialchars($section['section_number']); ?></td>
                    <td><?php echo htmlspecialchars($section['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($section['course_name']); ?></td>
                    <td>
                        <a href="?delete=<?php echo $section['section_id']; ?>" onclick="return confirm('Delete this section?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected sections?');">Delete Selected</button>
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