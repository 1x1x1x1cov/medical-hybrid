<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Get parameters
$report_number = isset($_GET['report']) ? (int)$_GET['report'] : 1;
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Get same filters as report
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_department = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$filter_json_type = isset($_GET['json_type']) ? clean_input($_GET['json_type']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'patient_name';
$order_direction = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

if (!in_array($order_direction, ['ASC', 'DESC'])) {
    $order_direction = 'ASC';
}

// Build same query as Report 1
$where_conditions = ["1=1"];

if ($filter_date_from) {
    $where_conditions[] = "a.date >= '$filter_date_from'";
}
if ($filter_date_to) {
    $where_conditions[] = "a.date <= '$filter_date_to'";
}
if ($filter_department > 0) {
    $where_conditions[] = "dept.department_id = $filter_department";
}

if ($is_doctor_user) {
    $doctor_id = $_SESSION['linked_id'];
    $where_conditions[] = "d.doctor_id = $doctor_id";
}

$where_sql = implode(" AND ", $where_conditions);

// Sorting
$order_by = "p.last_name, p.first_name";
switch ($sort_by) {
    case 'date':
        $order_by = "MAX(a.date) $order_direction";
        break;
    case 'appointment_count':
        $order_by = "COUNT(DISTINCT a.appointment_id) $order_direction";
        break;
    case 'department':
        $order_by = "dept.department_name $order_direction, p.last_name ASC";
        break;
    case 'files_count':
        $order_by = "p.last_name ASC";
        break;
    case 'patient_name':
    default:
        $order_by = "p.last_name $order_direction, p.first_name $order_direction";
        break;
}

// Query
$query = "SELECT DISTINCT 
    p.patient_id,
    p.first_name,
    p.last_name,
    p.dob,
    p.email,
    p.phone,
    d.first_name as doctor_first_name,
    d.last_name as doctor_last_name,
    dept.department_name,
    COUNT(DISTINCT a.appointment_id) as appointment_count,
    MAX(a.date) as last_appointment
FROM patient p
INNER JOIN appointment a ON p.patient_id = a.patient_id
INNER JOIN doctor d ON a.doctor_id = d.doctor_id
LEFT JOIN department dept ON d.department_id = dept.department_id
LEFT JOIN medical_record mr ON p.patient_id = mr.patient_id
WHERE $where_sql
GROUP BY p.patient_id, d.doctor_id, dept.department_id
ORDER BY $order_by";

$result = mysqli_query($conn, $query);

// Collect data
$report_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $patient_id = $row['patient_id'];
    
    // Get JSON lab results
    $json_query = "SELECT * FROM json_documents WHERE patient_id = $patient_id";
    if (!empty($filter_json_type)) {
        $json_query .= " AND doc_type = '$filter_json_type'";
    }
    $json_results = mysqli_query($conn, $json_query);
    $json_count = 0;
    $json_tests = [];
    while ($json_row = mysqli_fetch_assoc($json_results)) {
        $parsed_json = read_json_file($json_row['file_path']);
        if ($parsed_json) {
            $json_count++;
            $json_tests[] = $parsed_json['test_type'] ?? 'Lab Test';
        }
    }
    
    // Get files
    $files_query = "SELECT * FROM file_storage WHERE patient_id = $patient_id";
    $files_results = mysqli_query($conn, $files_query);
    $files_count = mysqli_num_rows($files_results);
    
    // Get file paths for PDF
    $files = [];
    mysqli_data_seek($files_results, 0);
    while ($file = mysqli_fetch_assoc($files_results)) {
        $files[] = $file;
    }
    
    $row['json_count'] = $json_count;
    $row['json_tests'] = implode(', ', $json_tests);
    $row['files_count'] = $files_count;
    $row['files'] = $files;
    
    $report_data[] = $row;
}

// Sort by files if needed
if ($sort_by == 'files_count') {
    usort($report_data, function($a, $b) use ($order_direction) {
        return ($order_direction == 'ASC') ? $a['files_count'] - $b['files_count'] : $b['files_count'] - $a['files_count'];
    });
}

