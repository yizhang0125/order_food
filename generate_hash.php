<?php
$password = "123";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password Hash for '123': " . $hash;
?> 