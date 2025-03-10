<?php
session_start();
require_once 'config.php';

// Make sure the sanitizeInput function exists
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Make sure createConnection function exists or create a fallback
if (!function_exists('createConnection')) {
    function createConnection() {
        // Assuming config.php defines these variables
        global $db_host, $db_user, $db_pass, $db_name;
        
        // Create connection
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        return $conn;
    }
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$errors = [];
$success = '';
$enrollment_id = 0;
$enrollment_data = null;

// Attempt to create database connection with error handling
try {
    $conn = createConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: enrollments.php");
    exit();
}

$enrollment_id = intval($_GET['id']);

// Get enrollment data
try {
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE enrollment_id = ?");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: enrollments.php");
        exit();
    }
    
    $enrollment_data = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $errors[] = "Error retrieving enrollment data: " . $e->getMessage();
}

// Handle form submission for updating enrollment
if (isset($_POST['update_enrollment'])) {
    $student_id = intval($_POST['student_id']);
    $course_id = intval($_POST['course_id']);
    $enrollment_date = $_POST['enrollment_date'];
    $semester = sanitizeInput($_POST['semester']);
    $academic_year = sanitizeInput($_POST['academic_year']);

    // Validate inputs
    if ($student_id <= 0) {
        $errors[] = "Invalid student selection";
    }
    if ($course_id <= 0) {
        $errors[] = "Invalid course selection";
    }
    if (empty($enrollment_date)) {
        $errors[] = "Enrollment date is required";
    }
    if (empty($semester)) {
        $errors[] = "Semester is required";
    }
    if (empty($academic_year)) {
        $errors[] = "Academic year is required";
    }

    // Check for existing enrollment to prevent duplicates (excluding current record)
    if (empty($errors)) {
        try {
            $check_existing = $conn->prepare("SELECT enrollment_id FROM enrollments 
                                          WHERE student_id = ? AND course_id = ? 
                                          AND academic_year = ? AND semester = ?
                                          AND enrollment_id != ?");
            if (!$check_existing) {
                throw new Exception($conn->error);
            }
            
            $check_existing->bind_param("iissi", $student_id, $course_id, $academic_year, $semester, $enrollment_id);
            $check_existing->execute();
            $existing_result = $check_existing->get_result();

            if ($existing_result->num_rows > 0) {
                $errors[] = "Student is already enrolled in this course for the selected semester and year";
            }
            $check_existing->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // If no errors, proceed with enrollment update
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE enrollments 
                                SET student_id = ?, course_id = ?, enrollment_date = ?, 
                                academic_year = ?, semester = ?
                                WHERE enrollment_id = ?");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            
            $stmt->bind_param("iisssi", $student_id, $course_id, $enrollment_date, $academic_year, $semester, $enrollment_id);
            
            if ($stmt->execute()) {
                $success = "Enrollment updated successfully!";
                
                // Refresh enrollment data
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM enrollments WHERE enrollment_id = ?");
                $stmt->bind_param("i", $enrollment_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $enrollment_data = $result->fetch_assoc();
            } else {
                $errors[] = "Error updating enrollment: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch data with error handling
try {
    // Fetch Students for Dropdown
    $students_query = "SELECT student_id, first_name, last_name, email FROM students";
    $students_result = $conn->query($students_query);
    if (!$students_result) {
        throw new Exception("Student query error: " . $conn->error);
    }

    // Fetch Departments for Dropdown
    $departments_query = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name";
    $departments_result = $conn->query($departments_query);
    if (!$departments_result) {
        throw new Exception("Department query error: " . $conn->error);
    }

    // Fetch Courses for Dropdown (will be filtered by JavaScript)
    $courses_query = "SELECT course_id, course_name, department_id FROM courses ORDER BY course_name";
    $courses_result = $conn->query($courses_query);
    if (!$courses_result) {
        throw new Exception("Course query error: " . $conn->error);
    }
    
    // Get current course's department ID for pre-selecting in dropdown
    if (isset($enrollment_data) && $enrollment_data) {
        $course_dept_query = "SELECT department_id FROM courses WHERE course_id = " . intval($enrollment_data['course_id']);
        $course_dept_result = $conn->query($course_dept_query);
        if ($course_dept_result && $course_dept_result->num_rows > 0) {
            $course_dept_data = $course_dept_result->fetch_assoc();
            $current_department_id = $course_dept_data['department_id'];
        } else {
            $current_department_id = 0;
        }
    }
    
} catch (Exception $e) {
    $errors[] = "Database error: " . $e->getMessage();
    
    // Initialize empty result sets to avoid undefined variable errors
    $students_result = new mysqli_result();
    $courses_result = new mysqli_result();
    $departments_result = new mysqli_result();
    $current_department_id = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Enrollment</title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .edit-enrollment-form input, 
        .edit-enrollment-form select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error-list {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            margin-right: 10px;
            border: none;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .action-buttons {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Enrollment</h1>

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

        <!-- Edit Enrollment Form -->
        <form method="post" class="edit-enrollment-form">
            <div class="form-group">
                <label for="student_id">Student:</label>
                <select name="student_id" id="student_id" required>
                    <option value="">Select Student</option>
                    <?php 
                    if (isset($students_result) && $students_result->num_rows > 0) {
                        // Reset the pointer
                        $students_result->data_seek(0);
                        while ($student = $students_result->fetch_assoc()) {
                            $selected = ($enrollment_data && $student['student_id'] == $enrollment_data['student_id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($student['student_id']) . "' $selected>" . 
                                 htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . 
                                 ' (' . $student['email'] . ')') . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="department_select">Department:</label>
                <select name="department_id" id="department_select" required>
                    <option value="">Select Department</option>
                    <?php 
                    if (isset($departments_result) && $departments_result->num_rows > 0) {
                        // Reset the pointer
                        $departments_result->data_seek(0);
                        while ($department = $departments_result->fetch_assoc()) {
                            $selected = (isset($current_department_id) && $department['dept_id'] == $current_department_id) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($department['dept_id']) . "' $selected>" . 
                                 htmlspecialchars($department['dept_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="course_select">Course:</label>
                <select name="course_id" id="course_select" required>
                    <option value="">Select Department First</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="enrollment_date">Enrollment Date:</label>
                <input type="date" name="enrollment_date" id="enrollment_date" required 
                       value="<?php echo isset($enrollment_data['enrollment_date']) ? htmlspecialchars($enrollment_data['enrollment_date']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="semester">Semester:</label>
                <select name="semester" id="semester" required>
                    <option value="">Select Semester</option>
                    <?php
                    $semesters = ['Fall', 'Spring', 'Summer', 'Winter'];
                    foreach ($semesters as $sem) {
                        $selected = ($enrollment_data && $enrollment_data['semester'] == $sem) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($sem) . "' $selected>" . 
                             htmlspecialchars($sem) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="academic_year">Academic Year:</label>
                <select name="academic_year" id="academic_year" required>
                    <option value="">Select Academic Year</option>
                    <?php
                    $current_year = date('Y');
                    for ($i = $current_year - 3; $i <= $current_year + 1; $i++) {
                        $academic_year = $i . '-' . ($i + 1);
                        $selected = ($enrollment_data && $enrollment_data['academic_year'] == $academic_year) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($academic_year) . "' $selected>" . 
                             htmlspecialchars($academic_year) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="action-buttons">
                <button type="submit" name="update_enrollment" class="btn btn-primary">Update Enrollment</button>
                <a href="enrollment.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- JavaScript for Department-Course filtering -->
    <script>
        // Store all courses in a JavaScript variable
        const allCourses = [
            <?php
            if (isset($courses_result) && $courses_result->num_rows > 0) {
                $courses_result->data_seek(0);
                while ($course = $courses_result->fetch_assoc()) {
                    echo "{
                        id: " . intval($course['course_id']) . ", 
                        name: '" . addslashes($course['course_name']) . "', 
                        departmentId: " . intval($course['department_id']) . "
                    },";
                }
            }
            ?>
        ];

        // Current enrollment course ID
        const currentCourseId = <?php echo (isset($enrollment_data) && $enrollment_data) ? intval($enrollment_data['course_id']) : 0; ?>;

        // Function to filter courses based on selected department
        function filterCourses() {
            const departmentSelect = document.getElementById('department_select');
            const courseSelect = document.getElementById('course_select');
            const selectedDepartmentId = parseInt(departmentSelect.value);
            
            // Clear existing options
            courseSelect.innerHTML = '';
            
            // If no department is selected
            if (!selectedDepartmentId) {
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select Department First';
                courseSelect.appendChild(defaultOption);
                return;
            }
            
            // Add default option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Course';
            courseSelect.appendChild(defaultOption);
            
            // Filter courses by selected department
            const filteredCourses = allCourses.filter(course => course.departmentId === selectedDepartmentId);
            
            // Add filtered courses to the dropdown
            filteredCourses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name;
                if (course.id === currentCourseId) {
                    option.selected = true;
                }
                courseSelect.appendChild(option);
            });
        }

        // Attach event listener to department dropdown
        document.getElementById('department_select').addEventListener('change', filterCourses);
        
        // Initialize on page load
        window.onload = filterCourses;
    </script>
</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>