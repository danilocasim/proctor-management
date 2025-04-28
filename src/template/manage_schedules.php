<?php
require '../includes/auth.php';
require '../includes/db.php';

$manual_feedback = '';

// Ensure these variables are always defined for use in manual adjustment logic
$min_proctor_break = 30 * 60; // 30 minutes in seconds
$max_subjects_per_section_per_day = 3;

// Fetch data for dropdowns
$assessments = $pdo->query("SELECT * FROM assessments ORDER BY assessment_type ASC")->fetchAll();
$sections = $pdo->query("SELECT s.*, c.course_name FROM sections s LEFT JOIN courses c ON s.course_id = c.course_id ORDER BY s.section_id ASC")->fetchAll();
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY room_no ASC")->fetchAll();
$proctors = $pdo->query("SELECT * FROM users WHERE role IN ('COS', 'Plantilla Faculty members') ORDER BY fname ASC")->fetchAll();

// Filter sections by assessment year when adding schedule
function getSectionsForAssessment($sections, $assessment_id, $assessments) {
    $assessment = null;
    foreach ($assessments as $a) {
        if ($a['assessment_id'] == $assessment_id) {
            $assessment = $a;
            break;
        }
    }
    if (!$assessment) return [];
    $year = $assessment['year'];
    $course_id = $assessment['course_id'];
    $filtered = [];
    foreach ($sections as $section) {
        if ($section['year_level'] == $year && $section['course_id'] == $course_id) {
            $filtered[] = $section;
        }
    }
    return $filtered;
}

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

    // Backend validation: section must match assessment year and course
    $assessment = null;
    foreach ($assessments as $a) if ($a['assessment_id'] == $assessment_id) $assessment = $a;
    $section = null;
    foreach ($sections as $s) if ($s['section_id'] == $section_id) $section = $s;
    if (!$assessment || !$section || $section['year_level'] != $assessment['year'] || $section['course_id'] != $assessment['course_id']) {
        die('Section does not match the assessment year and course.');
    }

    // Time validation: end must not exceed assessment end_time
    if ($custom_end && $assessment && $assessment['end_time'] && $custom_end > $assessment['end_time']) {
        die('Schedule end time exceeds allowed assessment end time.');
    }
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

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['delete_ids'])) {
    $ids = array_map('intval', $_POST['delete_ids']);
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $stmt = $pdo->prepare("DELETE FROM exam_schedules WHERE schedule_id IN ($placeholders)");
    $stmt->execute($ids);
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
    $exam_duration = 90 * 60; // 90 minutes in seconds
    $min_proctor_break = 30 * 60; // 30 minutes in seconds
    $max_subjects_per_section_per_day = 3;

    // Gather section student counts
    $section_students = [];
    foreach ($sections as $section) {
        $section_students[$section['section_id']] = isset($section['student_count']) ? $section['student_count'] : 0;
    }

    // Precompute subject clustering (by course, year, subject)
    $subject_clusters = [];
    foreach ($assessments as $a) {
        $key = $a['course_id'] . '-' . $a['year'] . '-' . $a['assessment_type'];
        if (!isset($subject_clusters[$key])) $subject_clusters[$key] = [];
        $subject_clusters[$key][] = $a;
    }
    // Optionally, sort clusters by size (most common first)
    uasort($subject_clusters, function($a, $b) { return count($b) - count($a); });

    // Set the exam week start date (e.g., next Monday)
    $exam_week_start = date('Y-m-d', strtotime('next Monday'));
    $created = 0;
    $skipped = 0;
    $section_day_subjects = [];
    $section_room_last = [];
    $proctor_last_end = [];
    $proctor_load = [];

    // --- TIME CLUSTER RULE FOR SAME ASSESSMENT NAME ---
    // Ensure that all sections with the same assessment_type (same course, year, and assessment_type)
    // are scheduled at the same date and time slot.
    foreach ($subject_clusters as $cluster) {
        // Pick a time slot for this assessment cluster
        $cluster_scheduled = false;
        $chosen_day = null;
        $chosen_slot = null;
        // Try every day and slot until one fits for all sections
        foreach ($days as $d_idx => $day) {
            $current_date = date('Y-m-d', strtotime("+$d_idx days", strtotime($exam_week_start)));
            foreach ($slots as $slot) {
                $can_schedule_all = true;
                // Check all sections for this cluster
                foreach ($cluster as $assessment) {
                    foreach ($sections as $section) {
                        if ($section['year_level'] != $assessment['year'] || $section['course_id'] != $assessment['course_id']) continue;
                        // Check daily limit
                        if (!isset($section_day_subjects[$section['section_id']][$current_date])) $section_day_subjects[$section['section_id']][$current_date] = 0;
                        if ($section_day_subjects[$section['section_id']][$current_date] >= $max_subjects_per_section_per_day) {
                            $can_schedule_all = false;
                            break 2;
                        }
                        // Check room availability
                        $room_found = null;
                        foreach ($rooms as $room) {
                            $stud_count = isset($section_students[$section['section_id']]) ? $section_students[$section['section_id']] : 0;
                            if ($room['capacity'] < $stud_count) continue;
                            $room_conflict = $pdo->prepare(
                                "SELECT 1 FROM exam_schedules WHERE room_id = ? AND custom_date = ? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?))"
                            );
                            $room_conflict->execute([
                                $room['room_id'], $current_date,
                                $slot['end'], $slot['end'],
                                $slot['start'], $slot['start'],
                                $slot['start'], $slot['end']
                            ]);
                            if (!$room_conflict->fetch()) {
                                $room_found = $room;
                                break;
                            }
                        }
                        if (!$room_found) {
                            $can_schedule_all = false;
                            break 2;
                        }
                        // Check proctor availability
                        $proctor_found = null;
                        foreach ($proctors as $proctor) {
                            $proctor_conflict = $pdo->prepare(
                                "SELECT custom_end FROM exam_schedules WHERE proctor_id = ? AND custom_date = ? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?))"
                            );
                            $proctor_conflict->execute([
                                $proctor['user_id'], $current_date,
                                $slot['end'], $slot['end'],
                                $slot['start'], $slot['start'],
                                $slot['start'], $slot['end']
                            ]);
                            $last_end = $proctor_last_end[$proctor['user_id']] ?? null;
                            if ($last_end && (strtotime($slot['start']) - strtotime($last_end)) < $min_proctor_break) continue;
                            $load = $proctor_load[$proctor['user_id']] ?? 0;
                            if ($load > 0 && $load > min($proctor_load)) continue;
                            if (!$proctor_conflict->fetch()) {
                                $proctor_found = $proctor;
                                break;
                            }
                        }
                        if (!$proctor_found) {
                            $can_schedule_all = false;
                            break 2;
                        }
                    }
                }
                if ($can_schedule_all) {
                    $chosen_day = $current_date;
                    $chosen_slot = $slot;
                    $cluster_scheduled = true;
                    break 2;
                }
            }
        }
        if ($cluster_scheduled && $chosen_day && $chosen_slot) {
            // Assign all schedules for this cluster at the same date/time
            foreach ($cluster as $assessment) {
                foreach ($sections as $section) {
                    if ($section['year_level'] != $assessment['year'] || $section['course_id'] != $assessment['course_id']) continue;
                    // Check if already scheduled
                    $exists = $pdo->prepare("SELECT 1 FROM exam_schedules WHERE assessment_id=? AND section_id=?");
                    $exists->execute([$assessment['assessment_id'], $section['section_id']]);
                    if ($exists->fetch()) continue;
                    // Find available room
                    $room_found = null;
                    foreach ($rooms as $room) {
                        $stud_count = isset($section_students[$section['section_id']]) ? $section_students[$section['section_id']] : 0;
                        if ($room['capacity'] < $stud_count) continue;
                        $room_conflict = $pdo->prepare(
                            "SELECT 1 FROM exam_schedules WHERE room_id = ? AND custom_date = ? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?))"
                        );
                        $room_conflict->execute([
                            $room['room_id'], $chosen_day,
                            $chosen_slot['end'], $chosen_slot['end'],
                            $chosen_slot['start'], $chosen_slot['start'],
                            $chosen_slot['start'], $chosen_slot['end']
                        ]);
                        if (!$room_conflict->fetch()) {
                            $room_found = $room;
                            break;
                        }
                    }
                    if (!$room_found) continue;
                    // Find available proctor
                    $proctor_found = null;
                    foreach ($proctors as $proctor) {
                        $proctor_conflict = $pdo->prepare(
                            "SELECT custom_end FROM exam_schedules WHERE proctor_id = ? AND custom_date = ? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?))"
                        );
                        $proctor_conflict->execute([
                            $proctor['user_id'], $chosen_day,
                            $chosen_slot['end'], $chosen_slot['end'],
                            $chosen_slot['start'], $chosen_slot['start'],
                            $chosen_slot['start'], $chosen_slot['end']
                        ]);
                        $last_end = $proctor_last_end[$proctor['user_id']] ?? null;
                        if ($last_end && (strtotime($chosen_slot['start']) - strtotime($last_end)) < $min_proctor_break) continue;
                        $load = $proctor_load[$proctor['user_id']] ?? 0;
                        if ($load > 0 && $load > min($proctor_load)) continue;
                        if (!$proctor_conflict->fetch()) {
                            $proctor_found = $proctor;
                            break;
                        }
                    }
                    if (!$proctor_found) continue;
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
                        $chosen_day,
                        $chosen_slot['start'],
                        $chosen_slot['end']
                    ]);
                    $created++;
                    $section_day_subjects[$section['section_id']][$chosen_day]++;
                    $section_room_last[$section['section_id']] = $room_found['room_id'];
                    $proctor_last_end[$proctor_found['user_id']] = $chosen_slot['end'];
                    $proctor_load[$proctor_found['user_id']] = ($proctor_load[$proctor_found['user_id']] ?? 0) + 1;
                }
            }
        } else {
            $skipped += count($cluster);
        }
    }
    // --- END TIME CLUSTER RULE ---
    $auto_schedule_feedback = "Auto-scheduling complete: $created schedules created, $skipped could not be scheduled due to conflicts.";
}

