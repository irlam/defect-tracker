
<?php
// change the name from admin etc to see the hashed password that you can insert into the sql under the users password.
$password = 'Subaru5554346';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
echo "Hashed Password: " . $hashedPassword;
?>