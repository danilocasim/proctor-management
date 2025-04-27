<?php
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = trim($_POST['department']);
    $role = $_POST['role'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = 'Email already registered.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstname, $lastname, $email, $password, $department, $role]);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign-up Form</title>
    <link rel="stylesheet" href="../css/signup.css" />
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <div class="logo-wrapper">
                <div class="logo-background">
                    <img class="logo" src="../../assets/image/plmun-logo-1.png" alt="PLMun Logo" />
                </div>
            </div>
        </div>
        <div class="signup-container">
            <div class="caption">
                <h2>Assigning exam rooms made easy.</h2>
                <h2>
                    Sign up to receive your proctor schedule and room assignments for
                    upcoming examinations at Pamantasan ng Lungsod ng Muntinlupa.
                </h2>
            </div>
            <div class="signup">
                <h2>Let's get started!</h2>
                <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
                <form method="post" id="create-account">
                    <div class="column">
                        <div>
                            <label for="firstname">FIRST NAME<span aria-label="required">*</span></label>
                            <input type="text" id="firstname" name="firstname" pattern="[a-zA-Z .]+" required />
                        </div>
                        <div>
                            <label for="email">EMAIL<span aria-label="required">*</span></label>
                            <input type="email" id="email" name="email" required />
                        </div>
                        <div>
                            <label for="password">PASSWORD<span aria-label="required">*</span></label>
                            <input type="password" id="password" name="password" required />
                        </div>
                    </div>
                    <div class="column">
                        <div>
                            <label for="lastname">LAST NAME<span aria-label="required">*</span></label>
                            <input type="text" id="lastname" name="lastname" pattern="[a-zA-Z ]+" required />
                        </div>
                        <div>
                            <label for="department">DEPARTMENT<span aria-label="required">*</span></label>
                            <input type="text" id="department" name="department" required />
                        </div>
                        <div>
                            <label for="role">ROLE<span aria-label="required">*</span></label>
                            <select id="role" name="role" required>
                                <option value="COS">COS</option>
                                <option value="Plantilla Faculty members">Plantilla Faculty members</option>
                            </select>
                        </div>
                    </div>
                    <div class="btn-container">
                        <button type="submit" class="btn">Create Account</button>
                    </div>
                </form>
                <p>
                    Already have an account?
                    <a href="login.php">Login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>