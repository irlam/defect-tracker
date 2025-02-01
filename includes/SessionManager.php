<?php
// /includes/SessionManager.php

class SessionManager {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function isAuthenticated() {
        return isset($_SESSION['username']) && !empty($_SESSION['username']);
    }

    public function login($username, $userId) {
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $userId;
        $_SESSION['last_activity'] = time();
    }

    public function logout() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public function getCurrentUser() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }

    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function checkSession() {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit();
        }
    }
}