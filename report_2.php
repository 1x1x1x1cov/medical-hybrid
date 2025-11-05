<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Admin only
if (!is_admin()) {
    die("Access denied. This report is for administrators only.");
}

// Get filter parameters
$filter_department = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'department_name';
$order_direction = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';

if (!in_array($order_direction, ['ASC', 'DESC'])) {
    $order_direction = 'ASC';
}

// Build query
$where_sql = "1=1";
if ($filter_department > 0) {
    $where_sql .= " AND dept.department_id = $filter_department";
}

// Sorting
$order_by_sql = "dept.department_name ASC";
switch ($sort_by) {
    case 'doctor_count':
        $order_by_sql = "doctor_count $order_direction";
        break;
    case 'appointment_count':
        $order_by_sql = "appointment_count $order_direction";
        break;
    case 'avg_appointments':
        $order_by_sql = "avg_appointments $order_direction";
        break;
    case 'department_name':
    default:
        $order_by_sql = "dept.department_name $order_direction";
        break;
}

// Main query - joining 3 tables
$query = "SELECT 
    dept.department_id,
    dept.department_name,
    dept.location,
    dept.phone,
    COUNT(DISTINCT d.doctor_id) as doctor_count,
    COUNT(DISTINCT a.appointment_id) as appointment_count,
    ROUND(COUNT(DISTINCT a.appointment_id) / COUNT(DISTINCT d.doctor_id), 2) as avg_appointments,
    hd.first_name as head_first_name,
    hd.last_name as head_last_name
FROM department dept
LEFT JOIN doctor d ON dept.department_id = d.department_id
LEFT JOIN appointment a ON d.doctor_id = a.doctor_id
LEFT JOIN doctor hd ON dept.headDoctor_id = hd.doctor_id
WHERE $where_sql
GROUP BY dept.department_id
ORDER BY $order_by_sql";

$result = mysqli_query($conn, $query);

// Get all departments for filter
$departments_query = "SELECT * FROM department ORDER BY department_name";
$departments = mysqli_query($conn, $departments_query);

// Collect report data
$report_data = [];
$total_doctors = 0;
$total_appointments = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $dept_id = $row['department_id'];
    
    // Try to read JSON metrics if they exist
    $json_file = "data/json/department_metrics_" . $dept_id . ".json";
    $json_data = read_json_file($json_file);
    
    if ($json_data) {
        $row['equipment_count'] = $json_data['equipment_count'] ?? 0;
        $row['budget'] = $json_data['annual_budget'] ?? 0;
        $row['rating'] = $json_data['patient_satisfaction'] ?? 0;
    } else {
        $row['equipment_count'] = 0;
        $row['budget'] = 0;
        $row['rating'] = 0;
    }
    
    $total_doctors += $row['doctor_count'];
    $total_appointments += $row['appointment_count'];
    
    $report_data[] = $row;
}

$total_records = count($report_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 2: Department Performance - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <strong>🏥 Medical Hybrid System</strong>
            <span class="role-badge">admin</span>
        </div>
        <div class="navbar-right">
            <a href="dashboard.php">Dashboard</a>
            <a href="patients.php">Patients</a>
            <a href="reports.php">Reports</a>
            <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="report-header">
            <h1>📈 Report 2: Department Performance Dashboard</h1>
            <p style="margin: 10px 0;">Comprehensive analysis of department efficiency and resource utilization</p>
            <div>
                <span class="data-source-badge badge-structured">Structured: 3 MySQL Tables</span>
                <span class="data-source-badge badge-semi">Semi-structured: JSON Metrics</span>
                <span class="data-source-badge badge-unstructured">Unstructured: Performance Notes</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filters & Sorting</h2>
            <form method="GET" class="filters">
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
                
                <div class="filter-item">
                    <label>Sort By</label>
                    <select name="sort">
                        <option value="department_name" <?php echo ($sort_by == 'department_name') ? 'selected' : ''; ?>>Department Name</option>
                        <option value="doctor_count" <?php echo ($sort_by == 'doctor_count') ? 'selected' : ''; ?>>Number of Doctors</option>
                        <option value="appointment_count" <?php echo ($sort_by == 'appointment_count') ? 'selected' : ''; ?>>Total Appointments</option>
                        <option value="avg_appointments" <?php echo ($sort_by == 'avg_appointments') ? 'selected' : ''; ?>>Avg Appointments per Doctor</option>
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
                    <a href="report_2.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_records; ?></h3>
                <p>Total Departments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_doctors; ?></h3>
                <p>Total Doctors</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_appointments; ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_doctors > 0 ? round($total_appointments / $total_doctors, 1) : 0; ?></h3>
                <p>Avg Appointments/Doctor</p>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <h2>📥 Export Options</h2>
            <div class="action-buttons">
                <a href="export.php?report=2&format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Export to CSV</a>
                <a href="export.php?report=2&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">Export to PDF</a>
            </div>
        </div>

        <!-- Report Data -->
        <div class="card">
            <h2>📊 Department Performance Data</h2>
            
            <?php if ($total_records > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Head Doctor</th>
                                <th>Location</th>
                                <th>Contact</th>
                                <th>Doctors</th>
                                <th>Appointments</th>
                                <th>Avg per Doctor</th>
                                <th>Equipment (JSON)</th>
                                <th>Budget (JSON)</th>
                                <th>Rating (JSON)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['department_name']); ?></strong></td>
                                    <td>
                                        <?php if ($row['head_first_name']): ?>
                                            Dr. <?php echo htmlspecialchars($row['head_first_name'] . ' ' . $row['head_last_name']); ?>
                                        <?php else: ?>
                                            <small style="color: #999;">Not assigned</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td><span class="inline-badge" style="background: #d4edda; color: #155724;"><?php echo $row['doctor_count']; ?></span></td>
                                    <td><span class="inline-badge" style="background: #cce5ff; color: #004085;"><?php echo $row['appointment_count']; ?></span></td>
                                    <td><?php echo $row['avg_appointments']; ?></td>
                                    <td>
                                        <?php if ($row['equipment_count'] > 0): ?>
                                            <span class="inline-badge" style="background: #fff3cd; color: #856404;"><?php echo $row['equipment_count']; ?> items</span>
                                        <?php else: ?>
                                            <small style="color: #999;">No data</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['budget'] > 0): ?>
                                            $<?php echo number_format($row['budget']); ?>
                                        <?php else: ?>
                                            <small style="color: #999;">No data</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['rating'] > 0): ?>
                                            <?php echo $row['rating']; ?>/5 ⭐
                                        <?php else: ?>
                                            <small style="color: #999;">No data</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center" style="padding: 40px; color: #999;">No data found.</p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>
    </div>
</body>
</html>