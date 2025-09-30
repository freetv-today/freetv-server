
<?php

// Determine line break style
$linebreak = (php_sapi_name() === 'cli') ? "\r\n" : "<br>";

// The password you want to hash
$newpass = 'password';

// Output the hashed password
echo $linebreak;
echo "Hashed value for password: '$newpass' (with random salt from PHP):" . $linebreak;
echo password_hash($newpass, PASSWORD_DEFAULT) . $linebreak;
echo $linebreak;