// Export based on format
if ($format == 'csv') {
    export_csv($report_data);
} elseif ($format == 'pdf') {
    export_pdf($report_data);
}

// CSV Export Function
function export_csv($data) {
    $filename = "patient_medical_summary_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, [
        'Patient Name',
        'DOB',
        'Email',
        'Phone',
        'Doctor',
        'Department',
        'Appointments',
        'Last Visit',
        'Lab Tests (JSON)',
        'Files Count'
    ]);
    
    // Data rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['first_name'] . ' ' . $row['last_name'],
            date('M j, Y', strtotime($row['dob'])),
            $row['email'],
            $row['phone'],
            'Dr. ' . $row['doctor_first_name'] . ' ' . $row['doctor_last_name'],
            $row['department_name'],
            $row['appointment_count'],
            date('M j, Y', strtotime($row['last_appointment'])),
            $row['json_tests'] ?: 'None',
            $row['files_count']
        ]);
    }
    
    fclose($output);
    exit();
}

// PDF Export Function
function export_pdf($data) {
    require_once 'includes/fpdf/fpdf.php';
    
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape for wider table
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'Patient Medical Summary Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'Total Records: ' . count($data), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(40, 8, 'Patient Name', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'DOB', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Email', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Doctor', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Department', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Appts', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Last Visit', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Files', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    foreach ($data as $row) {
        $pdf->SetFillColor(248, 249, 250);
        
        $pdf->Cell(40, 7, substr($row['first_name'] . ' ' . $row['last_name'], 0, 20), 1, 0, 'L', $fill);
        $pdf->Cell(25, 7, date('m/d/Y', strtotime($row['dob'])), 1, 0, 'C', $fill);
        $pdf->Cell(45, 7, substr($row['email'], 0, 25), 1, 0, 'L', $fill);
        $pdf->Cell(40, 7, 'Dr. ' . substr($row['doctor_first_name'] . ' ' . $row['doctor_last_name'], 0, 18), 1, 0, 'L', $fill);
        $pdf->Cell(35, 7, substr($row['department_name'], 0, 18), 1, 0, 'L', $fill);
        $pdf->Cell(20, 7, $row['appointment_count'], 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, date('m/d/Y', strtotime($row['last_appointment'])), 1, 0, 'C', $fill);
        $pdf->Cell(20, 7, $row['files_count'], 1, 1, 'C', $fill);
        
        $fill = !$fill;
    }
    
    // Add detailed records pages
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Detailed Patient Medical Records', 0, 1, 'C');
    $pdf->Ln(5);
    
    foreach ($data as $patient) {
        $patient_id = $patient['patient_id'];
        
        // Patient header
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(102, 126, 234);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 8, $patient['first_name'] . ' ' . $patient['last_name'], 1, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
        
        // GET AND DISPLAY JSON LAB RESULTS
        $json_query = "SELECT * FROM json_documents WHERE patient_id = $patient_id";
        $json_results = mysqli_query($GLOBALS['conn'], $json_query);
        
        if (mysqli_num_rows($json_results) > 0) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Lab Test Results (JSON Data):', 0, 1);
            $pdf->SetFont('Arial', '', 8);
            
            while ($json_row = mysqli_fetch_assoc($json_results)) {
                $json_data = read_json_file($json_row['file_path']);
                if ($json_data) {
                    // Check if we need new page
                    if ($pdf->GetY() > 240) {
                        $pdf->AddPage();
                    }
                    
                    // Test type header
                    $pdf->SetFillColor(255, 243, 205);
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->Cell(0, 6, $json_data['test_type'] ?? 'Lab Test', 1, 1, 'L', true);
                    
                    $pdf->SetFont('Arial', '', 8);
                    
                    // Test date
                    if (isset($json_data['test_date'])) {
                        $pdf->Cell(60, 5, 'Test Date:', 1, 0, 'L');
                        $pdf->Cell(0, 5, $json_data['test_date'], 1, 1);
                    }
                    
                    // Results
                    if (isset($json_data['results']) && is_array($json_data['results'])) {
                        foreach ($json_data['results'] as $key => $value) {
                            $pdf->Cell(60, 5, ucwords(str_replace('_', ' ', $key)) . ':', 1, 0, 'L');
                            $pdf->Cell(0, 5, $value, 1, 1);
                        }
                    }
                    
                    // Status
                    if (isset($json_data['status'])) {
                        $pdf->SetFont('Arial', 'B', 8);
                        $pdf->Cell(60, 5, 'Status:', 1, 0, 'L');
                        $pdf->SetFillColor(212, 237, 218);
                        $pdf->Cell(0, 5, $json_data['status'], 1, 1, 'L', true);
                        $pdf->SetFont('Arial', '', 8);
                    }
                    
                    // Lab technician if exists
                    if (isset($json_data['lab_technician'])) {
                        $pdf->Cell(60, 5, 'Lab Technician:', 1, 0, 'L');
                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->Cell(0, 5, $json_data['lab_technician'], 1, 1);
                    }
                    
                    $pdf->Ln(3);
                }
            }
            
            $pdf->Ln(3);
        }
        
        // DISPLAY IMAGE FILES (9 per page in 3x3 grid)
        $image_files = array_filter($patient['files'], function($f) {
            $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
            return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && file_exists(BASE_PATH . $f['file_path']);
        });
        
        if (count($image_files) > 0) {
            $pdf->AddPage(); // Always start fresh page for images
            
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 8, 'Medical Images', 0, 1);
            $pdf->Ln(3);
            
            $image_count = 0;
            
            foreach ($image_files as $file) {
                // Calculate position (3 columns x 3 rows = 9 per page)
                $col = $image_count % 3;
                $row = floor($image_count / 3) % 3;
                
                // Start new page after 9 images
                if ($image_count > 0 && $image_count % 9 == 0) {
                    $pdf->AddPage();
                    $pdf->SetFont('Arial', 'B', 11);
                    $pdf->Cell(0, 8, 'Medical Images (continued)', 0, 1);
                    $pdf->Ln(3);
                    $row = 0;
                }
                
                $x_pos = 10 + ($col * 90);
                $y_pos = 20 + ($row * 85);
                
                try {
                    $file_path = BASE_PATH . $file['file_path'];
                    $pdf->Image($file_path, $x_pos, $y_pos, 80, 65);
                    
                    // Category label
                    $pdf->SetXY($x_pos, $y_pos + 66);
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell(80, 5, substr($file['file_category'], 0, 30), 0, 0, 'C');
                } catch (Exception $e) {
                    // Skip broken images
                }
                
                $image_count++;
            }
        }
        
        // DISPLAY TEXT FILES (each on new page)
        $text_files = array_filter($patient['files'], function($f) {
            $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
            return $ext == 'txt' && file_exists(BASE_PATH . $f['file_path']);
        });
        
        if (count($text_files) > 0) {
            foreach ($text_files as $file) {
                $pdf->AddPage(); // Each text file gets own page
                
                $file_path = BASE_PATH . $file['file_path'];
                $text_content = file_get_contents($file_path);
                
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 8, 'Text File: ' . $file['file_name'], 0, 1);
                $pdf->Ln(2);
                
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetFillColor(248, 249, 250);
                
                // Display up to 2000 characters (much longer)
                $pdf->MultiCell(270, 5, substr($text_content, 0, 2000), 1, 'L', true);
                
                if (strlen($text_content) > 2000) {
                    $pdf->Ln(2);
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->Cell(0, 5, '... (content truncated, full version available in system)', 0, 1);
                }
            }
        }
    }
    
    // Output
    $filename = "patient_medical_summary_" . date('Y-m-d_His') . ".pdf";
    $pdf->Output('D', $filename);
    exit();
}
?>