<?php
require '../includes/auth.php';
require '../includes/db.php';

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

// Handle add room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room']) && $is_admin) {
    $room_no = trim($_POST['room_no']);
    $capacity = intval($_POST['capacity']);
    $floor = trim($_POST['floor']);
    $stmt = $pdo->prepare("INSERT INTO rooms (room_no, capacity, floor) VALUES (?, ?, ?)");
    $stmt->execute([$room_no, $capacity, $floor]);
    header('Location: manage_rooms.php');
    exit;
}

// Handle delete room
if (isset($_GET['delete']) && $is_admin) {
    $room_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
    $stmt->execute([$room_id]);
    header('Location: manage_rooms.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids']) && $is_admin) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id IN ($placeholders)");
    $stmt->execute($ids);
    header('Location: manage_rooms.php');
    exit;
}

// Fetch all rooms
$stmt = $pdo->query("SELECT * FROM rooms ORDER BY room_id ASC");
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Rooms - Proctor Management System</title>
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
        <h2>Manage Rooms</h2>
        <?php if ($is_admin): ?>
        <form method="post">
            <label>Room Number:</label>
            <input type="text" name="room_no" required>
            <label>Capacity:</label>
            <input type="number" name="capacity" min="1" required>
            <label>Floor:</label>
            <input type="text" name="floor" required>
            <button type="submit" name="add_room">Add Room</button>
        </form>
        <?php endif; ?>
        <h3>Room List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>ID</th>
                    <th>Room No</th>
                    <th>Capacity</th>
                    <th>Floor</th>
                    <?php if ($is_admin): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $room['room_id']; ?>"></td>
                    <td><?php echo htmlspecialchars($room['room_id']); ?></td>
                    <td><?php echo htmlspecialchars($room['room_no']); ?></td>
                    <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                    <td><?php echo htmlspecialchars($room['floor']); ?></td>
                    <?php if ($is_admin): ?>
                    <td data-label="Action">
                        <a href="?delete=<?php echo $room['room_id']; ?>" class="action-link" onclick="return confirm('Delete this room?');">Delete</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($is_admin): ?>
        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected rooms?');">Delete Selected</button>
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