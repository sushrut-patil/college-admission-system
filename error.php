<?php
// Error handling and display page
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 500;
$error_messages = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Page Not Found',
    500 => 'Internal Server Error',
    503 => 'Service Unavailable'
];

$error_title = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Unknown Error';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $error_code . ' - ' . $error_title; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
            text-align: center;
        }
        .error-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .error-code {
            font-size: 100px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 24px;
            margin-bottom: 15px;
        }
        .error-description {
            color: #6c757d;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .back-link:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo $error_title; ?></h1>
        <p class="error-description">
            <?php 
            switch($error_code) {
                case 404:
                    echo "The page you are looking for might have been removed or is temporarily unavailable.";
                    break;
                case 403:
                    echo "You do not have permission to access this page.";
                    break;
                case 500:
                    echo "An unexpected error occurred. Please try again later.";
                    break;
                default:
                    echo "Something went wrong. Please contact the system administrator.";
            }
            ?>
        </p>
        <a href="dashboard.php" class="back-link">Return to Dashboard</a>
    </div>
</body>
</html>