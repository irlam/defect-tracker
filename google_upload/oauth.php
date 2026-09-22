<?php
/**
 * oauth.php
 * 
 * This file handles the OAuth 2.0 authorization process for accessing the Google Drive API.
 * It initiates the authorization flow and redirects the user to Google's consent screen.
 * 
 * Required file path: /var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs/google_upload/oauth.php
 */

session_start();

require_once __DIR__ . '/google-api-php-client/autoload.php'; // Update the path to the correct location of the Google API PHP Client

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/credentials.json');
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/google_upload/oauth_callback.php');
$client->addScope(Google_Service_Drive::DRIVE);

if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
    $client->authenticate($_GET['code']);
    $_SESSION['access_token'] = $client->getAccessToken();
    header('Location: /google_upload/upload.php');
}
?>