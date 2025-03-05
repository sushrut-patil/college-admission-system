<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

$conn = createConnection();

// Handle Add Fee Payment
if (isset($_POST['add_fee_payment'])) {
    $student_id = intval($_POST['student_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    $academic_year = sanitizeInput($_POST['academic_year']);

    $stmt = $conn->prepare("INSERT INTO fees (student_id, amount, payment_date, payment_method, academic_year) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $student_id, $amount, $payment_date, $payment_method, $academic_year);
    
    if ($stmt->execute()) {
        $success = "Fee payment recorded successfully!";
    } else {
        $error = "Error recording fee payment: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Delete Fee Payment
if (isset($_GET['delete_fee'])) {
    $fee_id = intval($_GET['delete_fee']);
    
    $stmt = $conn->prepare("DELETE FROM fees WHERE fee_id = ?");
    $stmt->bind_param("i", $fee_id);
    
    if ($stmt->execute()) {
        $success = "Fee payment deleted successfully!";
    } else {
        $error = "Error deleting fee payment: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch Fee Payments with Student Information
$fees_query = "SELECT f.*, 
               s.first_name, 
               s.last_name, 
               s.email,
               d.dept_name
               FROM fees f
               JOIN students s ON f.student_id = s.student_id
               JOIN departments d ON s.department_id = d.dept_id
               ORDER BY f.payment_date DESC";
$fees_result = $conn->query($fees_query);

// Fetch Students for Dropdown
$students_query = "SELECT student_id, first_name, last_name, email FROM students";
$students_result = $conn->query($students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fees Management</title>
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
        .fees-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .fees-table th, 
        .fees-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .fees-table th {
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
        .add-fee-form input, 
        .add-fee-form select {
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
        .fee-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .fee-summary-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            width: 22%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Fees Management</h1>

        <?php 
        // Display success or error messages
        if (isset($success)) {
            echo "<div class='success'>$success</div>";
        }
        if (isset($error)) {
            echo "<div class='error'>$error</div>";
        }

        // Calculate Fee Summaries
        $total_fees_query = "SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            AVG(amount) as avg_amount
            FROM fees";
        $fee_summary = $conn->query($total_fees_query)->fetch_assoc();
        ?>

        <!-- Fee Summary Cards -->
        <div class="fee-summary">
            <div class="fee-summary-card">
                <h3>Total Payments</h3>
                <p><?php echo $fee_summary['total_payments']; ?></p>
            </div>
            <div class="fee-summary-card">
                <h3>Total Amount</h3>
                <p>₹<?php echo number_format($fee_summary['total_amount'], 2); ?></p>
            </div>
            <div class="fee-summary-card">
                <h3>Average Payment</h3>
                <p>₹<?php echo number_format($fee_summary['avg_amount'], 2); ?></p>
            </div>
            <div class="fee-summary-card">
                <h3>Unpaid Students</h3>
                <p><?php 
                    $unpaid_query = "SELECT COUNT(*) as unpaid FROM students s 
                                     LEFT JOIN fees f ON s.student_id = f.student_id 
                                     WHERE f.fee_id IS NULL";
                    echo $conn->query($unpaid_query)->fetch_assoc()['unpaid'];
                ?></p>
            </div>
        </div>

        <!-- Add Fee Payment Form -->
        <form method="post" class="add-fee-form">
            <h2>Record Fee Payment</h2>
            <select name="student_id" required>
                <option value="">Select Student</option>
                <?php 
                // Reset the pointer
                $students_result->data_seek(0);
                while ($student = $students_result->fetch_assoc()) {
                    echo "<option value='{$student['student_id']}'>{$student['first_name']} {$student['last_name']} ({$student['email']})</option>";
                }
                ?>
            </select>
            
            <input type="number" name="amount" placeholder="Amount" step="0.01" required>
            <input type="date" name="payment_date" required>
            
            <select name="payment_method" required>
                <option value="">Select Payment Method</option>
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Debit Card">Debit Card</option>
                <option value="Online Payment">Online Payment</option>
            </select>
            
            <select name="academic_year" required>
                <option value="">Select Academic Year</option>
                <?php
                $current_year = date('Y');
                for ($i = $current_year - 3; $i <= $current_year + 1; $i++) {
                    $academic_year = $i . '-' . ($i + 1);
                    echo "<option value='$academic_year'>$academic_year</option>";
                }
                ?>
            </select>
            
            <button type="submit" name="add_fee_payment" class="btn btn-edit">Record Payment</button>
        </form>

        <!-- Fee Payments List -->
        <h2>Fee Payments History</h2>
        <table class="fees-table">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Student Name</th>
                    <th>Department</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Payment Method</th>
                    <th>Academic Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                while ($fee = $fees_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$fee['fee_id']}</td>";
                    echo "<td>{$fee['first_name']} {$fee['last_name']}</td>";
                    echo "<td>{$fee['dept_name']}</td>";
                    echo "<td>₹" . number_format($fee['amount'], 2) . "</td>";
                    echo "<td>{$fee['payment_date']}</td>";
                    echo "<td>{$fee['payment_method']}</td>";
                    echo "<td>{$fee['academic_year']}</td>";
                    echo "<td class='action-buttons'>";
                    echo "<a href='edit_fee.php?id={$fee['fee_id']}' class='btn btn-edit'>Edit</a>";
                    echo "<a href='?delete_fee={$fee['fee_id']}' class='btn btn-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
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