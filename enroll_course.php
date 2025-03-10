<?php
session_start();
require_once 'config.php';

// Check if user is logged in as student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Get student data from session
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Initialize variables
$errors = [];
$success = '';
$course_name = '';
$course_id = null;

// Create database connection
$conn = createConnection();

// Fetch student's department
$stmt = $conn->prepare("SELECT s.department_id, d.dept_name 
                        FROM students s 
                        LEFT JOIN departments d ON s.department_id = d.dept_id 
                        WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_info = $result->fetch_assoc();
$department_id = $student_info['department_id'];
$department_name = $student_info['dept_name'];
$stmt->close();

// Check if course ID is provided in the URL
if (isset($_GET['id'])) {
    $course_id = intval($_GET['id']);
    
    // Fetch course information to verify it exists and belongs to the student's department
    $stmt = $conn->prepare("SELECT course_name, department_id, description FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $errors[] = "Course not found";
    } else {
        $course_data = $result->fetch_assoc();
        $course_name = $course_data['course_name'];
        
        // Verify course belongs to student's department
        if ($course_data['department_id'] != $department_id) {
            $errors[] = "This course is not available for your department";
        }
    }
    $stmt->close();
    
    // Check if student is already enrolled in this course
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "You are already enrolled in this course";
    }
    $stmt->close();
}

// Handle form submission for enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll']) && empty($errors)) {
    $course_id = intval($_POST['course_id']);
    $enrollment_date = date('Y-m-d'); // Current date
    $semester = $_POST['semester'];
    $academic_year = $_POST['academic_year'];
    
    // Insert enrollment record
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, semester, academic_year) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $student_id, $course_id, $enrollment_date, $semester, $academic_year);
    
    if ($stmt->execute()) {
        $success = "Successfully enrolled in " . htmlspecialchars($course_name);
    } else {
        $errors[] = "Error enrolling in course: " . $stmt->error;
    }
    $stmt->close();
}

// Close the database connection if we're redirecting
if (!empty($success)) {
    $conn->close();
    header("Location: view_courses.php?success=" . urlencode($success));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll in Course - College Admission System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logout {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        .logout:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .nav-links {
            margin-bottom: 20px;
        }
        .nav-links a {
            text-decoration: none;
            color: #007bff;
            margin-right: 15px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 15px;
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
        .btn {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0069d9;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .readonly-field {
            background-color: #f9f9f9;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Dashboard</h1>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($student_name); ?></span>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="nav-links">
            <a href="student_dashboard.php">Dashboard</a>
            <a href="edit_student.php?id=<?php echo $student_id; ?>">Edit Profile</a>
            <a href="view_courses.php">Browse Courses</a>
        </div>
        
        <div class="card">
            <h2>Enroll in Course</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <p><a href="view_courses.php">Return to course list</a></p>
            <?php elseif ($course_id): ?>
                <form method="post" action="">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <div class="form-group">
                        <label>Student:</label>
                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($student_name); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Department:</label>
                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($department_name); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Course:</label>
                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($course_name); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="semester">Semester:</label>
                        <select id="semester" name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <option value="Fall">Fall</option>
                            <option value="Spring">Spring</option>
                            <option value="Summer">Summer</option>
                            <option value="Winter">Winter</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year:</label>
                        <select id="academic_year" name="academic_year" class="form-control" required>
                            <option value="">Select Academic Year</option>
                            <?php
                            $current_year = date('Y');
                            for ($i = $current_year; $i <= $current_year + 1; $i++) {
                                $academic_year = $i . '-' . ($i + 1);
                                echo "<option value='" . htmlspecialchars($academic_year) . "'>" . 
                                     htmlspecialchars($academic_year) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="enroll" class="btn">Confirm Enrollment</button>
                    <a href="view_courses.php" style="margin-left: 10px; text-decoration: none;">Cancel</a>
                </form>
            <?php else: ?>
                <p>No course selected for enrollment.</p>
                <p><a href="view_courses.php">Return to course list</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>