// --- Manual adjustment warning logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    // Validate all rules for manual adjustment
    $schedule_id = intval($_POST['schedule_id']);
    $assessment_id = intval($_POST['assessment_id']);
    $section_id = intval($_POST['section_id']);
    $room_id = intval($_POST['room_id']);
    $proctor_id = intval($_POST['proctor_id']);
    $custom_date = $_POST['custom_date'] ?? null;
    $custom_start = $_POST['custom_start'] ?? null;
    $custom_end = $_POST['custom_end'] ?? null;
    $warnings = [];
    // Fetch related info
    $assessment = null;
    foreach ($assessments as $a) if ($a['assessment_id'] == $assessment_id) $assessment = $a;
    $section = null;
    foreach ($sections as $s) if ($s['section_id'] == $section_id) $section = $s;
    $room = null;
    foreach ($rooms as $r) if ($r['room_id'] == $room_id) $room = $r;
    $proctor = null;
    foreach ($proctors as $p) if ($p['user_id'] == $proctor_id) $proctor = $p;
    if (!$assessment || !$section || !$room || !$proctor) $warnings[] = 'Incomplete schedule information.';
    // Section/assessment match
    if ($section['year_level'] != $assessment['year'] || $section['course_id'] != $assessment['course_id']) $warnings[] = 'Section does not match assessment year/course.';
    // Room capacity
    $stud_count = $section_students[$section['section_id']] ?? 0;
    if ($room['capacity'] < $stud_count) $warnings[] = 'Room capacity is less than number of students in section.';
    // Proctor break
    $proctor_last = $pdo->prepare("SELECT custom_end FROM exam_schedules WHERE proctor_id=? AND custom_date=? AND schedule_id!=? ORDER BY custom_end DESC LIMIT 1");
    $proctor_last->execute([$proctor_id, $custom_date, $schedule_id]);
    $last_end = $proctor_last->fetchColumn();
    if ($last_end && (strtotime($custom_start) - strtotime($last_end)) < $min_proctor_break) $warnings[] = 'Proctor has less than 30 minutes break.';
    // Max subjects per day
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_schedules WHERE section_id=? AND custom_date=? AND schedule_id!=?");
    $stmt->execute([$section_id, $custom_date, $schedule_id]);
    $count_today = $stmt->fetchColumn();
    if ($count_today >= $max_subjects_per_section_per_day) $warnings[] = 'Section exceeds max 3 subjects per day.';
    // Room/proctor conflict
    $room_conflict = $pdo->prepare("SELECT 1 FROM exam_schedules WHERE room_id=? AND custom_date=? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?)) AND schedule_id!=?");
    $room_conflict->execute([
        $room_id,
        $custom_date,
        $custom_end, $custom_end,
        $custom_start, $custom_start,
        $custom_start, $custom_end,
        $schedule_id
    ]);
    if ($room_conflict->fetch()) $warnings[] = 'Room conflict with another schedule.';
    $proctor_conflict = $pdo->prepare("SELECT 1 FROM exam_schedules WHERE proctor_id=? AND custom_date=? AND ((custom_start < ? AND custom_end > ?) OR (custom_start < ? AND custom_end > ?) OR (custom_start >= ? AND custom_end <= ?)) AND schedule_id!=?");
    $proctor_conflict->execute([
        $proctor_id,
        $custom_date,
        $custom_end, $custom_end,
        $custom_start, $custom_start,
        $custom_start, $custom_end,
        $schedule_id
    ]);
    if ($proctor_conflict->fetch()) $warnings[] = 'Proctor conflict with another schedule.';
    // Assessment time
    if ($custom_end > $assessment['end_time']) $warnings[] = 'Schedule end time exceeds allowed assessment end time.';
    // If no warnings, update schedule
    if (empty($warnings)) {
        $stmt = $pdo->prepare("UPDATE exam_schedules SET assessment_id=?, section_id=?, room_id=?, proctor_id=?, custom_date=?, custom_start=?, custom_end=? WHERE schedule_id=?");
        $stmt->execute([$assessment_id, $section_id, $room_id, $proctor_id, $custom_date, $custom_start, $custom_end, $schedule_id]);
        $manual_feedback = 'Schedule updated.';
    } else {
        $manual_feedback = 'Cannot update schedule: ' . implode(' ', $warnings);
    }
}

