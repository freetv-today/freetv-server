<?php
$hash = '$2y$12$yC4l6qMrscz3JZ3KxpeJjeJN5oxViSULdjqSQmbe0LlbwRFBVXGRS';
$pass = '99&85*Sh^G8Ax6';
echo password_verify($pass, $hash) ? "MATCH\n" : "NO_MATCH\n";
