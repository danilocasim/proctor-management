<?php
require '../includes/auth.php';
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
            <!-- PHP loop to display schedules -->
        </tbody>
    </table>
</body>
</html>