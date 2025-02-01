<?php
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defects Tracking Website</title>
    <!-- Include Bootstrap CSS for styling -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .bg {
            /* The background image */
            background-image: url('background.jpeg');
            
            /* Full height */
            height: 100%; 

            /* Center and scale the image nicely */
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }
        .overlay {
            /* Dark overlay to make text readable */
            background: rgba(0, 0, 0, 0.5);
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }
        .content {
            max-width: 600px;
        }
    </style>
</head>
<body>
    <div class="bg">
        <div class="overlay">
            <div class="content">
                <h1>Defect Guardian<br>The Defects Tracking Website</h1>
                <p>Manage and track defects efficiently.</p>
                <a href="login.php" class="btn btn-primary btn-lg">Login</a>
            </div>
        </div>
    </div>
</body>
</html>