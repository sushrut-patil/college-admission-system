<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Initialize variables
$course_id = 0;
$course_name = '';
$department_id = 0;
$description = '';
$error = '';
$success = '';

// Check if course ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = "Invalid course ID.";
} else {
    $course_id = intval($_GET['id']);

    // Fetch course details
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $error = "Course not found.";
    } else {
        $course = $result->fetch_assoc();
        $course_name = $course['course_name'];
        $department_id = $course['department_id'];
        $description = $course['description'];
    }
    $stmt->close();
}

// Handle Course Update
if (isset($_POST['update_course'])) {
    $course_name = sanitizeInput($_POST['course_name']);
    $department_id = intval($_POST['department_id']);
    $description = sanitizeInput($_POST['description']);

    $stmt = $conn->prepare("UPDATE courses SET course_name = ?, department_id = ?, description = ? WHERE course_id = ?");
    $stmt->bind_param("sisi", $course_name, $department_id, $description, $course_id);
    
    if ($stmt->execute()) {
        $success = "Course updated successfully!";
    } else {
        $error = "Error updating course: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch Departments for Dropdown
$departments_query = "SELECT * FROM departments";
$departments_result = $conn->query($departments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Course</title>
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
        .edit-course-form input, 
        .edit-course-form select,
        .edit-course-form textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-update {
            background-color: #28a745;
            color: white;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
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
        <h1>Edit Course</h1>

        <?php 
        // Display error or success messages
        if (!empty($error)) {
            echo "<div class='error'>$error</div>";
        }
        if (!empty($success)) {
            echo "<div class='success'>$success</div>";
        }

        // Only show form if no error occurred
        if (empty($error)): 
        ?>

        <form method="post" class="edit-course-form">
            <input type="text" name="course_name" placeholder="Course Name" 
                   value="<?php echo htmlspecialchars($course_name); ?>" required>
            
            <select name="department_id" required>
                <option value="">Select Department</option>
                <?php 
                $departments_result->data_seek(0);
                while ($dept = $departments_result->fetch_assoc()) {
                    $selected = ($dept['dept_id'] == $department_id) ? 'selected' : '';
                    echo "<option value='{$dept['dept_id']}' $selected>{$dept['dept_name']}</option>";
                }
                ?>
            </select>
            
            <textarea name="description" placeholder="Course Description" required><?php 
                echo htmlspecialchars($description); 
            ?></textarea>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="update_course" class="btn btn-update">Update Course</button>
                <a href="courses.php" class="btn btn-cancel">Cancel</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>