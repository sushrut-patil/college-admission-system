<?php
session_start();
require_once 'config.php';

// Login Page
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    $conn = createConnection();
    
    if ($user_type == 'admin') {
        // Admin login
        $stmt = $conn->prepare("SELECT admin_id, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['logged_in'] = true;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    } else {
        // Student login
        $stmt = $conn->prepare("SELECT student_id, password, first_name, last_name FROM students WHERE email = ?");
        $stmt->bind_param("s", $username); // Using email as username for students
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $student = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['user_type'] = 'student';
                $_SESSION['logged_in'] = true;
                header("Location: student_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>College Admission System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .page-title {
            font-size: 2.5rem;
            margin-bottom: 30px;
            color: #333;
            text-align: center;
        }
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 350px;
            margin-bottom: 20px;
        }
        .login-container input, .login-container select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-container button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .login-container button:hover {
            background-color: #0056b3;
        }
        .register-button {
            padding: 12px 24px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .register-button:hover {
            background-color: #c82333;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1 class="page-title">College Admission System</h1>
    
    <div class="login-container">
        <h2 style="text-align: center;">Login</h2>
        <?php if(isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="user_type">Login As:</label>
                <select id="user_type" name="user_type" required>
                    <option value="student">Student</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="username">Username/Email:</label>
                <input type="text" id="username" name="username" placeholder="Enter username or email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
    
    <a href="student_register.php" class="register-button">Register as Student</a>
</body>
</html>