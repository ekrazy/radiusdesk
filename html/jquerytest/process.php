<?php

$errors = [];
$data = [];

if (empty($_POST['username'])) {
    $errors['username'] = 'username is required.';
}

if (empty($_POST['password'])) {
    $errors['password'] = 'password is required.';
}

if (!empty($errors)) {
    $data['success'] = false;
    $data['errors'] = $errors;
} else {
    $data['success'] = true;
    $data['message'] = 'Success!';
}

echo json_encode($data);
?>