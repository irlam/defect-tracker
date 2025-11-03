<?php
// add_user.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-27 18:32:20
// Current User's Login: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

date_default_timezone_set('Europe/London');

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['username'])) {
	header('Location: login.php');
	exit();
}

define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

$pageTitle = 'Add New User';
$currentUser = $_SESSION['username'];
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $currentUser));
$userRoleLabel = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');
$success_message = '';
$error_message = '';
$selectedUserType = $_POST['user_type'] ?? '';
$selectedContractorId = $_POST['department'] ?? '';
$selectedContractorDetails = null;
$selectedContractorContact = '';
$selectedContractorLocation = '';
$contractors = [];
$contractorCount = 0;
$navbar = null;
$quickLinks = [];

function isValidUsername($username)
{
	return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

function isValidEmail($email)
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
}

function isValidPassword($password)
{
	return strlen($password) >= 8;
}

function isValidUserType($type)
{
	$validTypes = ['admin', 'manager', 'contractor', 'inspector', 'viewer'];
	return in_array($type, $validTypes, true);
}

function mapUserTypeToRole($type)
{
	$roleMap = [
		'admin' => 'admin',
		'manager' => 'manager',
		'contractor' => 'contractor',
		'viewer' => 'viewer',
		'inspector' => 'client',
	];

	return $roleMap[$type] ?? 'viewer';
}

function getRoleIdByName(PDO $db, $roleName)
{
	$stmt = $db->prepare(
		"
		SELECT id
		FROM roles
		WHERE name = ?
		LIMIT 1
		"
	);
	$stmt->execute([$roleName]);
	$result = $stmt->fetchColumn();

	if (!$result) {
		error_log('Role not found: ' . $roleName);
		throw new Exception("Invalid role configuration. Role '" . $roleName . "' not found.");
	}

	return (int) $result;
}

