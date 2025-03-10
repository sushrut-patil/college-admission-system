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

// Fetch student information
$stmt = $conn->prepare("SELECT s.*, d.dept_name 
                        FROM students s 
                        LEFT JOIN departments d ON s.department_id = d.dept_id 
                        WHERE s.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch enrolled courses
$stmt = $conn->prepare("SELECT c.course_name, c.description, e.enrollment_date
                        FROM enrollments e
                        JOIN courses c ON e.course_id = c.course_id
                        WHERE e.student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result();
$stmt->close();

// Fetch fee payment history
$stmt = $conn->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$fees = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - College Admission System</title>
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
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .info-row {
            margin-bottom: 10px;
            display: flex;
        }
        .info-row strong {
            width: 150px;
            display: inline-block;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f4f4f4;
            color: #333;
        }
        .section {
            margin-bottom: 30px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 0.8rem;
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
        <div class="dashboard-grid">
            <div class="card">
                <h2>Personal Information</h2>
                <div class="info-row">
                    <strong>Name:</strong> 
                    <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Email:</strong> 
                    <span><?php echo htmlspecialchars($student['email']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Phone:</strong> 
                    <span><?php echo htmlspecialchars($student['phone_number']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Date of Birth:</strong> 
                    <span><?php echo date('F j, Y', strtotime($student['dob'])); ?></span>
                </div>
                <div class="info-row">
                    <strong>Department:</strong> 
                    <span><?php echo htmlspecialchars($student['dept_name']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Address:</strong> 
                    <span><?php echo htmlspecialchars($student['address']); ?></span>
                </div>
            </div>
            
            <div class="card">
                <h2>Quick Actions</h2>
                <ul>
                    <li><a href="edit_student.php?id=<?php echo $student_id; ?>">Edit Student Profile</a></li>
                    <li><a href="view_courses.php">Browse Courses</a></li>
                </ul>
            </div>
        </div>
        
        <div class="section">
            <div class="card">
                <h2>Enrolled Courses</h2>
                <?php if ($enrolled_courses->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Enrollment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $enrolled_courses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['description']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($course['enrollment_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>You are not enrolled in any courses yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="section">
            <div class="card">
                <h2>Fee Payment History</h2>
                <?php if ($fees->num_rows > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Academic Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($fee = $fees->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('F j, Y', strtotime($fee['payment_date'])); ?></td>
                                    <td>$<?php echo number_format($fee['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($fee['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No payment records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>