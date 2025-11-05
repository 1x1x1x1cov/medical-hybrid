<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Only doctors can upload
if (!is_doctor()) {
    die("Access denied. Only doctors can upload files.");
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: patients.php');
    exit();
}

$patient_id = (int)$_POST['patient_id'];
$notes = clean_input($_POST['notes']);
$doctor_id = $_SESSION['linked_id'];

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
    die("Error uploading file.");
}

$file = $_FILES['file'];
$file_name = $file['name'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];

// Validate file size (max 10MB)
if ($file_size > 10 * 1024 * 1024) {
    die("File too large. Maximum 10MB.");
}

// Determine category based on file type
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$doc_extensions = ['pdf', 'txt', 'doc', 'docx'];

// Validate file type
if (!in_array($file_extension, array_merge($image_extensions, $doc_extensions))) {
    die("File type not allowed. Only images (JPG, PNG, GIF) and documents (PDF, TXT, DOC, DOCX) are accepted.");
}

// Set category
if (in_array($file_extension, $doc_extensions)) {
    $category = 'other'; // Documents go to 'other' category
} else {
    $category = clean_input($_POST['category']); // Images use selected category
}

// Generate unique filename
$unique_name = 'patient_' . $patient_id . '_' . time() . '.' . $file_extension;

// Upload path
$upload_dir = UPLOAD_PATH;
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$upload_path = $upload_dir . $unique_name;
$relative_path = 'data/uploads/' . $unique_name;

// Move uploaded file
if (move_uploaded_file($file_tmp, $upload_path)) {
    // Insert into database
    $query = "INSERT INTO file_storage 
              (patient_id, uploaded_by_doctor_id, file_name, file_category, file_path, file_size_bytes, notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iisssis", 
        $patient_id, 
        $doctor_id, 
        $unique_name, 
        $category, 
        $relative_path, 
        $file_size, 
        $notes
    );
    
    if (mysqli_stmt_execute($stmt)) {
        // Success - redirect back to patient detail
        header("Location: patient_detail.php?id=$patient_id&upload=success");
        exit();
    } else {
        die("Database error: " . mysqli_error($conn));
    }
} else {
    die("Failed to move uploaded file.");
}
?>