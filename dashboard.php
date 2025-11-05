<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Get user info
$username = $_SESSION['username'];
$role = $_SESSION['role_name'];
$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Get statistics
$total_patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM patient"))['count'];
$total_doctors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM doctor"))['count'];
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointment"))['count'];
$total_files = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM file_storage"))['count'];

// If doctor, get their patient count
if ($is_doctor_user) {
    $doctor_id = $_SESSION['linked_id'];
    $my_patients_query = "SELECT COUNT(DISTINCT p.patient_id) as count 
                          FROM patient p 
                          JOIN appointment a ON p.patient_id = a.patient_id 
                          WHERE a.doctor_id = $doctor_id";
    $my_patients_count = mysqli_fetch_assoc(mysqli_query($conn, $my_patients_query))['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <strong>🏥 Medical Hybrid System</strong>
            <span class="role-badge"><?php echo $role; ?></span>
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
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Role: <?php echo ucfirst($role); ?> | Last login: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <div class="stats-grid">
            <?php if ($is_doctor_user): ?>
                <div class="stat-card">
                    <h3><?php echo $my_patients_count; ?></h3>
                    <p>My Patients</p>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <h3><?php echo $total_patients; ?></h3>
                    <p>Total Patients</p>
                </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <h3><?php echo $total_doctors; ?></h3>
                <p>Total Appointments</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo $total_appointments; ?></h3>
                <p>Total Visits</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo $total_files; ?></h3>
                <p>Files Stored</p>
            </div>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="patients.php" class="btn btn-primary">View Patients</a>
                <?php if ($is_admin_user): ?>
                    <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-secondary">Manage Database</a>
                    <a href="assign_patient.php" class="btn btn-primary">Assign Patient to Doctor</a>
                    <a href="create_patient.php" class="btn btn-primary">Create New Patient</a>
                    <a href="create_doctor.php" class="btn btn-primary">Create New Doctor</a>
                    <a href="admin_manage_doctors.php" class="btn btn-primary">Manage Doctor Accounts</a>
                <?php endif; ?>
                <?php if ($is_doctor_user || $is_admin_user): ?>
                <a href="create_lab_test.php" class ="btn btn-primary">New Lab Test</a>
                <?php endif; ?>


            </div>
        </div>
    </div>
</body>
</html>