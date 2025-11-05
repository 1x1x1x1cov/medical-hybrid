<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Get patient selection
$selected_patient = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get patients list (filtered by role)
if ($is_doctor_user) {
    $doctor_id = $_SESSION['linked_id'];
    $patients_query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name 
                       FROM patient p
                       JOIN appointment a ON p.patient_id = a.patient_id
                       WHERE a.doctor_id = $doctor_id
                       ORDER BY p.last_name, p.first_name";
} else {
    $patients_query = "SELECT patient_id, first_name, last_name 
                       FROM patient 
                       ORDER BY last_name, first_name";
}

$patients = mysqli_query($conn, $patients_query);

// If patient selected, get timeline data
$timeline_data = [];
$patient_info = null;

if ($selected_patient > 0) {
    // Check access
    if ($is_doctor_user) {
        $doctor_id = $_SESSION['linked_id'];
        $access_check = "SELECT COUNT(*) as count FROM appointment WHERE patient_id = $selected_patient AND doctor_id = $doctor_id";
        $has_access = mysqli_fetch_assoc(mysqli_query($conn, $access_check))['count'] > 0;
        
        if (!$has_access) {
            die("Access denied. This patient is not assigned to you.");
        }
    }
    
    // Get patient info
    $patient_query = "SELECT * FROM patient WHERE patient_id = $selected_patient";
    $patient_info = mysqli_fetch_assoc(mysqli_query($conn, $patient_query));
    
    // Build timeline query
    $where_conditions = ["a.patient_id = $selected_patient"];
    
    if ($filter_date_from) {
        $where_conditions[] = "a.date >= '$filter_date_from'";
    }
    if ($filter_date_to) {
        $where_conditions[] = "a.date <= '$filter_date_to'";
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Query joining 4 tables
    $timeline_query = "SELECT 
        a.appointment_id,
        a.date,
        a.time,
        a.description as appointment_description,
        d.first_name as doctor_first_name,
        d.last_name as doctor_last_name,
        dept.department_name,
        t.treatment_id,
        t.diagnosis,
        t.date as treatment_date
    FROM appointment a
    INNER JOIN doctor d ON a.doctor_id = d.doctor_id
    LEFT JOIN department dept ON d.department_id = dept.department_id
    LEFT JOIN treatment t ON a.appointment_id = t.appointment_id
    WHERE $where_sql
    ORDER BY a.date DESC, a.time DESC";
    
    $result = mysqli_query($conn, $timeline_query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $appointment_date = $row['date'];
        
        // Get files for this appointment (within ±3 days)
        $files_query = "SELECT * FROM file_storage 
                        WHERE patient_id = $selected_patient 
                        AND DATE(upload_date) BETWEEN 
                            DATE_SUB('$appointment_date', INTERVAL 3 DAY) AND 
                            DATE_ADD('$appointment_date', INTERVAL 3 DAY)
                        ORDER BY upload_date ASC";
        $files = [];
        $files_result = mysqli_query($conn, $files_query);
        while ($file = mysqli_fetch_assoc($files_result)) {
            $files[] = $file;
        }
        
        // Get JSON test results for this appointment (within ±3 days)
        $json_query = "SELECT * FROM json_documents WHERE patient_id = $selected_patient";
        $json_results = mysqli_query($conn, $json_query);
        $json_data = [];
        while ($json_row = mysqli_fetch_assoc($json_results)) {
            $parsed = read_json_file($json_row['file_path']);
            if ($parsed && isset($parsed['test_date'])) {
                $test_date = strtotime($parsed['test_date']);
                $appt_date = strtotime($appointment_date);
                $diff_days = abs(($test_date - $appt_date) / 86400);
                
                if ($diff_days <= 3) {
                    $json_data[] = $parsed;
                }
            }
        }
        
        $row['files'] = $files;
        $row['json_tests'] = $json_data;
        
        $timeline_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 3: Patient Treatment Timeline - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 50px;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #667eea;
        }
        .timeline-item {
            position: relative;
            padding-left: 80px;
            margin-bottom: 40px;
        }
        .timeline-marker {
            position: absolute;
            left: 38px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #667eea;
            border: 4px solid white;
            box-shadow: 0 0 0 4px #667eea;
        }
        .timeline-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .timeline-date {
            position: absolute;
            left: 0;
            top: 0;
            font-weight: bold;
            font-size: 12px;
            color: #667eea;
            width: 30px;
            text-align: right;
        }
    </style>
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
            <a href="create_appointment.php">New Appointment</a>
            <a href="reports.php">Reports</a>
            <?php if ($is_admin_user): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="report-header">
            <h1>⏱️ Report 3: Patient Treatment Timeline</h1>
            <p style="margin: 10px 0;">Chronological view of patient treatments, diagnoses, and medical files</p>
            <div>
                <span class="data-source-badge badge-structured">Structured: 4 MySQL Tables</span>
                <span class="data-source-badge badge-semi">Semi-structured: JSON Test Results</span>
                <span class="data-source-badge badge-unstructured">Unstructured: Medical Images</span>
            </div>
        </div>

        <!-- Patient Selection -->
        <div class="card">
            <h2>👤 Select Patient</h2>
            <form method="GET" class="filters">
                <div class="filter-item">
                    <label>Patient</label>
                    <select name="patient_id" required>
                        <option value="0">-- Select Patient --</option>
                        <?php while ($patient = mysqli_fetch_assoc($patients)): ?>
                            <option value="<?php echo $patient['patient_id']; ?>" 
                                <?php echo ($selected_patient == $patient['patient_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="filter-item">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="filter-item">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Load Timeline</button>
                </div>
                
                <?php if ($selected_patient > 0): ?>
                <div class="filter-item">
                    <label>&nbsp;</label>
                    <a href="report_3.php" class="btn btn-secondary" style="text-align: center;">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($patient_info): ?>
            <!-- Patient Info -->
            <div class="card">
                <h2>Patient Information</h2>
                <p>
                    <strong>Name:</strong> <?php echo htmlspecialchars($patient_info['first_name'] . ' ' . $patient_info['last_name']); ?> |
                    <strong>DOB:</strong> <?php echo date('F j, Y', strtotime($patient_info['dob'])); ?> |
                    <strong>Email:</strong> <?php echo htmlspecialchars($patient_info['email']); ?> |
                    <strong>Phone:</strong> <?php echo htmlspecialchars($patient_info['phone']); ?>
                </p>
            </div>

            <!-- Timeline Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo count($timeline_data); ?></h3>
                    <p>Total Visits</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_filter($timeline_data, fn($t) => !empty($t['diagnosis']))); ?></h3>
                    <p>Treatments</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo array_sum(array_map(fn($t) => count($t['files']), $timeline_data)); ?></h3>
                    <p>Medical Files</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo array_sum(array_map(fn($t) => count($t['json_tests']), $timeline_data)); ?></h3>
                    <p>Lab Tests</p>
                </div>
            </div>

            <!-- Export -->
            <div class="card">
                <h2>📥 Export Timeline</h2>
                <div class="action-buttons">
                    <a href="export.php?report=3&format=csv&patient_id=<?php echo $selected_patient; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Export to CSV</a>
                    <a href="export.php?report=3&format=pdf&patient_id=<?php echo $selected_patient; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">Export to PDF</a>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <h2>📅 Treatment Timeline</h2>
                
                <?php if (count($timeline_data) > 0): ?>
                    <div class="timeline">
                        <?php foreach ($timeline_data as $item): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h3><?php echo date('F j, Y', strtotime($item['date'])); ?> at <?php echo date('g:i A', strtotime($item['time'])); ?></h3>
                                    <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($item['doctor_first_name'] . ' ' . $item['doctor_last_name']); ?> 
                                       (<?php echo htmlspecialchars($item['department_name']); ?>)</p>
                                    
                                    <?php if ($item['appointment_description']): ?>
                                        <p><strong>Appointment:</strong> <?php echo htmlspecialchars($item['appointment_description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($item['diagnosis']): ?>
                                        <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                            <strong>Diagnosis/Treatment:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($item['diagnosis'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (count($item['json_tests']) > 0): ?>
                                        <div style="background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                            <strong>🧪 Lab Tests (JSON):</strong><br>
                                            <?php foreach ($item['json_tests'] as $test): ?>
                                                • <?php echo htmlspecialchars($test['test_type'] ?? 'Lab Test'); ?> 
                                                - Status: <?php echo htmlspecialchars($test['status'] ?? 'N/A'); ?><br>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (count($item['files']) > 0): ?>
                                        <div style="margin-top: 15px;">
                                            <strong>📎 Attached Files (<?php echo count($item['files']); ?>):</strong>
                                            <div class="file-grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); margin-top: 10px;">
                                                <?php foreach ($item['files'] as $file): ?>
                                                    <div class="file-item" style="padding: 10px;">
                                                        <?php 
                                                        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])):
                                                        ?>
                                                            <img src="<?php echo htmlspecialchars($file['file_path']); ?>" alt="Medical image" style="max-width: 100%; height: 100px; object-fit: cover; border-radius: 5px;">
                                                        <?php else: ?>
                                                            <div style="height: 100px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; border-radius: 5px;">📄</div>
                                                        <?php endif; ?>
                                                        <p style="font-size: 11px; margin-top: 5px;"><?php echo htmlspecialchars($file['file_category']); ?></p>
                                                        <p style="font-size: 10px; color: #666;">
                                                            <?php echo date('M j, Y', strtotime($file['upload_date'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center" style="padding: 40px; color: #999;">No appointments found for this patient in the selected date range.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p class="text-center" style="padding: 40px; color: #999;">Please select a patient to view their treatment timeline.</p>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>
    </div>
</body>
</html>