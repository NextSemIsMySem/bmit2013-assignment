<?php
include '_base.php';

if (!is_post()) {
	http_response_code(405);
	exit('Logout requires a POST request.');
}
verify_csrf();

temp('info', 'Logged out.');
logout('/index.php');
