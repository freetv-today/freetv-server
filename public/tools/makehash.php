<?php

// The password you want to hash
$newpass = 'password';

// Output the hashed password
echo "\r\n";
echo "Hashed value for password: $newpass\r\n<br>";
echo password_hash($newpass, PASSWORD_DEFAULT) . PHP_EOL;
echo "\r\n<br>";
