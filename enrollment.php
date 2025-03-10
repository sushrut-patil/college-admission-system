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

// Debug session
// Uncomment this line to bypass session check for testing
// $_SESSION['logged_in'] = true;

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Attempt to create database connection with error handling
try {
    $conn = createConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize errors and success messages
$errors = [];
$success = '';

// Handle Enrollment
if (isset($_POST['enroll_student'])) {
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

    // Check for existing enrollment to prevent duplicates
    if (empty($errors)) {
        try {
            $check_existing = $conn->prepare("SELECT enrollment_id FROM enrollments 
                                          WHERE student_id = ? AND course_id = ? 
                                          AND academic_year = ? AND semester = ?");
            if (!$check_existing) {
                throw new Exception($conn->error);
            }
            
            $check_existing->bind_param("iiss", $student_id, $course_id, $academic_year, $semester);
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

    // If no errors, proceed with enrollment
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO enrollments 
                                (student_id, course_id, enrollment_date, academic_year, semester) 
                                VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            
            $stmt->bind_param("iisss", $student_id, $course_id, $enrollment_date, $academic_year, $semester);
            
            if ($stmt->execute()) {
                $success = "Student enrolled successfully!";
            } else {
                $errors[] = "Error enrolling student: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Delete Enrollment
if (isset($_GET['delete_enrollment'])) {
    $enrollment_id = intval($_GET['delete_enrollment']);
    
    try {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        
        $stmt->bind_param("i", $enrollment_id);
        
        if ($stmt->execute()) {
            $success = "Enrollment deleted successfully!";
        } else {
            $errors[] = "Error deleting enrollment: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Fetch data with error handling
try {
    // Fetch Enrollment Details with Full Student and Course Information
    $enrollments_query = "SELECT e.*, 
                         s.first_name, s.last_name, s.email, 
                         c.course_name, d.dept_name
                         FROM enrollments e
                         JOIN students s ON e.student_id = s.student_id
                         JOIN courses c ON e.course_id = c.course_id
                         JOIN departments d ON s.department_id = d.dept_id
                         ORDER BY e.enrollment_date DESC";
    $enrollments_result = $conn->query($enrollments_query);
    if (!$enrollments_result) {
        throw new Exception("Enrollment query error: " . $conn->error);
    }

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
    
    // Calculate Enrollment Summaries - Simplified to avoid subquery issues
    $enrollment_summary_query = "SELECT 
        COUNT(*) as total_enrollments,
        COUNT(DISTINCT student_id) as unique_students,
        COUNT(DISTINCT course_id) as unique_courses
        FROM enrollments";
    $enrollment_summary_result = $conn->query($enrollment_summary_query);
    if (!$enrollment_summary_result) {
        throw new Exception("Summary query error: " . $conn->error);
    }
    $enrollment_summary = $enrollment_summary_result->fetch_assoc();
    
    // Calculate average courses per student separately
    $avg_courses_query = "SELECT AVG(course_count) as avg_courses_per_student FROM (
        SELECT student_id, COUNT(course_id) as course_count 
        FROM enrollments 
        GROUP BY student_id
    ) as student_course_counts";
    $avg_courses_result = $conn->query($avg_courses_query);
    if ($avg_courses_result) {
        $avg_courses = $avg_courses_result->fetch_assoc();
        $enrollment_summary['avg_courses_per_student'] = $avg_courses['avg_courses_per_student'];
    } else {
        $enrollment_summary['avg_courses_per_student'] = 0;
    }
    
} catch (Exception $e) {
    // Handle database errors
    $errors[] = "Database error: " . $e->getMessage();
    
    // Initialize empty result sets to avoid undefined variable errors
    $enrollments_result = new mysqli_result();
    $students_result = new mysqli_result();
    $courses_result = new mysqli_result();
    $departments_result = new mysqli_result();
    $enrollment_summary = [
        'total_enrollments' => 0,
        'unique_students' => 0,
        'unique_courses' => 0,
        'avg_courses_per_student' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollment Management</title>
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
        .enrollments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .enrollments-table th, 
        .enrollments-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .enrollments-table th {
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
            cursor: pointer;
        }
        .btn-edit {
            background-color: #28a745;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .add-enrollment-form input, 
        .add-enrollment-form select {
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
        .error-list {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .enrollment-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .enrollment-summary-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            width: 22%;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .enrollment-summary-card {
                width: 45%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Enrollment Management</h1>

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

        <!-- Enrollment Summary Cards -->
        <div class="enrollment-summary">
            <div class="enrollment-summary-card">
                <h3>Total Enrollments</h3>
                <p><?php echo isset($enrollment_summary['total_enrollments']) ? $enrollment_summary['total_enrollments'] : '0'; ?></p>
            </div>
            <div class="enrollment-summary-card">
                <h3>Unique Students</h3>
                <p><?php echo isset($enrollment_summary['unique_students']) ? $enrollment_summary['unique_students'] : '0'; ?></p>
            </div>
            <div class="enrollment-summary-card">
                <h3>Unique Courses</h3>
                <p><?php echo isset($enrollment_summary['unique_courses']) ? $enrollment_summary['unique_courses'] : '0'; ?></p>
            </div>
            <div class="enrollment-summary-card">
                <h3>Avg Courses/Student</h3>
                <p><?php echo isset($enrollment_summary['avg_courses_per_student']) ? number_format($enrollment_summary['avg_courses_per_student'], 2) : '0.00'; ?></p>
            </div>
        </div>

        <!-- Add Enrollment Form -->
        <form method="post" class="add-enrollment-form">
            <h2>Enroll Student in Course</h2>
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php 
                if (isset($students_result) && $students_result->num_rows > 0) {
                    // Reset the pointer
                    $students_result->data_seek(0);
                    while ($student = $students_result->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($student['student_id']) . "'>" . 
                             htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . 
                             ' (' . $student['email'] . ')') . "</option>";
                    }
                }
                ?>
            </select>
            
            <select name="department_id" id="department_select" required>
                <option value="">Select Department</option>
                <?php 
                if (isset($departments_result) && $departments_result->num_rows > 0) {
                    // Reset the pointer
                    $departments_result->data_seek(0);
                    while ($department = $departments_result->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($department['dept_id']) . "'>" . 
                             htmlspecialchars($department['dept_name']) . "</option>";
                    }
                }
                ?>
            </select>
            
            <select name="course_id" id="course_select" required>
                <option value="">Select Department First</option>
            </select>
            
            <input type="date" name="enrollment_date" required>
            
            <select name="semester" required>
                <option value="">Select Semester</option>
                <option value="Fall">Fall</option>
                <option value="Spring">Spring</option>
                <option value="Summer">Summer</option>
                <option value="Winter">Winter</option>
            </select>
            
            <select name="academic_year" required>
                <option value="">Select Academic Year</option>
                <?php
                $current_year = date('Y');
                for ($i = $current_year - 3; $i <= $current_year + 1; $i++) {
                    $academic_year = $i . '-' . ($i + 1);
                    echo "<option value='" . htmlspecialchars($academic_year) . "'>" . 
                         htmlspecialchars($academic_year) . "</option>";
                }
                ?>
            </select>
            
            <button type="submit" name="enroll_student" class="btn btn-edit">Enroll Student</button>
        </form>

        <!-- Enrollment List -->
        <h2>Enrollment History</h2>
        <table class="enrollments-table">
            <thead>
                <tr>
                    <th>Enrollment ID</th>
                    <th>Student Name</th>
                    <th>Department</th>
                    <th>Course</th>
                    <th>Enrollment Date</th>
                    <th>Semester</th>
                    <th>Academic Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (isset($enrollments_result) && $enrollments_result->num_rows > 0) {
                    // Reset the pointer
                    $enrollments_result->data_seek(0);
                    while ($enrollment = $enrollments_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($enrollment['enrollment_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['dept_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['course_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['enrollment_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['semester']) . "</td>";
                        echo "<td>" . htmlspecialchars($enrollment['academic_year']) . "</td>";
                        echo "<td class='action-buttons'>";
                        echo "<a href='edit_enrollment.php?id=" . htmlspecialchars($enrollment['enrollment_id']) . 
                             "' class='btn btn-edit'>Edit</a>";
                        echo "<a href='?delete_enrollment=" . htmlspecialchars($enrollment['enrollment_id']) . 
                             "' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No enrollments found</td></tr>";
                }
                ?>
            </tbody>
        </table>
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