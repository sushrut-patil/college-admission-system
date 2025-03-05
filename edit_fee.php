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
$fee_id = 0;
$student_id = 0;
$amount = 0;
$payment_date = '';
$payment_method = '';
$academic_year = '';
$error = '';
$success = '';

// Check if fee ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = "Invalid fee payment ID.";
} else {
    $fee_id = intval($_GET['id']);

    // Fetch fee payment details
    $stmt = $conn->prepare("SELECT * FROM fees WHERE fee_id = ?");
    $stmt->bind_param("i", $fee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $error = "Fee payment record not found.";
    } else {
        $fee = $result->fetch_assoc();
        $student_id = $fee['student_id'];
        $amount = $fee['amount'];
        $payment_date = $fee['payment_date'];
        $payment_method = $fee['payment_method'];
        $academic_year = $fee['academic_year'];
    }
    $stmt->close();
}

// Handle Fee Payment Update
if (isset($_POST['update_fee_payment'])) {
    $student_id = intval($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $academic_year = sanitizeInput($_POST['academic_year']);

    $stmt = $conn->prepare("UPDATE fees SET student_id = ?, amount = ?, payment_date = ?, payment_method = ?, academic_year = ? WHERE fee_id = ?");
    $stmt->bind_param("idsss", $student_id, $amount, $payment_date, $payment_method, $academic_year, $fee_id);
    
    if ($stmt->execute()) {
        $success = "Fee payment updated successfully!";
    } else {
        $error = "Error updating fee payment: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch Students for Dropdown
$students_query = "SELECT student_id, first_name, last_name, email FROM students";
$students_result = $conn->query($students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Fee Payment</title>
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
        .edit-fee-form input, 
        .edit-fee-form select {
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
        <h1>Edit Fee Payment</h1>

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

        <form method="post" class="edit-fee-form">
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php 
                $students_result->data_seek(0);
                while ($student = $students_result->fetch_assoc()) {
                    $selected = ($student['student_id'] == $student_id) ? 'selected' : '';
                    echo "<option value='{$student['student_id']}' $selected>{$student['first_name']} {$student['last_name']} ({$student['email']})</option>";
                }
                ?>
            </select>
            
            <input type="number" name="amount" placeholder="Amount" step="0.01" 
                   value="<?php echo number_format($amount, 2); ?>" required>
            
            <input type="date" name="payment_date" 
                   value="<?php echo htmlspecialchars($payment_date); ?>" required>
            
            <select name="payment_method" required>
                <option value="">Select Payment Method</option>
                <option value="Cash" <?php echo ($payment_method == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="Bank Transfer" <?php echo ($payment_method == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="Credit Card" <?php echo ($payment_method == 'Credit Card') ? 'selected' : ''; ?>>Credit Card</option>
                <option value="Debit Card" <?php echo ($payment_method == 'Debit Card') ? 'selected' : ''; ?>>Debit Card</option>
                <option value="Online Payment" <?php echo ($payment_method == 'Online Payment') ? 'selected' : ''; ?>>Online Payment</option>
            </select>
            
            <select name="academic_year" required>
                <option value="">Select Academic Year</option>
                <?php
                $current_year = date('Y');
                for ($i = $current_year - 3; $i <= $current_year + 1; $i++) {
                    $academic_year_option = $i . '-' . ($i + 1);
                    $selected = ($academic_year_option == $academic_year) ? 'selected' : '';
                    echo "<option value='$academic_year_option' $selected>$academic_year_option</option>";
                }
                ?>
            </select>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="update_fee_payment" class="btn btn-update">Update Payment</button>
                <a href="fees.php" class="btn btn-cancel">Cancel</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</body>
</html>
<?php 
$conn->close(); 
?>