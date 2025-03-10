<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Get quick statistics
$conn = createConnection();

// Count students
$studentCount = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];

// Count departments
$departmentCount = $conn->query("SELECT COUNT(*) as count FROM departments")->fetch_assoc()['count'];

// Count courses
$courseCount = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];

// Total fees collected
$totalFees = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM fees")->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>College Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0px;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 5px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .dashboard-card h3 {
            margin-bottom: 10px;
            color: #333;
        }
        .dashboard-card .count {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .nav-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        .nav-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .nav-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>College Administration System</h1>
            <a href="logout.php" class="nav-button">Logout</a>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Students</h3>
                <div class="count"><?php echo $studentCount; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Departments</h3>
                <div class="count"><?php echo $departmentCount; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Courses</h3>
                <div class="count"><?php echo $courseCount; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Total Fees Collected</h3>
                <div class="count">₹<?php echo number_format($totalFees, 2); ?></div>
            </div>
        </div>

        <div class="nav-buttons">
            <a href="students.php" class="nav-button">Manage Students</a>
            <a href="departments.php" class="nav-button">Manage Departments</a>
            <a href="courses.php" class="nav-button">Manage Courses</a>
            <a href="enrollment.php" class="nav-button">Manage Enrollment</a>
            <a href="fees.php" class="nav-button">Manage Fees</a>
        </div>
    </div>
</body>
</html>