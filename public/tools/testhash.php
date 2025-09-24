<?php

// The plaintext password to test
$passwd = 'password';

// The hash of the password to test
$hash = '$2y$10$ZJQhez0yp2VMqJrQjXJAgOoBQKnFrE9uudIYKtuns/ZW187XnLiTK';

// Display the results of the test
echo "\r\n";
if (password_verify($passwd, $hash)) {
    echo "The password and hash values match\r\n\r\n";
} else {
    echo "The password and hash values do NOT match\r\n\r\n";
}
echo "Password tested: $passwd\r\n";
echo "Hash tested: $hash\r\n";
echo "\r\n";
