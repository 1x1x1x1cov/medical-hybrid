<?php
// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin';
}

// Check if user is doctor
function is_doctor() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'doctor';
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit();
    }
}

// Read JSON file
function read_json_file($filepath) {
    $full_path = BASE_PATH . $filepath;
    if (file_exists($full_path)) {
        $json_content = file_get_contents($full_path);
        return json_decode($json_content, true);
    }
    return null;
}

// Read text file
function read_text_file($filepath) {
    $full_path = BASE_PATH . $filepath;
    if (file_exists($full_path)) {
        return file_get_contents($full_path);
    }
    return null;
}

// Format file size
function format_file_size($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Sanitize input
function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>