// Filter logic for manage_schedules
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
    "SELECT es.*, a.assessment_type, a.year, a.semester, c.course_name, s.section_number, s.year_level, r.room_no, u.fname, u.lname
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

// Role-based access control
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Exam Schedules - Proctor Management System</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
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
        <h2>Manage Exam Schedules</h2>
        <?php if ($auto_schedule_feedback): ?>
            <p style="color: green;"><?php echo htmlspecialchars($auto_schedule_feedback); ?></p>
        <?php endif; ?>
        <?php if ($manual_feedback): ?>
            <p style="color: green;"><?php echo htmlspecialchars($manual_feedback); ?></p>
        <?php endif; ?>
        <?php if ($is_admin): ?>
            <form method="post" style="margin-bottom:1em;">
                <button type="submit" name="auto_schedule">Auto Generate Schedules</button>
            </form>
        <?php endif; ?>
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
        <form method="post">
            <label>Assessment:</label>
            <select name="assessment_id" id="assessmentSelect" required>
                <option value="">-- Select Assessment --</option>
                <?php foreach ($assessments as $a): ?>
                    <option value="<?php echo $a['assessment_id']; ?>">
                        <?php echo htmlspecialchars($a['assessment_type'] . " (" . $a['year'] . ", " . $a['semester'] . ")"); ?> 
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Section:</label>
            <select name="section_id" id="sectionSelect" required>
                <option value="">-- Select Section --</option>
                <!-- Options will be dynamically populated -->
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
            <?php if ($is_admin): ?>
                <button type="submit" name="add_schedule">Add Schedule</button>
            <?php endif; ?>
        </form>
        <h3>Exam Schedule List</h3>
        <form method="post" id="bulkDeleteForm">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th data-label="ID">ID</th>
                    <th data-label="Assessment">Assessment</th>
                    <th data-label="Course">Course</th>
                    <th data-label="Section">Section</th>
                    <th data-label="Year Level">Year Level</th>
                    <th data-label="Room">Room</th>
                    <th data-label="Proctor">Proctor</th>
                    <th data-label="Date">Date</th>
                    <th data-label="Time">Time</th>
                    <th data-label="Reason">Reason</th>
                    <th data-label="Action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?php echo $s['schedule_id']; ?>"></td>
                    <td data-label="ID"><?php echo htmlspecialchars($s['schedule_id']); ?></td>
                    <td data-label="Assessment"><?php echo htmlspecialchars($s['assessment_type']); ?></td>
                    <td data-label="Course"><?php echo htmlspecialchars($s['course_name']); ?></td>
                    <td data-label="Section"><?php echo htmlspecialchars($s['section_number']); ?></td>
                    <td data-label="Year Level"><?php echo isset($s['year_level']) ? htmlspecialchars($s['year_level']) : 'N/A'; ?></td>
                    <td data-label="Room"><?php echo htmlspecialchars($s['room_no']); ?></td>
                    <td data-label="Proctor"><?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?></td>
                    <td data-label="Date"><?php echo htmlspecialchars($s['custom_date']); ?></td>
                    <td data-label="Time"><?php echo htmlspecialchars($s['custom_start'] . ' - ' . $s['custom_end']); ?></td>
                    <td data-label="Reason"><?php echo htmlspecialchars($s['reason']); ?></td>
                    <td data-label="Action">
                        <?php if ($is_admin): ?>
                            <a href="?delete=<?php echo $s['schedule_id']; ?>" class="action-link" onclick="return confirm('Delete this schedule?');">Delete</a>
                            <a href="?edit=<?php echo $s['schedule_id']; ?>" class="action-link">Edit</a>
                        <?php else: ?>
                            <?php if ($s['proctor_id'] == $_SESSION['user_id']): ?>
                                <?php if (isset($s['status']) && $s['status'] === 'Done'): ?>
                                    <span style="color:green;font-weight:bold;">Done</span>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>">
                                        <select name="status">
                                            <option value="Pending" <?php if(isset($s['status']) && $s['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                            <option value="Done" <?php if(isset($s['status']) && $s['status'] == 'Done') echo 'selected'; ?>>Done</option>
                                        </select>
                                        <button type="submit" name="update_status">Update</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($is_admin): ?>
            <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected schedules?');">Delete Selected</button>
        <?php endif; ?>
        </form>
        <?php if (isset($_GET['edit'])): ?>
            <?php
            $schedule_id = intval($_GET['edit']);
            $stmt = $pdo->prepare("SELECT * FROM exam_schedules WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();
            ?>
            <?php if ($is_admin): ?>
                <form method="post">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                    <label>Assessment:</label>
                    <select name="assessment_id" required>
                        <option value="">-- Select Assessment --</option>
                        <?php foreach ($assessments as $a): ?>
                            <option value="<?php echo $a['assessment_id']; ?>" <?php if ($a['assessment_id'] == $schedule['assessment_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($a['assessment_type'] . " (" . $a['year'] . ", " . $a['semester'] . ")"); ?> 
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Section:</label>
                    <select name="section_id" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s['section_id']; ?>" <?php if ($s['section_id'] == $schedule['section_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['section_number'] . ' (' . $s['year_level'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Room:</label>
                    <select name="room_id" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['room_id']; ?>" <?php if ($r['room_id'] == $schedule['room_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($r['room_no']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Proctor:</label>
                    <select name="proctor_id" required>
                        <option value="">-- Select Proctor --</option>
                        <?php foreach ($proctors as $p): ?>
                            <option value="<?php echo $p['user_id']; ?>" <?php if ($p['user_id'] == $schedule['proctor_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($p['fname'] . ' ' . $p['lname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Reason (optional):</label>
                    <input type="text" name="reason" value="<?php echo htmlspecialchars($schedule['reason']); ?>">
                    <label>Date (optional):</label>
                    <input type="date" name="custom_date" value="<?php echo htmlspecialchars($schedule['custom_date']); ?>">
                    <label>Start Time (optional):</label>
                    <input type="time" name="custom_start" value="<?php echo htmlspecialchars($schedule['custom_start']); ?>">
                    <label>End Time (optional):</label>
                    <input type="time" name="custom_end" value="<?php echo htmlspecialchars($schedule['custom_end']); ?>">
                    <button type="submit" name="edit_schedule">Update Schedule</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <script>
        // JavaScript to filter sections by assessment year and course
        const assessments = <?php echo json_encode($assessments); ?>;
        const sections = <?php echo json_encode($sections); ?>;
        document.getElementById('assessmentSelect').addEventListener('change', function() {
            var assessmentId = this.value;
            var sectionSelect = document.getElementById('sectionSelect');
            sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
            if (!assessmentId) return;
            var assessment = assessments.find(a => a.assessment_id == assessmentId);
            if (!assessment) return;
            var filtered = sections.filter(s => s.year_level == assessment.year && s.course_id == assessment.course_id);
            filtered.forEach(function(section) {
                var opt = document.createElement('option');
                opt.value = section.section_id;
                opt.textContent = section.section_number + ' (' + section.year_level + ')';
                sectionSelect.appendChild(opt);
            });
        });
        </script>
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

<?php
// Handle non-admin status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && !$is_admin) {
    $schedule_id = intval($_POST['schedule_id']);
    $status = $_POST['status'] === 'Done' ? 'Done' : 'Pending';
    // Only allow update if user is assigned proctor
    $stmt = $pdo->prepare("UPDATE exam_schedules SET status=? WHERE schedule_id=? AND proctor_id=?");
    $stmt->execute([$status, $schedule_id, $_SESSION['user_id']]);
    header('Location: manage_schedules.php');
    exit;
}
?>