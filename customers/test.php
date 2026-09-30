<?php
$hash = '$2y$10$FFnDnhng9vgia322L52EWeusFwws352...'; // paste the FULL hash from phpMyAdmin, not truncated
$test_password = 'whatever_you_are_typing_at_login';

var_dump(password_verify($test_password, $hash));