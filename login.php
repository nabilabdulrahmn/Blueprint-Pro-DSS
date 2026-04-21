<?php
require_once 'db.php';
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    header("Location: index.php");
    exit;
}

$error   = '';
$success = '';

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Account created successfully! Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else if ($db_error) {
        $error = 'Database connection failed. Please try again later.';
    } else {
        $safeUser = mysqli_real_escape_string($conn, $username);
        $result = mysqli_query($conn, "SELECT id, full_name, username, password FROM users WHERE username = '$safeUser' LIMIT 1");

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_array($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = intval($user['id']);
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                header("Location: index.php");
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
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

        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Sign in to your account to continue</p>

        <?php if ($error !== ''): ?>
            <div class="auth-alert auth-alert-error">
                <i class="bx bx-error-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="auth-alert auth-alert-success">
                <i class="bx bx-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="auth-form" id="login-form">
            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-user"></i></div>
                <input type="text" id="username" name="username" placeholder="Username" autocomplete="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="auth-field">
                <div class="auth-field-icon"><i class="bx bx-lock-alt"></i></div>
                <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                <button type="button" class="auth-toggle-pw" onclick="togglePassword('password', this)" title="Show password">
                    <i class="bx bx-hide"></i>
                </button>
            </div>

            <button type="submit" class="auth-submit-btn" id="login-btn">
                <i class="bx bx-log-in"></i> Sign In
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php" class="auth-link">Create one</a></p>
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