try {
	$database = new Database();
	$db = $database->getConnection();

	$contractorQuery = "
		SELECT
			id,
			company_name,
			contact_name,
			email,
			phone,
			trade,
			city,
			county
		FROM contractors
		WHERE status = 'active'
			AND deleted_at IS NULL
		ORDER BY trade ASC, company_name ASC
	";

	$contractorStmt = $db->query($contractorQuery);
	if ($contractorStmt instanceof PDOStatement) {
		$contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		$contractorCount = count($contractors);
	}

	if ($selectedContractorId !== '') {
		foreach ($contractors as $contractor) {
			if ((string) ($contractor['id'] ?? '') === (string) $selectedContractorId) {
				$selectedContractorDetails = $contractor;

				if (!empty($contractor['contact_name'])) {
					$selectedContractorContact = 'Contact: ' . $contractor['contact_name'];
				}

				$locationParts = array_filter([
					$contractor['city'] ?? '',
					$contractor['county'] ?? '',
				]);

				if (!empty($locationParts)) {
					$selectedContractorLocation = 'Location: ' . implode(', ', $locationParts);
				}

				break;
			}
		}
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$username = trim(filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING));
		$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
		$password = $_POST['password'] ?? '';
		$confirmPassword = $_POST['confirm_password'] ?? '';
		$userType = trim(filter_var($_POST['user_type'] ?? '', FILTER_SANITIZE_STRING));
		$firstName = trim(filter_var($_POST['first_name'] ?? '', FILTER_SANITIZE_STRING));
		$lastName = trim(filter_var($_POST['last_name'] ?? '', FILTER_SANITIZE_STRING));
		$contractorId = isset($_POST['department']) ? trim(filter_var($_POST['department'], FILTER_SANITIZE_STRING)) : '';
		$fullName = trim($firstName . ' ' . $lastName);

		$errors = [];

		if (!isValidUsername($username)) {
			$errors[] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores';
		}

		if (!isValidEmail($email)) {
			$errors[] = 'Please enter a valid email address';
		}

		if (!isValidPassword($password)) {
			$errors[] = 'Password must be at least 8 characters';
		}

		if ($password !== $confirmPassword) {
			$errors[] = 'Passwords do not match';
		}

		if (!isValidUserType($userType)) {
			$errors[] = 'Invalid user type selected';
		}

		if ($userType === 'contractor') {
			if ($contractorId === '') {
				$errors[] = 'Contractor selection is required for contractor users';
			} else {
				$stmt = $db->prepare(
					"
					SELECT COUNT(*)
					FROM contractors
					WHERE id = ?
						AND status = 'active'
						AND deleted_at IS NULL
					"
				);
				$stmt->execute([$contractorId]);
				if ($stmt->fetchColumn() == 0) {
					$errors[] = 'Invalid contractor selected';
				}
			}
		}

		$stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
		$stmt->execute([$username]);
		if ($stmt->fetchColumn() > 0) {
			$errors[] = 'Username already exists';
		}

		$stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
		$stmt->execute([$email]);
		if ($stmt->fetchColumn() > 0) {
			$errors[] = 'Email already exists';
		}

		if (empty($errors)) {
			$db->beginTransaction();

			try {
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
				$roleName = mapUserTypeToRole($userType);
				$roleId = getRoleIdByName($db, $roleName);

				$contractorDetails = null;
				if ($userType === 'contractor' && $contractorId !== '') {
					$stmt = $db->prepare(
						"
						SELECT company_name, trade
						FROM contractors
						WHERE id = ?
							AND status = 'active'
							AND deleted_at IS NULL
						"
					);
					$stmt->execute([$contractorId]);
					$contractorDetails = $stmt->fetch(PDO::FETCH_ASSOC);
				}

				if ($userType === 'contractor') {
					$stmt = $db->prepare(
						"
						INSERT INTO users (
							username,
							password,
							first_name,
							last_name,
							email,
							user_type,
							status,
							created_by,
							full_name,
							role,
							role_id,
							contractor_id,
							contractor_name,
							contractor_trade,
							theme_preference,
							is_active
						) VALUES (
							?, ?, ?, ?, ?, ?,
							'active',
							?,
							?,
							?,
							?,
							?,
							?,
							?,
							'light',
							1
						)
						"
					);

					$stmt->execute([
						$username,
						$passwordHash,
						$firstName,
						$lastName,
						$email,
						$userType,
						$_SESSION['username'],
						$fullName,
						$roleName,
						$roleId,
						$contractorId,
						$contractorDetails['company_name'] ?? null,
						$contractorDetails['trade'] ?? null,
					]);
				} else {
					$stmt = $db->prepare(
						"
						INSERT INTO users (
							username,
							password,
							first_name,
							last_name,
							email,
							user_type,
							status,
							created_by,
							full_name,
							role,
							role_id,
							theme_preference,
							is_active
						) VALUES (
							?, ?, ?, ?, ?, ?,
							'active',
							?,
							?,
							?,
							?,
							'light',
							1
						)
						"
					);

					$stmt->execute([
						$username,
						$passwordHash,
						$firstName,
						$lastName,
						$email,
						$userType,
						$_SESSION['username'],
						$fullName,
						$roleName,
						$roleId,
					]);
				}

				$newUserId = (int) $db->lastInsertId();

				$stmt = $db->prepare(
					"
					INSERT INTO user_roles (user_id, role_id, created_at, created_by)
					VALUES (?, ?, UTC_TIMESTAMP(), ?)
					"
				);
				$stmt->execute([$newUserId, $roleId, $_SESSION['user_id'] ?? null]);

				$stmt = $db->prepare(
					"
					INSERT INTO user_logs (
						user_id,
						action,
						action_by,
						action_at,
						ip_address,
						details
					) VALUES (
						?,
						'create_user',
						?,
						UTC_TIMESTAMP(),
						?,
						?
					)
					"
				);

				$logDetails = json_encode([
					'username' => $username,
					'email' => $email,
					'user_type' => $userType,
					'role' => $roleName,
					'role_id' => $roleId,
					'contractor_id' => $userType === 'contractor' ? $contractorId : null,
					'contractor_name' => $contractorDetails['company_name'] ?? null,
					'created_by' => $_SESSION['username'],
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

				if ($logDetails === false) {
					$logDetails = '{}';
				}

				$stmt->execute([
					$newUserId,
					$_SESSION['user_id'] ?? null,
					$_SERVER['REMOTE_ADDR'] ?? 'unknown',
					$logDetails,
				]);

				$db->commit();
				$success_message = 'User successfully created';

				unset($password, $confirmPassword, $passwordHash);

				header('Location: user_management.php?success=user_created');
				exit();
			} catch (Exception $transactionError) {
				$db->rollBack();
				throw new Exception('Failed to create user: ' . $transactionError->getMessage());
			}
		} else {
			$error_message = implode('<br>', $errors);
		}
	}
} catch (Exception $e) {
	$error_message = $e->getMessage();
	error_log('Error in add_user.php: ' . $e->getMessage());
}

if (isset($db) && $db instanceof PDO) {
	try {
		$navbar = new Navbar($db, (int) ($_SESSION['user_id'] ?? 0), $_SESSION['username'] ?? '');
	} catch (Throwable $navbarError) {
		error_log('Navbar initialisation error on add_user.php: ' . $navbarError->getMessage());
		$navbar = null;
	}
}

$quickLinks = [
	['href' => 'user_management.php', 'icon' => 'bx-group', 'label' => 'User Directory'],
	['href' => 'role_management.php', 'icon' => 'bx-shield-quarter', 'label' => 'Roles & Permissions'],
	['href' => 'contractors.php', 'icon' => 'bx-buildings', 'label' => 'Contractor Registry'],
	['href' => 'reports.php', 'icon' => 'bx-line-chart', 'label' => 'Activity Reports'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Add New User - Defect Tracker System">
	<meta name="author" content="<?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?>">
	<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
	<link href="/css/app.css" rel="stylesheet">
	<style>
		body {
			background: radial-gradient(circle at top, rgba(30, 64, 175, 0.25), rgba(15, 23, 42, 0.95) 45%, rgba(2, 6, 23, 1) 100%);
			color: rgba(226, 232, 240, 0.92);
			min-height: 100vh;
		}

		.user-admin-page {
			color: rgba(226, 232, 240, 0.9);
		}

		.user-admin-header {
			background: linear-gradient(135deg, rgba(17, 24, 39, 0.9), rgba(30, 41, 59, 0.92));
			border-radius: var(--bs-border-radius-xl);
			border: 1px solid rgba(148, 163, 184, 0.22);
			padding: 2.25rem;
			box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			gap: 1.5rem;
		}

		.user-admin-header h1 {
			color: rgba(248, 250, 252, 0.96);
		}

		.user-admin-header p {
			color: rgba(148, 163, 184, 0.78);
		}

		.user-admin-meta {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			gap: 0.5rem;
			font-size: 0.9rem;
			color: rgba(148, 163, 184, 0.82);
		}

		.user-admin-meta i {
			color: rgba(96, 165, 250, 0.8);
		}

		.user-admin-links {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 0.85rem;
		}

		.user-admin-link {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 1rem 1.2rem;
			border-radius: var(--bs-border-radius-lg);
			border: 1px solid rgba(148, 163, 184, 0.18);
			background: rgba(15, 23, 42, 0.92);
			color: rgba(226, 232, 240, 0.92);
			text-decoration: none;
			transition: transform 0.2s ease, border-color 0.2s ease;
			font-weight: 500;
		}

		.user-admin-link:hover {
			transform: translateY(-3px);
			border-color: rgba(96, 165, 250, 0.5);
			color: rgba(226, 232, 240, 1);
		}

		.user-admin-grid {
			display: grid;
			grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
			gap: 1.5rem;
		}

		.user-entry-card,
		.user-help-card {
			background: rgba(15, 23, 42, 0.92);
			border: 1px solid rgba(148, 163, 184, 0.18);
			border-radius: var(--bs-border-radius-xl);
			box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
			padding: 2rem;
		}

		.user-entry-card__header h2 {
			color: rgba(226, 232, 240, 0.95);
		}

		.user-entry-card__header p {
			color: rgba(148, 163, 184, 0.78);
		}

		.form-label {
			font-weight: 500;
			color: rgba(226, 232, 240, 0.9);
		}

		.form-control,
		.form-select {
			background: rgba(30, 41, 59, 0.88);
			border: 1px solid rgba(148, 163, 184, 0.25);
			color: rgba(226, 232, 240, 0.92);
			padding: 0.7rem 0.9rem;
			border-radius: 0.65rem;
		}

		.form-control:focus,
		.form-select:focus {
			border-color: rgba(96, 165, 250, 0.75);
			box-shadow: 0 0 0 0.15rem rgba(59, 130, 246, 0.25);
			background: rgba(15, 23, 42, 0.92);
			color: rgba(226, 232, 240, 0.95);
		}

		.form-text,
		.text-muted {
			color: rgba(148, 163, 184, 0.78) !important;
		}

		.password-wrapper {
			position: relative;
		}

		.password-toggle {
			position: absolute;
			right: 12px;
			top: 50%;
			transform: translateY(-50%);
			cursor: pointer;
			color: rgba(148, 163, 184, 0.82);
			font-size: 1.2rem;
		}

		.validation-checks {
			margin-top: 0.5rem;
		}

		.validation-check {
			display: flex;
			align-items: center;
			font-size: 0.85rem;
			color: rgba(148, 163, 184, 0.75);
			gap: 0.35rem;
		}

		.validation-check.valid {
			color: rgba(16, 185, 129, 0.9);
		}

		.validation-check.invalid {
			color: rgba(248, 113, 113, 0.9);
		}

		.user-role-hints {
			margin-top: 0.35rem;
			padding-left: 1.1rem;
		}

		.user-role-hints li {
			margin-bottom: 0.2rem;
		}

		.user-contractor-details {
			margin-top: 0.75rem;
			padding: 0.75rem 1rem;
			border-left: 3px solid rgba(59, 130, 246, 0.65);
			background: rgba(30, 41, 59, 0.85);
			border-radius: 0.65rem;
			display: flex;
			gap: 0.75rem;
			align-items: flex-start;
			color: rgba(226, 232, 240, 0.88);
		}

		.user-form-divider {
			border: none;
			height: 1px;
			background: linear-gradient(90deg, rgba(59, 130, 246, 0.35), rgba(59, 130, 246, 0));
			margin: 1.75rem 0;
		}

		.user-help-card h3 {
			color: rgba(226, 232, 240, 0.95);
		}

		.user-help-intro {
			color: rgba(148, 163, 184, 0.78);
		}

		.user-help-list {
			list-style: none;
			padding-left: 0;
			margin-bottom: 1.5rem;
		}

		.user-help-list li {
			display: flex;
			align-items: flex-start;
			gap: 0.6rem;
			margin-bottom: 0.75rem;
			color: rgba(203, 213, 225, 0.85);
		}

		.user-help-list i {
			color: rgba(96, 165, 250, 0.9);
			font-size: 1.25rem;
			margin-top: 0.1rem;
		}

		.user-help-links {
			display: grid;
			gap: 0.6rem;
		}

		.user-help-link {
			display: inline-flex;
			justify-content: space-between;
			align-items: center;
			padding: 0.75rem 1rem;
			border-radius: var(--bs-border-radius-lg);
			border: 1px solid rgba(148, 163, 184, 0.2);
			color: rgba(226, 232, 240, 0.9);
			text-decoration: none;
			transition: transform 0.2s ease, border-color 0.2s ease;
			background: rgba(15, 23, 42, 0.85);
		}

		.user-help-link:hover {
			transform: translateY(-2px);
			border-color: rgba(59, 130, 246, 0.6);
			color: rgba(226, 232, 240, 1);
		}

		.btn {
			border-radius: 0.65rem;
			font-weight: 500;
			padding: 0.65rem 1.4rem;
		}

		.btn-primary {
			background: linear-gradient(135deg, #2563eb, #4f46e5);
			border: none;
		}

		.btn-primary:hover {
			background: linear-gradient(135deg, #1d4ed8, #4338ca);
		}

		.btn-outline-light {
			border-color: rgba(148, 163, 184, 0.45);
			color: rgba(226, 232, 240, 0.9);
		}

		.btn-outline-light:hover {
			background: rgba(148, 163, 184, 0.15);
			color: rgba(226, 232, 240, 1);
		}

		.user-admin-alert {
			border: 1px solid rgba(34, 197, 94, 0.35);
			background: rgba(16, 185, 129, 0.12);
			color: rgba(190, 242, 100, 0.95);
		}

		.user-admin-alert.alert-danger {
			border-color: rgba(248, 113, 113, 0.4);
			background: rgba(248, 113, 113, 0.12);
			color: rgba(254, 202, 202, 0.95);
		}

		.user-admin-alert .btn-close {
			filter: invert(1);
		}

		@media (max-width: 1199px) {
			.user-admin-grid {
				grid-template-columns: 1fr;
			}

			.user-help-card {
				order: -1;
			}
		}

		@media (max-width: 768px) {
			.user-admin-header {
				padding: 1.6rem;
			}
		}
	</style>
</head>
<body class="tool-body" data-bs-theme="dark">
	<?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
	<div class="app-content-offset"></div>

	<main class="tool-page container-xl py-4 user-admin-page">
		<header class="user-admin-header mb-4">
			<div>
				<h1 class="h3 mb-2"><i class='bx bx-user-plus me-2'></i>Add New User</h1>
				<p class="mb-0">Onboard team members and contractors with consistent credentials and role-based access controls.</p>
			</div>
			<div class="user-admin-meta">
				<span><i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
				<span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($userRoleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
				<span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
				<span><i class='bx bx-buildings me-1'></i><?php echo htmlspecialchars((string) $contractorCount, ENT_QUOTES, 'UTF-8'); ?> active contractors</span>
			</div>
		</header>

		<?php if (!empty($success_message)): ?>
			<div class="alert alert-success alert-dismissible fade show user-admin-alert" role="alert">
				<i class="bx bx-check-circle me-2"></i><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?php if (!empty($error_message)): ?>
			<div class="alert alert-danger alert-dismissible fade show user-admin-alert" role="alert">
				<i class="bx bx-error-circle me-2"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?php if (!empty($quickLinks)): ?>
			<section class="user-admin-links mb-4">
				<?php foreach ($quickLinks as $link): ?>
					<a class="user-admin-link" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>">
						<span><i class='bx <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?> me-2'></i><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
						<i class='bx bx-chevron-right'></i>
					</a>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<section class="user-admin-grid">
			<article class="user-entry-card">
				<div class="user-entry-card__header mb-4">
					<h2 class="h5 mb-1">Account Details</h2>
					<p class="mb-0">Provide the credentials and assign permissions before inviting the user.</p>
				</div>
				<form id="addUserForm" method="post" class="needs-validation" novalidate>
					<div class="row g-3">
						<div class="col-md-6">
							<label for="username" class="form-label">Username <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="username" name="username" pattern="^[a-zA-Z0-9_]{3,50}$" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
							<div class="form-text">3-50 characters. Letters, numbers, and underscores only.</div>
						</div>
						<div class="col-md-6">
							<label for="email" class="form-label">Email <span class="text-danger">*</span></label>
							<input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
							<div class="form-text">Used for notifications and password recovery.</div>
						</div>
					</div>

					<div class="row g-3 mt-0">
						<div class="col-md-6">
							<label for="first_name" class="form-label">First Name</label>
							<input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
							<div class="form-text">Optional but recommended for reports and assignments.</div>
						</div>
						<div class="col-md-6">
							<label for="last_name" class="form-label">Last Name</label>
							<input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
							<div class="form-text">Optional but recommended for reports and assignments.</div>
						</div>
					</div>

					<div class="row g-3 mt-0">
						<div class="col-md-6">
							<label for="password" class="form-label">Password <span class="text-danger">*</span></label>
							<div class="password-wrapper">
								<input type="password" class="form-control" id="password" name="password" required>
								<i class="bx bx-hide password-toggle"></i>
							</div>
							<div class="validation-checks">
								<div class="validation-check" data-requirement="length">
									<i class="bx bx-x"></i>At least 8 characters
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
							<div class="password-wrapper">
								<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
								<i class="bx bx-hide password-toggle"></i>
							</div>
						</div>
					</div>

					<div class="row g-3 mt-0">
						<div class="col-md-6">
							<label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
							<select class="form-select" id="user_type" name="user_type" required>
								<option value="" disabled<?php echo $selectedUserType === '' ? ' selected' : ''; ?>>Select user type</option>
								<option value="admin"<?php echo $selectedUserType === 'admin' ? ' selected' : ''; ?>>Admin &mdash; Full system access</option>
								<option value="manager"<?php echo $selectedUserType === 'manager' ? ' selected' : ''; ?>>Manager &mdash; Project and defect management</option>
								<option value="contractor"<?php echo $selectedUserType === 'contractor' ? ' selected' : ''; ?>>Contractor &mdash; Assigned items only</option>
								<option value="inspector"<?php echo $selectedUserType === 'inspector' ? ' selected' : ''; ?>>Inspector &mdash; Inspection and reporting</option>
								<option value="viewer"<?php echo $selectedUserType === 'viewer' ? ' selected' : ''; ?>>Viewer &mdash; Read-only access</option>
							</select>
							<ul class="user-role-hints text-muted small mb-0">
								<li>Admin can configure settings and manage all records.</li>
								<li>Managers oversee project data and defect lifecycles.</li>
								<li>Contractors must be linked to an active contractor record.</li>
							</ul>
						</div>
						<div class="col-md-6">
							<label for="department" class="form-label">Select Contractor <span class="text-danger contractor-required<?php echo $selectedUserType === 'contractor' ? '' : ' d-none'; ?>">*</span></label>
							<small class="form-text d-block mb-2">Required when creating contractor accounts.</small>
							<select class="form-select" id="department" name="department" <?php echo $selectedUserType === 'contractor' ? 'required' : 'disabled'; ?>>
								<option value="">Select Contractor</option>
								<?php
								$tradeGroups = [];
								foreach ($contractors as $contractor) {
									$tradeGroups[$contractor['trade']][] = $contractor;
								}
								ksort($tradeGroups);
								foreach ($tradeGroups as $trade => $tradeContractors):
								?>
									<optgroup label="<?php echo htmlspecialchars($trade, ENT_QUOTES, 'UTF-8'); ?>">
										<?php foreach ($tradeContractors as $contractor): ?>
											<?php
												$location = trim(($contractor['city'] ?? '') . (($contractor['city'] ?? '') && ($contractor['county'] ?? '') ? ', ' : '') . ($contractor['county'] ?? ''));
											?>
											<option value="<?php echo htmlspecialchars($contractor['id'], ENT_QUOTES, 'UTF-8'); ?>" data-type="contractor" data-trade="<?php echo htmlspecialchars($contractor['trade'], ENT_QUOTES, 'UTF-8'); ?>" data-contact="<?php echo htmlspecialchars($contractor['contact_name'], ENT_QUOTES, 'UTF-8'); ?>" data-location="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ((string) ($contractor['id']) === (string) $selectedContractorId) ? ' selected' : ''; ?>>
												<?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo $location ? ' (' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . ')' : ''; ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<div id="contractorDetails" class="user-contractor-details<?php echo ($selectedUserType === 'contractor' && $selectedContractorId) ? '' : ' d-none'; ?>">
								<i class='bx bx-id-card'></i>
								<div>
									<div id="contactName"><?php echo htmlspecialchars($selectedContractorContact, ENT_QUOTES, 'UTF-8'); ?></div>
									<div id="location"><?php echo htmlspecialchars($selectedContractorLocation, ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
							</div>
						</div>
					</div>

					<hr class="user-form-divider">

					<div class="d-flex justify-content-end gap-2">
						<a href="user_management.php" class="btn btn-outline-light"><i class='bx bx-arrow-back me-1'></i>Cancel</a>
						<button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
							<i class='bx bx-user-plus'></i>Create User
						</button>
					</div>
				</form>
			</article>
			<aside class="user-help-card">
				<h3 class="h6 mb-3">Onboarding Checklist</h3>
				<p class="user-help-intro small">Follow these steps to onboard a new user smoothly.</p>
				<ul class="user-help-list">
					<li>
						<i class='bx bx-user-check'></i>
						<span>Confirm the user has completed compliance training and accepted system policies.</span>
					</li>
					<li>
						<i class='bx bx-lock-alt'></i>
						<span>Use strong passwords and enforce reset after first login for security best practices.</span>
					</li>
					<li>
						<i class='bx bx-sitemap'></i>
						<span>Select the correct role to ensure they see the right projects and tasks.</span>
					</li>
					<li>
						<i class='bx bx-building'></i>
						<span>For contractors, always link their profile to the matching company record.</span>
					</li>
				</ul>

				<div class="user-help-links">
					<a class="user-help-link" href="help_pages/user_roles_guide.html" target="_blank" rel="noopener">
						User roles guide <i class='bx bx-link-external'></i>
					</a>
					<a class="user-help-link" href="reports.php" rel="noopener">
						Review access reports <i class='bx bx-chevron-right'></i>
					</a>
					<a class="user-help-link" href="notifications.php" rel="noopener">
						Configure alerts <i class='bx bx-chevrons-right'></i>
					</a>
				</div>
			</aside>
		</section>
	</main>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		(function () {
			'use strict';
			const form = document.getElementById('addUserForm');
			if (form) {
				form.addEventListener('submit', function (event) {
					if (!form.checkValidity()) {
						event.preventDefault();
						event.stopPropagation();
					}
					form.classList.add('was-validated');
				}, false);
			}

			const passwordFields = document.querySelectorAll('.password-wrapper');
			passwordFields.forEach((wrapper) => {
				const input = wrapper.querySelector('input');
				const toggle = wrapper.querySelector('.password-toggle');
				if (input && toggle) {
					toggle.addEventListener('click', () => {
						const isPassword = input.getAttribute('type') === 'password';
						input.setAttribute('type', isPassword ? 'text' : 'password');
						toggle.classList.toggle('bx-hide', !isPassword);
						toggle.classList.toggle('bx-show', isPassword);
					});
				}
			});

			const contractorDetails = document.getElementById('contractorDetails');
			const contractorContactEl = contractorDetails ? contractorDetails.querySelector('#contactName') : null;
			const contractorLocationEl = contractorDetails ? contractorDetails.querySelector('#location') : null;
			const contractorSelect = document.getElementById('department');
			const userTypeSelect = document.getElementById('user_type');
			const contractorRequired = document.querySelector('.contractor-required');

			function toggleContractorFields() {
				const isContractor = userTypeSelect && userTypeSelect.value === 'contractor';
				if (contractorSelect) {
					contractorSelect.disabled = !isContractor;
					contractorSelect.required = isContractor;
					if (!isContractor) {
						contractorSelect.value = '';
					}
				}

				if (contractorRequired) {
					contractorRequired.classList.toggle('d-none', !isContractor);
				}

				if (!isContractor && contractorDetails) {
					contractorDetails.classList.add('d-none');
				} else if (isContractor && contractorDetails && contractorSelect && contractorSelect.value) {
					contractorDetails.classList.remove('d-none');
				}
			}

			if (userTypeSelect) {
				userTypeSelect.addEventListener('change', toggleContractorFields);
				toggleContractorFields();
			}

			if (contractorSelect) {
				contractorSelect.addEventListener('change', (event) => {
					const selectedOption = event.target.selectedOptions[0];
					if (!selectedOption || !contractorDetails) {
						return;
					}
					const contactName = selectedOption.getAttribute('data-contact') || '';
					const location = selectedOption.getAttribute('data-location') || '';

					if (contractorContactEl) {
						contractorContactEl.textContent = contactName ? 'Contact: ' + contactName : '';
					}

					if (contractorLocationEl) {
						contractorLocationEl.textContent = location ? 'Location: ' + location : '';
					}

					contractorDetails.classList.toggle('d-none', !(contactName || location));
				});
			}

			const passwordInput = document.getElementById('password');
			const validationCheck = document.querySelector('.validation-check[data-requirement="length"]');
			if (passwordInput && validationCheck) {
				passwordInput.addEventListener('input', () => {
					const isValid = passwordInput.value.length >= 8;
					validationCheck.classList.toggle('valid', isValid);
					validationCheck.classList.toggle('invalid', !isValid && passwordInput.value.length > 0);
					validationCheck.querySelector('i').className = isValid ? 'bx bx-check' : 'bx bx-x';
				});
			}
		})();
	</script>
</body>
</html>
