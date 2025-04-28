<?php
require '../includes/auth.php';
require '../includes/db.php';

// Filter logic for dashboard
$where = [];
$params = [];
if (!empty($_GET['search'])) {
    $where[] = "(a.assessment_type LIKE ? OR c.course_name LIKE ? OR s.section_number LIKE ? OR u.fname LIKE ? OR u.lname LIKE ?)";
    $search = '%' . $_GET['search'] . '%';
    array_push($params, $search, $search, $search, $search, $search);
}
if (!empty($_GET['status'])) {
    $where[] = "es.status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['date'])) {
    $where[] = "es.custom_date = ?";
    $params[] = $_GET['date'];
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare(
    "SELECT es.*, a.assessment_type, a.year, a.semester, c.course_name, s.section_number, r.room_no, u.fname, u.lname
     FROM exam_schedules es
     LEFT JOIN assessments a ON es.assessment_id = a.assessment_id
     LEFT JOIN sections s ON es.section_id = s.section_id
     LEFT JOIN courses c ON s.course_id = c.course_id
     LEFT JOIN rooms r ON es.room_id = r.room_id
     LEFT JOIN users u ON es.proctor_id = u.user_id
     $where_sql
     ORDER BY es.custom_date, es.custom_start"
);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');
$user_id = $_SESSION['user_id'];

// Handle non-admin status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && !$is_admin) {
    $schedule_id = intval($_POST['schedule_id']);
    $status = $_POST['status'] === 'Done' ? 'Done' : 'Pending';
    // Only allow update if user is assigned proctor
    $stmt = $pdo->prepare("UPDATE exam_schedules SET status=? WHERE schedule_id=? AND proctor_id=?");
    $stmt->execute([$status, $schedule_id, $_SESSION['user_id']]);
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Proctor Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="../assets/image/logo.png" alt="Logo">
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
        <div class="user-info">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['fname'] . ' ' . $_SESSION['lname']); ?></h2>
            <p>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
        </div>
    </div>
    <div class="filter-bar card" style="max-width:900px;margin:1.5em auto 0;display:flex;gap:1em;align-items:center;">
        <form method="get" style="display:flex;gap:1em;flex-wrap:wrap;width:100%">
            <input type="text" name="search" placeholder="Search assessment, course, section, proctor..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="Pending" <?php if(isset($_GET['status']) && $_GET['status']==='Pending') echo 'selected'; ?>>Pending</option>
                <option value="Done" <?php if(isset($_GET['status']) && $_GET['status']==='Done') echo 'selected'; ?>>Done</option>
            </select>
            <input type="date" name="date" value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '' ?>">
            <button type="submit">Filter</button>
        </form>
    </div>
    <table>
        <thead>
            <tr>
                <th>Assessment</th>
                <th>Course</th>
                <th>Section</th>
                <th>Room</th>
                <th>Proctor</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?php echo htmlspecialchars($s['assessment_type']); ?></td>
                <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                <td><?php echo htmlspecialchars($s['section_number']); ?></td>
                <td><?php echo htmlspecialchars($s['room_no']); ?></td>
                <td><?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?></td>
                <td><?php echo htmlspecialchars($s['custom_date']); ?></td>
                <td><?php echo htmlspecialchars($s['custom_start'] . ' - ' . $s['custom_end']); ?></td>
                <td data-label="Status">
                    <?php if (!$is_admin && isset($s['proctor_id']) && $s['proctor_id'] == $user_id): ?>
                        <?php if (isset($s['status']) && $s['status'] === 'Done'): ?>
                            <span style="color:green;font-weight:bold;">Done</span>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                                <select name="status">
                                    <option value="Pending" <?php if(isset($s['status']) && $s['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Done" <?php if(isset($s['status']) && $s['status'] == 'Done') echo 'selected'; ?>>Done</option>
                                </select>
                                <button type="submit" name="update_status" class="action-link">Update</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo isset($s['status']) ? htmlspecialchars($s['status']) : 'N/A'; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>