<?php
/**
 * upload.php
 * 
 * This file handles the file upload process to Google Drive.
 * It uses the access token to authenticate and upload files to the user's Google Drive.
 * 
 * Required file path: /var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs/google_upload/upload.php
 */

session_start();

require_once __DIR__ . '/google-api-php-client/autoload.php'; // Update the path to the correct location of the Google API PHP Client

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google_Service_Drive::DRIVE);

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);
    $drive_service = new Google_Service_Drive($client);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        $file = new Google_Service_Drive_DriveFile();
        $file->setName($_FILES['file']['name']);
        $result = $drive_service->files->create($file, array(
            'data' => file_get_contents($_FILES['file']['tmp_name']),
            'mimeType' => $_FILES['file']['type'],
            'uploadType' => 'multipart'
        ));

        echo "File uploaded successfully. File ID: " . $result->id;
    }
} else {
    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/google_upload/oauth.php';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload to Google Drive</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Upload a File to Google Drive</h1>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>