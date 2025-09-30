
<?php

// Determine line break style
$linebreak = (php_sapi_name() === 'cli') ? "\r\n" : "<br>";

// The plaintext password to test
$passwd = 'password';

// The hash of the password to test
$previous_hash = '$2y$10$ZJQhez0yp2VMqJrQjXJAgOoBQKnFrE9uudIYKtuns/ZW187XnLiTK';

// Display the results of the test
echo $linebreak;
if (password_verify($passwd, $previous_hash)) {
    echo "The password and previously hashed value match!" . $linebreak . $linebreak;
} else {
    echo "The password and previously hashed value do NOT match." . $linebreak . $linebreak;
}
echo "Password tested: '$passwd'" . $linebreak;
echo "Previous hash tested: $previous_hash" . $linebreak;
echo $linebreak;
