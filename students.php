<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Initialize errors array
$errors = [];
$success = '';

// Handle Add Student
if (isset($_POST['add_student'])) {
    // Sanitize and validate inputs
    $first_name = trim(sanitizeInput($_POST['first_name']));
    $last_name = trim(sanitizeInput($_POST['last_name']));
    $dob = $_POST['dob'];
    $phone = trim(sanitizeInput($_POST['phone']));
    $email = $_POST['email'];
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

    // If no errors, proceed with insertion
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
                // Prepare and execute student insertion
                $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, dob, phone_number, email, department_id, address, previous_qualifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                // IMPORTANT: Ensure the bind_param types match exactly
                // Use 'ssssisis' to match the actual number and types of variables
                $stmt->bind_param("ssssisis", 
                    $first_name,   // s (string)
                    $last_name,    // s (string)
                    $dob,          // s (string)
                    $phone,        // s (string)
                    $email,        // s (string)
                    $department_id,// i (integer)
                    $address,      // s (string)
                    $prev_qualifications // s (string)
                );
                
                if ($stmt->execute()) {
                    $success = "Student added successfully!";
                    // Clear form inputs after successful submission
                    $_POST = [];
                } else {
                    $errors[] = "Database error: " . $stmt->error;
                    logError("Student insert error: " . $stmt->error);
                }
                $stmt->close();
            }
            $check_email->close();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
            logError("Unexpected error: " . $e->getMessage());
        }
    }
}

// Handle Delete Student
if (isset($_GET['delete_student'])) {
    $student_id = intval($_GET['delete_student']);
    
    try {
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        
        if ($stmt->execute()) {
            $success = "Student deleted successfully!";
        } else {
            $errors[] = "Error deleting student: " . $stmt->error;
            logError("Student delete error: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $errors[] = "Error: " . $e->getMessage();
        logError("Unexpected delete error: " . $e->getMessage());
    }
}

// Fetch Departments for Dropdown
$departments_result = $conn->query("SELECT * FROM departments");

// Fetch Students
$students_result = $conn->query("SELECT s.*, d.dept_name 
                   FROM students s 
                   LEFT JOIN departments d ON s.department_id = d.dept_id 
                   ORDER BY s.student_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Management</title>
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
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .student-table th, .student-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .student-table th {
            background-color: #007bff;
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-edit {
            background-color: #28a745;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .add-student-form {
            margin-bottom: 20px;
        }
        .add-student-form input, 
        .add-student-form select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .error-list {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Management</h1>

        <?php 
        // Display success or error messages
        if (!empty($success)) {
            echo "<div class='success'>" . htmlspecialchars($success) . "</div>";
        }
        if (!empty($errors)) {
            echo "<div class='error-list'>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        ?>

        <!-- Add Student Form -->
        <form method="post" class="add-student-form">
            <h2>Add New Student</h2>
            <input type="text" name="first_name" placeholder="First Name" 
                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
            <input type="text" name="last_name" placeholder="Last Name"
                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
            <input type="date" name="dob" placeholder="Date of Birth" 
                   value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>" required>
            <input type="tel" name="phone" placeholder="Phone Number" 
                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
            <input type="email" name="email" placeholder="Email" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            
            <select name="department" required>
                <option value="">Select Department</option>
                <?php 
                // Reset department result pointer
                $departments_result->data_seek(0);
                while ($dept = $departments_result->fetch_assoc()) {
                    $selected = (isset($_POST['department']) && $_POST['department'] == $dept['dept_id']) ? 'selected' : '';
                    echo "<option value='{$dept['dept_id']}' $selected>" . htmlspecialchars($dept['dept_name']) . "</option>";
                }
                ?>
            </select>
            
            <textarea name="address" placeholder="Address" required><?php 
                echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; 
            ?></textarea>
            <textarea name="prev_qualifications" placeholder="Previous Qualifications" required><?php 
                echo isset($_POST['prev_qualifications']) ? htmlspecialchars($_POST['prev_qualifications']) : ''; 
            ?></textarea>
            
            <button type="submit" name="add_student" class="btn btn-edit">Add Student</button>
        </form>

        <!-- Student List -->
        <h2>Students List</h2>
        <table class="student-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Reset students result pointer
                $students_result->data_seek(0);
                while ($student = $students_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($student['student_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($student['phone_number']) . "</td>";
                    echo "<td>" . htmlspecialchars($student['dept_name']) . "</td>";
                    echo "<td class='action-buttons'>";
                    echo "<a href='edit_student.php?id=" . htmlspecialchars($student['student_id']) . "' class='btn btn-edit'>Edit</a>";
                    echo "<a href='?delete_student=" . htmlspecialchars($student['student_id']) . "' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>