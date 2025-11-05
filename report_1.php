<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Get filter parameters
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_department = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$filter_json_type = isset($_GET['json_type']) ? clean_input($_GET['json_type']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'patient_name';
$order_direction = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

// Validate order direction
if (!in_array($order_direction, ['ASC', 'DESC'])) {
    $order_direction = 'ASC';
}

// Build query with filters
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

// RBAC: Doctor only sees their patients
if ($is_doctor_user) {
    $doctor_id = $_SESSION['linked_id'];
    $where_conditions[] = "d.doctor_id = $doctor_id";
}

$where_sql = implode(" AND ", $where_conditions);

// Sorting logic
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

// Main query - joining 5 tables
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
    dept.department_id,
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

// Get all departments for filter dropdown
$departments_query = "SELECT * FROM department ORDER BY department_name";
$departments = mysqli_query($conn, $departments_query);

// Collect report data
$report_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $patient_id = $row['patient_id'];
    
    // Get JSON lab results for this patient
    $json_query = "SELECT * FROM json_documents WHERE patient_id = $patient_id";
    if (!empty($filter_json_type)) {
        $json_query .= " AND doc_type = '$filter_json_type'";
    }
    $json_results = mysqli_query($conn, $json_query);
    $json_count = 0;
    $json_list = [];
    while ($json_row = mysqli_fetch_assoc($json_results)) {
        $parsed_json = read_json_file($json_row['file_path']);
        if ($parsed_json) {
            $json_count++;
            $json_list[] = $parsed_json['test_type'] ?? 'Lab Test';
        }
    }
    
    // Get uploaded image files for this patient
    $files_query = "SELECT * FROM file_storage 
                    WHERE patient_id = $patient_id 
                    AND file_category IN ('xray', 'scan', 'prescription')";
    $files_results = mysqli_query($conn, $files_query);
    $files_count = mysqli_num_rows($files_results);
    
    // Get text/document files for this patient
    $text_files_query = "SELECT * FROM file_storage 
                         WHERE patient_id = $patient_id 
                         AND file_category = 'other'";
    $text_files_results = mysqli_query($conn, $text_files_query);
    $text_files_count = mysqli_num_rows($text_files_results);
    
    $row['json_count'] = $json_count;
    $row['json_tests'] = implode(', ', $json_list);
    $row['files_count'] = $files_count;
    $row['text_files_count'] = $text_files_count;
    
    $report_data[] = $row;
}

// Sort by files_count if requested (can't sort in SQL)
if ($sort_by == 'files_count') {
    usort($report_data, function($a, $b) use ($order_direction) {
        if ($order_direction == 'ASC') {
            return $a['files_count'] - $b['files_count'];
        } else {
            return $b['files_count'] - $a['files_count'];
        }
    });
}

