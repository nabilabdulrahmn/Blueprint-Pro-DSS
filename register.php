<?php
require_once 'db.php';
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    header("Location: index.php");
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $username  = isset($_POST['username'])  ? trim($_POST['username'])  : '';
    $phone     = isset($_POST['phone'])     ? trim($_POST['phone'])     : '';
    $password  = isset($_POST['password'])  ? $_POST['password']        : '';
    $confirm   = isset($_POST['confirm'])   ? $_POST['confirm']         : '';

    if ($fullName === '' || $username === '' || $phone === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } else if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } else if ($db_error) {
        $error = 'Database connection failed. Please try again later.';
    } else {
        // Check if username already exists
        $safeUser  = mysqli_real_escape_string($conn, $username);
        $safePhone = mysqli_real_escape_string($conn, $phone);

        $checkUser = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safeUser' LIMIT 1");
        if ($checkUser && mysqli_num_rows($checkUser) > 0) {
            $error = 'This username is already taken. Please choose another.';
        } else {
            // Check if phone already exists
            $checkPhone = mysqli_query($conn, "SELECT id FROM users WHERE phone = '$safePhone' LIMIT 1");
            if ($checkPhone && mysqli_num_rows($checkPhone) > 0) {
                $error = 'This phone number is already registered.';
            } else {
                // Create the account
                $hashedPw  = password_hash($password, PASSWORD_DEFAULT);
                $safeName  = mysqli_real_escape_string($conn, $fullName);
                $safeHash  = mysqli_real_escape_string($conn, $hashedPw);

                $sql = "INSERT INTO users (full_name, username, phone, password) VALUES ('$safeName', '$safeUser', '$safePhone', '$safeHash')";
                $insResult = mysqli_query($conn, $sql);

                if ($insResult) {
                    header("Location: login.php?registered=1");
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blueprint Pro | Decision Support System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="auth-body">

<div class="auth-container">
    <!-- Decorative background elements -->
    <div class="auth-bg-orb auth-bg-orb-1"></div>
    <div class="auth-bg-orb auth-bg-orb-2"></div>
    <div class="auth-bg-orb auth-bg-orb-3"></div>

    <div class="auth-card">
        <!-- Brand Header -->
        <div class="auth-brand">
            <div class="auth-brand-icon">
                <i class="bx bx-store-alt"></i>
            </div>
            <h1 class="auth-brand-name">BLUEPRINT <span class="brand-pro" style="font-size: 0.9em;">PRO</span></h1>
            <p class="auth-brand-tag">Precision Financial Architecture</p>
        </div>

        <div class="auth-divider"></div>

        <h2 class="auth-title">Create Account</h2>
        <p class="auth-subtitle">Set up your business dashboard access</p>

        <?php if ($error !== ''): ?>
            <div class="auth-alert auth-alert-error">
                <i class="bx bx-error-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="auth-form" id="register-form">
            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-id-card"></i></div>
                <input type="text" id="full_name" name="full_name" placeholder="Full Name" autocomplete="name"
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>

            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-user"></i></div>
                <input type="text" id="username" name="username" placeholder="Username" autocomplete="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-phone"></i></div>
                <input type="tel" id="phone" name="phone" placeholder="Phone Number (e.g. 0123456789)" autocomplete="tel"
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
            </div>

            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-lock-alt"></i></div>
                <input type="password" id="password" name="password" placeholder="Password (min. 6 characters)" autocomplete="new-password" required>
                <button type="button" class="auth-toggle-pw" onclick="togglePassword('password', this)" title="Show password">
                    <i class="bx bx-hide"></i>
                </button>
            </div>

            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-lock-open-alt"></i></div>
                <input type="password" id="confirm" name="confirm" placeholder="Confirm Password" autocomplete="new-password" required>
                <button type="button" class="auth-toggle-pw" onclick="togglePassword('confirm', this)" title="Show password">
                    <i class="bx bx-hide"></i>
                </button>
            </div>

            <button type="submit" class="auth-submit-btn" id="register-btn">
                <i class="bx bx-user-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="login.php" class="auth-link">Sign in</a></p>
        </div>
    </div>

    <p class="auth-copyright">Blueprint Pro &copy; 2024</p>
</div>

<script>
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-show';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-hide';
    }
}
</script>

</body>
</html>
