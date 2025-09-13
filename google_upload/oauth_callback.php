<?php
/**
 * oauth_callback.php
 * 
 * This file handles the OAuth 2.0 callback from Google.
 * It retrieves the access token and stores it in the session.
 * 
 * Required file path: /var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs/google_upload/oauth_callback.php
 */

session_start();

require_once __DIR__ . '/google-api-php-client/autoload.php'; // Update the path to the correct location of the Google API PHP Client

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->addScope(Google_Service_Drive::DRIVE);

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);
    $drive_service = new Google_Service_Drive($client);
    header('Location: /google_upload/upload.php');
} else {
    $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . '/google_upload/oauth.php';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}
?>