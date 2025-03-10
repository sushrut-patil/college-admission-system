<?php
session_start();
require_once 'config.php';

// Check if user is logged in as student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

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

// Fetch courses available for the student's department
$stmt = $conn->prepare("SELECT c.course_id, c.course_name, c.description, 
                        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrolled_students
                        FROM courses c
                        WHERE c.department_id = ?
                        ORDER BY c.course_name");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$available_courses = $stmt->get_result();
$stmt->close();

// Check which courses the student is already enrolled in
$stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_result = $stmt->get_result();
$enrolled_courses = [];
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row['course_id'];
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Available Courses - College Admission System</title>
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
            max-width: 1200px;
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
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f4f4f4;
            color: #333;
        }
        .table tr:hover {
            background-color: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .badge-enrolled {
            background-color: #28a745;
        }
        .empty-message {
            text-align: center;
            padding: 20px;
            color: #666;
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
            <a href="view_courses.php" style="font-weight: bold;">Browse Courses</a>
        </div>
        
        <div class="card">
            <h2>Available Courses for <?php echo htmlspecialchars($department_name); ?> Department</h2>
            
            <?php if ($available_courses->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Description</th>
                            <th>Enrolled Students</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($course = $available_courses->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['description']); ?></td>
                                <td><?php echo $course['enrolled_students']; ?></td>
                                <td>
                                    <?php if (in_array($course['course_id'], $enrolled_courses)): ?>
                                        <span class="badge badge-enrolled">Enrolled</span>
                                    <?php else: ?>
                                        <a href="enroll_course.php?id=<?php echo $course['course_id']; ?>" class="badge">Enroll</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-message">
                    <p>No courses are currently available for your department.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>