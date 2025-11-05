<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Build query based on role
if ($is_admin_user) {
    // Admin sees ALL patients
    $query = "SELECT DISTINCT p.*, 
              d.first_name as doctor_first_name, 
              d.last_name as doctor_last_name,
              dept.department_name
              FROM patient p
              LEFT JOIN appointment a ON p.patient_id = a.patient_id
              LEFT JOIN doctor d ON a.doctor_id = d.doctor_id
              LEFT JOIN department dept ON d.department_id = dept.department_id
              ORDER BY p.last_name, p.first_name";
} else if ($is_doctor_user) {
    // Doctor sees only THEIR patients (via appointments)
    $doctor_id = $_SESSION['linked_id'];
    $query = "SELECT DISTINCT p.*, 
              d.first_name as doctor_first_name, 
              d.last_name as doctor_last_name,
              dept.department_name
              FROM patient p
              JOIN appointment a ON p.patient_id = a.patient_id
              JOIN doctor d ON a.doctor_id = d.doctor_id
              LEFT JOIN department dept ON d.department_id = dept.department_id
              WHERE a.doctor_id = $doctor_id
              ORDER BY p.last_name, p.first_name";
}

$result = mysqli_query($conn, $query);
$patients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $patients[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Medical Hybrid System</title>
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
            <?php if ($is_doctor_user): ?>
            <a href="create_appointment.php">New Appointment</a>
            <?php endif; ?>
            <?php if ($is_admin_user): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <?php if ($is_doctor_user || $is_admin_user): ?>
            <a href="create_lab_test.php">New Lab Test</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><?php echo $is_doctor_user ? 'My Patients' : 'All Patients'; ?></h1>
            <p>Total: <?php echo count($patients); ?> patients</p>
        </div>

        <?php if (count($patients) > 0): ?>
            <div class="patient-list">
                <?php foreach ($patients as $patient): ?>
                    <div class="patient-item">
                        <div class="patient-info">
                            <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                            <p>
                                DOB: <?php echo date('M d, Y', strtotime($patient['dob'])); ?> | 
                                Email: <?php echo htmlspecialchars($patient['email']); ?>
                                <?php if (isset($patient['doctor_first_name'])): ?>
                                    | Doctor: Dr. <?php echo htmlspecialchars($patient['doctor_first_name'] . ' ' . $patient['doctor_last_name']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <a href="patient_detail.php?id=<?php echo $patient['patient_id']; ?>" class="btn btn-primary btn-small">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p class="text-center">No patients found.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>