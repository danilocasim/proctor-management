<?php
require '../includes/auth.php';
require '../includes/db.php';

// Fetch data for dropdowns
$assessments = $pdo->query("SELECT * FROM assessments ORDER BY assessment_type ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.course_name FROM sections s LEFT JOIN courses c ON s.course_id = c.course_id ORDER BY s.section_id ASC")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_no ASC")->fetchAll();
$proctors = $pdo->query("SELECT * FROM users WHERE role IN ('COS', 'Plantilla Faculty members') ORDER BY fname ASC")->fetchAll();

// Handle add schedule (manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $assessment_id = intval($_POST['assessment_id']);
    $section_id = intval($_POST['section_id']);
    $room_id = intval($_POST['room_id']);
    $proctor_id = intval($_POST['proctor_id']);
    $reason = trim($_POST['reason']);
    $custom_date = $_POST['custom_date'] ?? null;
    $custom_start = $_POST['custom_start'] ?? null;
    $custom_end = $_POST['custom_end'] ?? null;
    $stmt = $pdo->prepare(
        "INSERT INTO exam_schedules (assessment_id, section_id, room_id, proctor_id, reason, custom_date, custom_start, custom_end)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$assessment_id, $section_id, $room_id, $proctor_id, $reason, $custom_date, $custom_start, $custom_end]);
    header('Location: manage_schedules.php');
    exit;
}

// Handle delete schedule
if (isset($_GET['delete'])) {
    $schedule_id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM exam_schedules WHERE schedule_id = ?");
    $stmt->execute([$schedule_id]);
    header('Location: manage_schedules.php');
    exit;
}

// Advanced Auto-Scheduling with Day and Time Slot Assignment
$auto_schedule_feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_schedule'])) {
    // Define scheduling days and slots
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $slots = [
        ['start' => '08:30:00', 'end' => '10:00:00'],
        ['start' => '10:00:00', 'end' => '11:30:00'],
        // Lunch break: 11:30–13:00
        ['start' => '13:00:00', 'end' => '14:30:00'],
        ['start' => '14:30:00', 'end' => '16:00:00'],
        ['start' => '16:00:00', 'end' => '17:30:00'], // Optional
    ];

    // Set the exam week start date (e.g., next Monday)
    $exam_week_start = date('Y-m-d', strtotime('next Monday'));

    $created = 0;
    $skipped = 0;
    foreach ($assessments as $assessment) {
        foreach ($sections as $section) {
            // Check if already scheduled
            $exists = $pdo->prepare("SELECT 1 FROM exam_schedules WHERE assessment_id=? AND section_id=?");
            $exists->execute([$assessment['assessment_id'], $section['section_id']]);
            if ($exists->fetch()) continue;

            $scheduled = false;
            // Try every day and slot
            for ($d = 0; $d < count($days); $d++) {
                $current_date = date('Y-m-d', strtotime("+$d days", strtotime($exam_week_start)));
                foreach ($slots as $slot) {
                    $start_time = $slot['start'];
                    $end_time = $slot['end'];

                    // Find available room
                    $room_found = null;
                    foreach ($rooms as $room) {
                        $room_conflict = $pdo->prepare(
                            "SELECT 1 FROM exam_schedules 
                             WHERE room_id = ? AND custom_date = ? AND (
                                (custom_start < ? AND custom_end > ?) OR
                                (custom_start < ? AND custom_end > ?) OR
                                (custom_start >= ? AND custom_end <= ?)
                             )"
                        );
                        $room_conflict->execute([
                            $room['room_id'],
                            $current_date,
                            $end_time, $end_time,
                            $start_time, $start_time,
                            $start_time, $end_time
                        ]);
                        if (!$room_conflict->fetch()) {
                            $room_found = $room;
                            break;
                        }
                    }
                    if (!$room_found) continue; // No room available for this slot

                    // Find available proctor
                    $proctor_found = null;
                    foreach ($proctors as $proctor) {
                        $proctor_conflict = $pdo->prepare(
                            "SELECT 1 FROM exam_schedules 
                             WHERE proctor_id = ? AND custom_date = ? AND (
                                (custom_start < ? AND custom_end > ?) OR
                                (custom_start < ? AND custom_end > ?) OR
                                (custom_start >= ? AND custom_end <= ?)
                             )"
                        );
                        $proctor_conflict->execute([
                            $proctor['user_id'],
                            $current_date,
                            $end_time, $end_time,
                            $start_time, $start_time,
                            $start_time, $end_time
                        ]);
                        if (!$proctor_conflict->fetch()) {
                            $proctor_found = $proctor;
                            break;
                        }
                    }
                    if (!$proctor_found) continue; // No proctor available for this slot

                    // Assign schedule
                    $stmt = $pdo->prepare(
                        "INSERT INTO exam_schedules (assessment_id, section_id, room_id, proctor_id, reason, custom_date, custom_start, custom_end)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $assessment['assessment_id'],
                        $section['section_id'],
                        $room_found['room_id'],
                        $proctor_found['user_id'],
                        null,
                        $current_date,
                        $start_time,
                        $end_time
                    ]);
                    $created++;
                    $scheduled = true;
                    break 2; // Done for this assessment-section
                }
            }
            if (!$scheduled) $skipped++;
        }
    }
    $auto_schedule_feedback = "Auto-scheduling complete: $created schedules created, $skipped could not be scheduled due to conflicts.";
}

