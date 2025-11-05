<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Simple API key authentication
$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
$expected_key = 'medical_system_2024_secret'; 

if ($api_key !== $expected_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get report type (default to 2)
$report = isset($_GET['report']) ? (int)$_GET['report'] : 2;

// Query for Report 2 (Department Performance)
$query = "SELECT 
    dept.department_name,
    COUNT(DISTINCT a.appointment_id) as total_appointments,
    COUNT(DISTINCT a.patient_id) as unique_patients,
    COUNT(DISTINCT d.doctor_id) as doctors_in_dept
FROM department dept
LEFT JOIN doctor d ON dept.department_id = d.department_id
LEFT JOIN appointment a ON d.doctor_id = a.doctor_id
GROUP BY dept.department_id
ORDER BY total_appointments DESC";

$result = mysqli_query($conn, $query);

// Generate CSV
$filename = "department_performance_" . date('Y-m-d') . ".csv";
$filepath = "../data/reports/" . $filename;

// Create reports directory if not exists
if (!file_exists("../data/reports")) {
    mkdir("../data/reports", 0777, true);
}

$output = fopen($filepath, 'w');

// Headers
fputcsv($output, [
    'Department',
    'Total Appointments',
    'Unique Patients',
    'Doctors in Department',
    'Avg Appointments per Doctor'
]);

// Data
while ($row = mysqli_fetch_assoc($result)) {
    $avg_appts = $row['doctors_in_dept'] > 0 ? 
        round($row['total_appointments'] / $row['doctors_in_dept'], 1) : 0;
    
    fputcsv($output, [
        $row['department_name'],
        $row['total_appointments'],
        $row['unique_patients'],
        $row['doctors_in_dept'],
        $avg_appts
    ]);
}

fclose($output);

// Return success with full file path
$full_path = realpath($filepath);

echo json_encode([
    'success' => true,
    'message' => 'Report generated successfully',
    'filename' => $filename,
    'filepath' => $full_path,
    'download_url' => 'http://localhost/medical_hybrid_system/data/reports/' . $filename,
    'generated_at' => date('Y-m-d H:i:s')
]);
?>