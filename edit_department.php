<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Check if department ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: departments.php");
    exit();
}

$dept_id = intval($_GET['id']);
$conn = createConnection();

// Fetch department details
$stmt = $conn->prepare("SELECT * FROM departments WHERE dept_id = ?");
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: departments.php");
    exit();
}

$department = $result->fetch_assoc();

// Handle Update Department
if (isset($_POST['update_department'])) {
    $dept_name = sanitizeInput($_POST['dept_name']);
    $dept_code = sanitizeInput($_POST['dept_code']);

    $stmt = $conn->prepare("UPDATE departments SET dept_name = ?, dept_code = ? WHERE dept_id = ?");
    $stmt->bind_param("ssi", $dept_name, $dept_code, $dept_id);
    
    if ($stmt->execute()) {
        $success = "Department updated successfully!";
        // Refresh department data
        $department = $_POST + ['dept_id' => $dept_id];
    } else {
        $error = "Error updating department: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch department statistics
$students_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE department_id = $dept_id")->fetch_assoc()['count'];
$courses_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE department_id = $dept_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Department</title>
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
            max-width: 600px;
            margin: 0 auto;
        }
        .edit-department-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0056b3;
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
        .department-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .stat-item {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Department</h1>

        <?php 
        // Display success or error messages
        if (isset($success)) {
            echo "<div class='success'>$success</div>";
        }
        if (isset($error)) {
            echo "<div class='error'>$error</div>";
        }
        ?>

        <div class="department-stats">
            <div class="stat-item">
                <h3>Students</h3>
                <p><?php echo $students_count; ?></p>
            </div>
            <div class="stat-item">
                <h3>Courses</h3>
                <p><?php echo $courses_count; ?></p>
            </div>
        </div>

        <form method="post" class="edit-department-form">
            <input type="text" name="dept_name" placeholder="Department Name" 
                   value="<?php echo htmlspecialchars($department['dept_name']); ?>" required>
            
            <input type="text" name="dept_code" placeholder="Department Code" 
                   value="<?php echo htmlspecialchars($department['dept_code']); ?>" required>
            
            <button type="submit" name="update_department" class="btn">Update Department</button>
            <a href="departments.php" class="btn" style="background-color: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>