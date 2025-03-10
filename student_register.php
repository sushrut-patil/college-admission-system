<?php
session_start();
require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = createConnection();
    
    // Sanitize and validate inputs
    $first_name = trim(sanitizeInput($_POST['first_name']));
    $last_name = trim(sanitizeInput($_POST['last_name']));
    $dob = $_POST['dob'];
    $phone = trim(sanitizeInput($_POST['phone']));
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department_id = isset($_POST['department']) ? intval($_POST['department']) : 0;
    $address = $_POST['address'];
    $prev_qualifications = trim(sanitizeInput($_POST['prev_qualifications']));

    // Comprehensive input validation
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    if (empty($dob)) {
        $errors[] = "Date of Birth is required";
    }
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if ($department_id <= 0) {
        $errors[] = "Department selection is required";
    }
    if (empty($address)) {
        $errors[] = "Address is required";
    }

    // Additional specific validations
    if (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    }

    if (!validatePhoneNumber($phone)) {
        $errors[] = "Invalid phone number. Must be 10 digits.";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Check for existing email
            $check_email = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $check_email_result = $check_email->get_result();

            if ($check_email_result->num_rows > 0) {
                $errors[] = "Email already exists in the system";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare and execute student insertion
                $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, dob, phone_number, email, password, department_id, address, previous_qualifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sssssssss", 
                    $first_name,
                    $last_name,
                    $dob,
                    $phone,
                    $email,
                    $hashed_password,
                    $department_id,
                    $address,
                    $prev_qualifications
                );
                
                if ($stmt->execute()) {
                    // Set a session flash message for login page
                    $_SESSION['registration_success'] = "Registration successful! You can now login.";
                    
                    // Redirect to login page
                    header("Location: index.php");
                    exit();
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                }
                $stmt->close();
            }
            $check_email->close();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
    
    $conn->close();
}

// Get departments for dropdown
$conn = createConnection();
$departments_result = $conn->query("SELECT * FROM departments");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration - College Admission System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-submit {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn-submit:hover {
            background-color: #0056b3;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-list {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Registration</h1>
        
        <?php 
        // Display success or error messages
        if (!empty($success)) {
            echo "<div class='success'>" . htmlspecialchars($success) . "</div>";
        }
        if (!empty($errors)) {
            echo "<div class='error-list'><ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul></div>";
        }
        ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" class="form-control" 
                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" class="form-control"
                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="dob">Date of Birth:</label>
                <input type="date" id="dob" name="dob" class="form-control"
                       value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number:</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="At least 8 characters" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="department">Department:</label>
                <select id="department" name="department" class="form-control" required>
                    <option value="">Select Department</option>
                    <?php 
                    while ($dept = $departments_result->fetch_assoc()) {
                        $selected = (isset($_POST['department']) && $_POST['department'] == $dept['dept_id']) ? 'selected' : '';
                        echo "<option value='{$dept['dept_id']}' $selected>" . htmlspecialchars($dept['dept_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address" class="form-control" rows="3" required><?php 
                    echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="prev_qualifications">Previous Qualifications:</label>
                <textarea id="prev_qualifications" name="prev_qualifications" class="form-control" rows="3" required><?php 
                    echo isset($_POST['prev_qualifications']) ? htmlspecialchars($_POST['prev_qualifications']) : ''; 
                ?></textarea>
            </div>
            
            <button type="submit" class="btn-submit">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>
</body>
</html>