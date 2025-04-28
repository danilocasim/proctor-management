<?php
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = trim($_POST['department']);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = 'Email already registered.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (fname, lname, email, password, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fname, $lname, $email, $password, $department, $role]);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Proctor Management System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="signup-container">
        <h2>Create an Account</h2>
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="post">
            <label>First Name:</label>
            <input type="text" name="fname" required><br>
            <label>Last Name:</label>
            <input type="text" name="lname" required><br>
            <label>Email:</label>
            <input type="email" name="email" required><br>
            <label>Password:</label>
            <input type="password" name="password" required><br>
            <label>Department:</label>
            <input type="text" name="department" required><br>
            <label>Role:</label>
            <select name="role" required>
                <option value="Admin">Admin</option>
                <option value="COS">COS</option>
                <option value="Plantilla Faculty members">Plantilla Faculty members</option>
            </select><br>
            <button type="submit">Sign Up</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>