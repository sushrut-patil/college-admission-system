<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = intval($_GET['id']);
$conn = createConnection();

// Fetch student details
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: students.php");
    exit();
}

$student = $result->fetch_assoc();

// Handle Update Student
if (isset($_POST['update_student'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $dob = $_POST['dob'];
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $department_id = intval($_POST['department']);
    $address = sanitizeInput($_POST['address']);
    $prev_qualifications = sanitizeInput($_POST['prev_qualifications']);

    // Validate inputs
    $errors = [];

    if (!validateEmail($email)) {
        $errors[] = "Invalid email format";
    }

    if (!validatePhoneNumber($phone)) {
        $errors[] = "Invalid phone number. Must be 10 digits.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE students SET 
            first_name = ?, 
            last_name = ?, 
            dob = ?, 
            phone_number = ?, 
            email = ?, 
            department_id = ?, 
            address = ?, 
            previous_qualifications = ? 
            WHERE student_id = ?");
        $stmt->bind_param("sssssissi", 
            $first_name, $last_name, $dob, $phone, $email, 
            $department_id, $address, $prev_qualifications, $student_id);
        
        if ($stmt->execute()) {
            $success = "Student updated successfully!";
            // Refresh student data
            $student = $_POST;
        } else {
            $errors[] = "Error updating student: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch Departments for Dropdown
$departments_query = "SELECT * FROM departments";
$departments_result = $conn->query($departments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .edit-student-form input, 
        .edit-student-form select,
        .edit-student-form textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Student</h1>

        <?php 
        // Display success or error messages
        if (isset($success)) {
            echo "<div class='success'>$success</div>";
        }
        if (!empty($errors)) {
            echo "<div class='error'>" . implode('<br>', $errors) . "</div>";
        }
        ?>

        <form method="post" class="edit-student-form">
            <input type="text" name="first_name" placeholder="First Name" 
                   value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
            
            <input type="text" name="last_name" placeholder="Last Name" 
                   value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
            
            <input type="date" name="dob" placeholder="Date of Birth" 
                   value="<?php echo htmlspecialchars($student['dob']); ?>" required>
            
            <input type="tel" name="phone" placeholder="Phone Number" 
                   value="<?php echo htmlspecialchars($student['phone_number']); ?>" required>
            
            <input type="email" name="email" placeholder="Email" 
                   value="<?php echo htmlspecialchars($student['email']); ?>" required>
            
            <select name="department" required>
                <option value="">Select Department</option>
                <?php 
                while ($dept = $departments_result->fetch_assoc()) {
                    $selected = ($dept['dept_id'] == $student['department_id']) ? 'selected' : '';
                    echo "<option value='{$dept['dept_id']}' $selected>{$dept['dept_name']}</option>";
                }
                ?>
            </select>
            
            <textarea name="address" placeholder="Address" required><?php 
                echo htmlspecialchars($student['address']); 
            ?></textarea>
            
            <textarea name="prev_qualifications" placeholder="Previous Qualifications" required><?php 
                echo htmlspecialchars($student['previous_qualifications']); 
            ?></textarea>
            
            <button type="submit" name="update_student" class="btn">Update Student</button>
            <a href="students.php" class="btn" style="background-color: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>