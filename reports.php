<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Medical Hybrid System</title>
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
            <a href="reports.php">Reports</a>
            <?php if ($is_admin_user): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>📊 Reports</h1>
            <p>Generate comprehensive reports integrating structured, semi-structured, and unstructured data.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
            
            <!-- Report 1: Comprehensive Patient Medical Summary -->
            <div class="card" style="border-left: 4px solid #667eea;">
                <h2>📋 Report 1: Patient Medical Summary</h2>
                <p style="color: #666; margin: 15px 0;">Complete patient overview combining medical records, appointments, lab results (JSON), and medical images.</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>Data Sources:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Structured: Patient, Doctor, Appointment, Medical Record, Department (5 tables)</li>
                        <li>Semi-structured: JSON lab results</li>
                        <li>Unstructured: X-ray images, doctor notes</li>
                    </ul>
                </div>

                <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 15px 0; font-size: 13px;">
                    <strong>✨ Features:</strong> Filtering by date, department | Sorting by patient name | Export to CSV/PDF
                </div>

                <a href="report_1.php" class="btn btn-primary" style="width: 100%; text-align: center;">Generate Report</a>
            </div>

            <!-- Report 2: Department Performance Dashboard -->
            <div class="card" style="border-left: 4px solid #28a745;">
                <h2>📈 Report 2: Department Performance</h2>
                <p style="color: #666; margin: 15px 0;">Analyze department efficiency, doctor workload, and appointment statistics across all departments.</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>Data Sources:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Structured: Department, Doctor, Appointment (3 tables)</li>
                        <li>Semi-structured: Department metrics (JSON)</li>
                        <li>Unstructured: Performance notes</li>
                    </ul>
                </div>

                <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 15px 0; font-size: 13px;">
                    <strong>✨ Features:</strong> Filter by department | Sort by appointment count | Export statistics
                </div>

                <?php if ($is_admin_user): ?>
                    <a href="report_2.php" class="btn btn-success" style="width: 100%; text-align: center;">Generate Report</a>
                <?php else: ?>
                    <button class="btn btn-secondary" style="width: 100%; cursor: not-allowed;" disabled>Admin Only</button>
                <?php endif; ?>
            </div>

            <!-- Report 3: Patient Treatment Timeline -->
            <div class="card" style="border-left: 4px solid #764ba2;">
                <h2>⏱️ Report 3: Treatment Timeline</h2>
                <p style="color: #666; margin: 15px 0;">Chronological view of patient treatments, diagnoses, and attached medical files over time.</p>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>Data Sources:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Structured: Patient, Appointment, Treatment, Doctor (4 tables)</li>
                        <li>Semi-structured: Test results per visit (JSON)</li>
                        <li>Unstructured: Medical images per appointment</li>
                    </ul>
                </div>

                <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 15px 0; font-size: 13px;">
                    <strong>✨ Features:</strong> Select patient | Date range filter | Visual timeline | Export with images
                </div>

                <a href="report_3.php" class="btn btn-primary" style="width: 100%; text-align: center; background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);">Generate Report</a>
            </div>

        </div>
    </div>
</body>
</html>