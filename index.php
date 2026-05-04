<?php include 'alert.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UpGrade</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="container">
    <div class="left-container">
        <img src="assets\logo.png" alt="logo" class="logo">
    </div>

    <div class="right-container">
        <div class="auth-container">
            
            <div id="login-form">
                <h2>Welcome Back!</h2>
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
                <span class="toggle-link" onclick="toggleForms()">Need an account? Register here.</span>
            </div>

            <div id="register-form" style="display: none;">
                <h2>Join UpGrade</h2>
                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="fname" required>
                    </div>
                    <div class="input-group">
                        <label>Middle Name (Optional)</label>
                        <input type="text" name="mname">
                    </div>
                    <div class="input-group">
                        <label>Last Name</label>
                        <input type="text" name="lname" required>
                    </div>
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" id="reg-password" 
                               pattern="(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).+" 
                               title="Password must contain at least one letter, one number, and one special character." required>
                    </div>
                    <div class="input-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="reg-confirm-password" required>
                    </div>
                    <button type="submit" class="btn">Register</button>
                </form>
                <span class="toggle-link" onclick="toggleForms()">Already have an account? Login.</span>
            </div>

        </div>
    </div>
</div>

<script>
    function toggleForms() {
        const login = document.getElementById('login-form');
        const register = document.getElementById('register-form');
        if (login.style.display === 'none') {
            login.style.display = 'block';
            register.style.display = 'none';
        } else {
            login.style.display = 'none';
            register.style.display = 'block';
        }
    }

    // Modern HTML5 password matching validation
    const password = document.getElementById('reg-password');
    const confirmPassword = document.getElementById('reg-confirm-password');

    function validatePasswordMatch() {
        if (password.value !== confirmPassword.value) {
            // Triggers the browser's native red required warning box
            confirmPassword.setCustomValidity("Passwords do not match!");
        } else {
            // Clears the error so the form can submit successfully
            confirmPassword.setCustomValidity('');
        }
    }

    // Check for matching values dynamically as the user interacts with the fields
    password.addEventListener('change', validatePasswordMatch);
    confirmPassword.addEventListener('keyup', validatePasswordMatch);
</script>

</body>
</html>