$total_records = count($report_data);
$total_json_tests = array_sum(array_column($report_data, 'json_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 1: Patient Medical Summary - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <strong>🏥 Medical Hybrid System</strong>
            <span class="role-badge"><?php echo $_SESSION['role_name']; ?></span>
        </div>
        <div class="navbar-right">
            <a href="dashboard.php">Dashboard</a>
            <a href="patients.php">Patients</a>
            <?php if ($is_doctor_user || $is_admin_user): ?>
                <a href="create_appointment.php">New Appointment</a>
                <a href="create_lab_test.php">New Lab Test</a>
            <?php endif; ?>
            <a href="reports.php">Reports</a>
            <?php if ($is_admin_user): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="report-header">
            <h1>📋 Report 1: Comprehensive Patient Medical Summary</h1>
            <p style="margin: 10px 0;">Integrated data from multiple sources showing complete patient health overview</p>
            <div>
                <span class="data-source-badge badge-structured">Structured: 5 MySQL Tables</span>
                <span class="data-source-badge badge-semi">Semi-structured: JSON Lab Results</span>
                <span class="data-source-badge badge-unstructured">Unstructured: Medical Images & Text Files</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filters & Sorting</h2>
            <form method="GET" class="filters">
                <div class="filter-item">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="filter-item">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <?php if ($is_admin_user): ?>
                <div class="filter-item">
                    <label>Department</label>
                    <select name="department">
                        <option value="0">All Departments</option>
                        <?php 
                        mysqli_data_seek($departments, 0);
                        while ($dept = mysqli_fetch_assoc($departments)): 
                        ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo ($filter_department == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="filter-item">
                    <label>Lab Test Type (JSON)</label>
                    <select name="json_type">
                        <option value="">All Test Types</option>
                        <option value="complete_blood_count" <?php echo ($filter_json_type == 'complete_blood_count') ? 'selected' : ''; ?>>Complete Blood Count</option>
                        <option value="lipid_panel" <?php echo ($filter_json_type == 'lipid_panel') ? 'selected' : ''; ?>>Lipid Panel</option>
                        <option value="liver_function_test" <?php echo ($filter_json_type == 'liver_function_test') ? 'selected' : ''; ?>>Liver Function Test</option>
                        <option value="kidney_function_test" <?php echo ($filter_json_type == 'kidney_function_test') ? 'selected' : ''; ?>>Kidney Function Test</option>
                        <option value="x-ray_report" <?php echo ($filter_json_type == 'x-ray_report') ? 'selected' : ''; ?>>X-Ray Report</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Sort By</label>
                    <select name="sort">
                        <option value="patient_name" <?php echo ($sort_by == 'patient_name') ? 'selected' : ''; ?>>Patient Name</option>
                        <option value="date" <?php echo ($sort_by == 'date') ? 'selected' : ''; ?>>Last Appointment Date</option>
                        <option value="appointment_count" <?php echo ($sort_by == 'appointment_count') ? 'selected' : ''; ?>>Number of Appointments</option>
                        <option value="department" <?php echo ($sort_by == 'department') ? 'selected' : ''; ?>>Department</option>
                        <option value="files_count" <?php echo ($sort_by == 'files_count') ? 'selected' : ''; ?>>Number of Files</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Order</label>
                    <select name="order">
                        <option value="asc" <?php echo ($order_direction == 'ASC') ? 'selected' : ''; ?>>Ascending ↑</option>
                        <option value="desc" <?php echo ($order_direction == 'DESC') ? 'selected' : ''; ?>>Descending ↓</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
                
                <div class="filter-item">
                    <label>&nbsp;</label>
                    <a href="report_1.php" class="btn btn-secondary" style="text-align: center;">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_records; ?></h3>
                <p>Total Patients</p>
            </div>
            <div class="stat-card">
                <h3><?php echo array_sum(array_column($report_data, 'appointment_count')); ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_json_tests; ?></h3>
                <p>JSON Lab Tests</p>
            </div>
            <div class="stat-card">
                <h3><?php echo array_sum(array_column($report_data, 'files_count')); ?></h3>
                <p>Image Files</p>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <h2>📥 Export Options</h2>
            <div class="action-buttons">
                <a href="export.php?report=1&format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Export to CSV</a>
                <a href="export.php?report=1&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">Export to PDF</a>
            </div>
        </div>

        <!-- Report Data -->
        <div class="card">
            <h2>📊 Report Data (<?php echo $total_records; ?> records)</h2>
            
            <?php if ($total_records > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>DOB</th>
                                <th>Contact</th>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Appointments</th>
                                <th>Last Visit</th>
                                <th>Lab Results (JSON)</th>
                                <th>Image Files</th>
                                <th>Text Files</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($row['dob'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['email']); ?><br>
                                        <small><?php echo htmlspecialchars($row['phone']); ?></small>
                                    </td>
                                    <td>Dr. <?php echo htmlspecialchars($row['doctor_first_name'] . ' ' . $row['doctor_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                    <td><?php echo $row['appointment_count']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($row['last_appointment'])); ?></td>
                                    <td>
                                        <?php if ($row['json_count'] > 0): ?>
                                            <span class="inline-badge" style="background: #fff3cd; color: #856404;">
                                                <?php echo $row['json_count']; ?> test(s)
                                            </span>
                                            <br>
                                            <small><?php echo htmlspecialchars($row['json_tests']); ?></small>
                                        <?php else: ?>
                                            <small style="color: #999;">No data</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['files_count'] > 0): ?>
                                            <span class="inline-badge" style="background: #cce5ff; color: #004085;">
                                                <?php echo $row['files_count']; ?> file(s)
                                            </span>
                                        <?php else: ?>
                                            <small style="color: #999;">No files</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['text_files_count'] > 0): ?>
                                            <span class="inline-badge" style="background: #d4edda; color: #155724;">
                                                <?php echo $row['text_files_count']; ?> text file(s)
                                            </span>
                                        <?php else: ?>
                                            <small style="color: #999;">No text files</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="patient_detail.php?id=<?php echo $row['patient_id']; ?>" class="btn btn-primary btn-small">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center" style="padding: 40px; color: #999;">No data matches your filters. Try adjusting the filter criteria.</p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>
    </div>
</body>
</html>