// Fetch all schedules with joined info
$stmt = $pdo->query(
    "SELECT es.*, 
            a.assessment_type, 
            s.section_number, s.year_level, c.course_name, 
            r.room_no, 
            u.fname, u.lname 
     FROM exam_schedules es
     LEFT JOIN assessments a ON es.assessment_id = a.assessment_id
     LEFT JOIN sections s ON es.section_id = s.section_id
     LEFT JOIN courses c ON s.course_id = c.course_id
     LEFT JOIN rooms r ON es.room_id = r.room_id
     LEFT JOIN users u ON es.proctor_id = u.user_id
     ORDER BY es.schedule_id ASC"
);
$schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Exam Schedules - Proctor Management System</title>
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
        <h2>Manage Exam Schedules</h2>
        <?php if ($auto_schedule_feedback): ?>
            <p style="color: green;"><?php echo htmlspecialchars($auto_schedule_feedback); ?></p>
        <?php endif; ?>
        <form method="post" style="margin-bottom:1em;">
            <button type="submit" name="auto_schedule">Auto Generate Schedules</button>
        </form>
        <form method="post">
            <label>Assessment:</label>
            <select name="assessment_id" required>
                <option value="">-- Select Assessment --</option>
                <?php foreach ($assessments as $a): ?>
                    <option value="<?php echo $a['assessment_id']; ?>">
                        <?php echo htmlspecialchars($a['assessment_type']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Section:</label>
            <select name="section_id" required>
                <option value="">-- Select Section --</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s['section_id']; ?>">
                        <?php echo htmlspecialchars($s['section_number'] . " - " . $s['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Room:</label>
            <select name="room_id" required>
                <option value="">-- Select Room --</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?php echo $r['room_id']; ?>">
                        <?php echo htmlspecialchars($r['room_no']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Proctor:</label>
            <select name="proctor_id" required>
                <option value="">-- Select Proctor --</option>
                <?php foreach ($proctors as $p): ?>
                    <option value="<?php echo $p['user_id']; ?>">
                        <?php echo htmlspecialchars($p['fname'] . ' ' . $p['lname']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Reason (optional):</label>
            <input type="text" name="reason">
            <label>Date (optional):</label>
            <input type="date" name="custom_date">
            <label>Start Time (optional):</label>
            <input type="time" name="custom_start">
            <label>End Time (optional):</label>
            <input type="time" name="custom_end">
            <button type="submit" name="add_schedule">Add Schedule</button>
        </form>
        <h3>Exam Schedule List</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Assessment</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Year</th>
                    <th>Room</th>
                    <th>Proctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['schedule_id']); ?></td>
                    <td><?php echo htmlspecialchars($s['assessment_type']); ?></td>
                    <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['section_number']); ?></td>
                    <td><?php echo htmlspecialchars($s['year_level']); ?></td>
                    <td><?php echo htmlspecialchars($s['room_no']); ?></td>
                    <td><?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?></td>
                    <td><?php echo htmlspecialchars($s['custom_date']); ?></td>
                    <td><?php echo htmlspecialchars($s['custom_start'] . ' - ' . $s['custom_end']); ?></td>
                    <td><?php echo htmlspecialchars($s['reason']); ?></td>
                    <td>
                        <a href="?delete=<?php echo $s['schedule_id']; ?>" onclick="return confirm('Delete this schedule?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>