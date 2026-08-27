<?php
require '../_base.php';

$_db->query('DELETE FROM token WHERE expire < NOW()');
$id = req('id');

$stm = $_db->prepare(
    'SELECT t.user_id FROM token t JOIN user u ON u.user_id = t.user_id WHERE t.id = ? AND t.type = "verification" AND t.expire >= NOW()'
);
$stm->execute([$id]);
$verification = $stm->fetch();

if (!$verification) {
    temp('info', 'This verification link is invalid or has expired.');
    redirect('/login.php');
}

$_db->prepare('UPDATE user SET email_verified = 1 WHERE user_id = ?')->execute([$verification->user_id]);
$_db->prepare('DELETE FROM token WHERE id = ?')->execute([$id]);

temp('info', 'Email verified successfully. You may now log in.');
redirect('/login.php');