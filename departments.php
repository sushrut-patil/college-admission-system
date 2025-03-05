<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Handle Add Department
if (isset($_POST['add_department'])) {
    $dept_name = sanitizeInput($_POST['dept_name']);
    $dept_code = sanitizeInput($_POST['dept_code']);

    $stmt = $conn->prepare("INSERT INTO departments (dept_name, dept_code) VALUES (?, ?)");
    $stmt->bind_param("ss", $dept_name, $dept_code);
    
    if ($stmt->execute()) {
        $success = "Department added successfully!";
    } else {
        $error = "Error adding department: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Delete Department
if (isset($_GET['delete_department'])) {
    $dept_id = intval($_GET['delete_department']);
    
    $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
    $stmt->bind_param("i", $dept_id);
    
    if ($stmt->execute()) {
        $success = "Department deleted successfully!";
    } else {
        $error = "Error deleting department: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch Departments
$departments_query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM students s WHERE s.department_id = d.dept_id) as student_count,
                     (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.dept_id) as course_count
                     FROM departments d";
$departments_result = $conn->query($departments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Department Management</title>
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
        .department-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .department-table th, 
        .department-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .department-table th {
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
        .add-department-form input {
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
        <h1>Department Management</h1>

        <?php 
        // Display success or error messages
        if (isset($success)) {
            echo "<div class='success'>$success</div>";
        }
        if (isset($error)) {
            echo "<div class='error'>$error</div>";
        }
        ?>

        <!-- Add Department Form -->
        <form method="post" class="add-department-form">
            <h2>Add New Department</h2>
            <input type="text" name="dept_name" placeholder="Department Name" required>
            <input type="text" name="dept_code" placeholder="Department Code" required>
            <button type="submit" name="add_department" class="btn btn-edit">Add Department</button>
        </form>

        <!-- Department List -->
        <h2>Departments List</h2>
        <table class="department-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department Name</th>
                    <th>Department Code</th>
                    <th>Students Count</th>
                    <th>Courses Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                while ($department = $departments_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$department['dept_id']}</td>";
                    echo "<td>{$department['dept_name']}</td>";
                    echo "<td>{$department['dept_code']}</td>";
                    echo "<td>{$department['student_count']}</td>";
                    echo "<td>{$department['course_count']}</td>";
                    echo "<td class='action-buttons'>";
                    echo "<a href='edit_department.php?id={$department['dept_id']}' class='btn btn-edit'>Edit</a>";
                    echo "<a href='?delete_department={$department['dept_id']}' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
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