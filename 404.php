<?php
// 404.php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 offset-md-3 text-center">
                <h1 class="display-4">404</h1>
                <h2>Page Not Found</h2>
                <p class="lead">The page you're looking for doesn't exist or you don't have permission to access it.</p>
                <a href="login.php" class="btn btn-primary">Return to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>