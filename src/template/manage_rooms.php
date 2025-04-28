<?php
require '../includes/auth.php';
require '../includes/db.php';

// Handle add room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $room_no = trim($_POST['room_no']);
    $capacity = intval($_POST['capacity']);
    $floor = trim($_POST['floor']);
    $stmt = $pdo->prepare("INSERT INTO rooms (room_no, capacity, floor) VALUES (?, ?, ?)");
    $stmt->execute([$room_no, $capacity, $floor]);
    header('Location: manage_rooms.php');
    exit;
}

// Handle delete room
if (isset($_GET['delete'])) {
    $room_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
    $stmt->execute([$room_id]);
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
        <form method="post">
            <label>Room No:</label>
            <input type="text" name="room_no" required>
            <label>Capacity:</label>
            <input type="number" name="capacity" min="1" required>
            <label>Floor:</label>
            <input type="text" name="floor" required>
            <button type="submit" name="add_room">Add Room</button>
        </form>
        <h3>Room List</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Room No</th>
                    <th>Capacity</th>
                    <th>Floor</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><?php echo htmlspecialchars($room['room_id']); ?></td>
                    <td><?php echo htmlspecialchars($room['room_no']); ?></td>
                    <td><?php echo htmlspecialchars($room['capacity']); ?></td>
                    <td><?php echo htmlspecialchars($room['floor']); ?></td>
                    <td>
                        <!-- For simplicity, only delete is implemented here -->
                        <a href="?delete=<?php echo $room['room_id']; ?>" onclick="return confirm('Delete this room?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>