<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Handle Add Course
if (isset($_POST['add_course'])) {
    $course_name = sanitizeInput($_POST['course_name']);
    $department_id = intval($_POST['department_id']);
    $description = sanitizeInput($_POST['description']);

    $stmt = $conn->prepare("INSERT INTO courses (course_name, department_id, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $course_name, $department_id, $description);
    
    if ($stmt->execute()) {
        $success = "Course added successfully!";
    } else {
        $error = "Error adding course: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Delete Course
if (isset($_GET['delete_course'])) {
    $course_id = intval($_GET['delete_course']);
    
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    
    if ($stmt->execute()) {
        $success = "Course deleted successfully!";
    } else {
        $error = "Error deleting course: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch Courses with Department Information
$courses_query = "SELECT c.*, d.dept_name, 
                 (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrollment_count
                 FROM courses c
                 JOIN departments d ON c.department_id = d.dept_id";
$courses_result = $conn->query($courses_query);

// Fetch Departments for Dropdown
$departments_query = "SELECT * FROM departments";
$departments_result = $conn->query($departments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Management</title>
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
        .course-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .course-table th, 
        .course-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .course-table th {
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
        .add-course-form input, 
        .add-course-form select,
        .add-course-form textarea {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Course Management</h1>

        <?php 
        // Display success or error messages
        if (isset($success)) {
            echo "<div class='success'>$success</div>";
        }
        if (isset($error)) {
            echo "<div class='error'>$error</div>";
        }
        ?>

        <!-- Add Course Form -->
        <form method="post" class="add-course-form">
            <h2>Add New Course</h2>
            <input type="text" name="course_name" placeholder="Course Name" required>
            
            <select name="department_id" required>
                <option value="">Select Department</option>
                <?php 
                // Reset the pointer
                $departments_result->data_seek(0);
                while ($dept = $departments_result->fetch_assoc()) {
                    echo "<option value='{$dept['dept_id']}'>{$dept['dept_name']}</option>";
                }
                ?>
            </select>
            
            <textarea name="description" placeholder="Course Description" required></textarea>
            
            <button type="submit" name="add_course" class="btn btn-edit">Add Course</button>
        </form>

        <!-- Course List -->
        <h2>Courses List</h2>
        <table class="course-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course Name</th>
                    <th>Department</th>
                    <th>Description</th>
                    <th>Enrollments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                while ($course = $courses_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$course['course_id']}</td>";
                    echo "<td>{$course['course_name']}</td>";
                    echo "<td>{$course['dept_name']}</td>";
                    echo "<td>" . substr($course['description'], 0, 50) . "...</td>";
                    echo "<td>{$course['enrollment_count']}</td>";
                    echo "<td class='action-buttons'>";
                    echo "<a href='edit_course.php?id={$course['course_id']}' class='btn btn-edit'>Edit</a>";
                    echo "<a href='?delete_course={$course['course_id']}' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
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