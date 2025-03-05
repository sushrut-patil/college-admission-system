<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Handle Enrollment
if (isset($_POST['enroll_student'])) {
    $student_id = intval($_POST['student_id']);
    $course_id = intval($_POST['course_id']);
    
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $student_id, $course_id);
    if ($stmt->execute()) {
        $success = "Student enrolled successfully!";
    } else {
        $errors[] = "Error enrolling student.";
    }
    $stmt->close();
}

// Fetch Enrollments
$enrollments = $conn->query("SELECT e.enrollment_id, s.first_name, s.last_name, c.course_name, e.enrollment_date FROM enrollments e JOIN students s ON e.student_id = s.id JOIN courses c ON e.course_id = c.id");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enroll Students</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h2 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0px 0px 10px 0px #ccc;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            margin-top: 10px;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover {
            background-color: #218838;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0px 0px 10px 0px #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <h2>Enroll a Student in a Course</h2>
    <form method="POST">
        <label for="student_id">Student ID:</label>
        <input type="number" name="student_id" required>
        <label for="course_id">Course ID:</label>
        <input type="number" name="course_id" required>
        <button type="submit" name="enroll_student">Enroll</button>
    </form>
    
    <h2>Enrollments</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Course Name</th>
                <th>Enrollment Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $enrollments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['enrollment_id']; ?></td>
                    <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                    <td><?php echo $row['course_name']; ?></td>
                    <td><?php echo $row['enrollment_date']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
