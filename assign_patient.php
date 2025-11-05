<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Admin only
if (!is_admin()) {
    die("Access denied. This page is for administrators only.");
}

$success = '';
$error = '';

// Get all patients
$patients_query = "SELECT patient_id, first_name, last_name FROM patient ORDER BY last_name, first_name";
$patients = mysqli_query($conn, $patients_query);

// Get all doctors
$doctors_query = "SELECT d.doctor_id, d.first_name, d.last_name, dept.department_name 
                  FROM doctor d
                  LEFT JOIN department dept ON d.department_id = dept.department_id
                  ORDER BY d.last_name, d.first_name";
$doctors = mysqli_query($conn, $doctors_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $date = clean_input($_POST['date']);
    $time = clean_input($_POST['time']);
    $description = clean_input($_POST['description']) ?: 'Initial consultation';
    
    if (empty($patient_id) || empty($doctor_id) || empty($date) || empty($time)) {
        $error = "Please fill in all required fields.";
    } else {
        // Create first appointment (this assigns patient to doctor)
        $query = "INSERT INTO appointment (patient_id, doctor_id, date, time, description) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iisss", $patient_id, $doctor_id, $date, $time, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Patient successfully assigned to doctor! Initial appointment created.";
            $_POST = array();
        } else {
            $error = "Error creating assignment: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Patient to Doctor - Medical Hybrid System</title>
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
        <div class="card">
            <h1>👥 Assign Patient to Doctor</h1>
            <p>Create initial appointment linking a patient to a doctor. After this, the doctor can schedule future appointments.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Patient *</label>
                    <select name="patient_id" required>
                        <option value="">-- Select Patient --</option>
                        <?php 
                        mysqli_data_seek($patients, 0);
                        while ($patient = mysqli_fetch_assoc($patients)): 
                        ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assign to Doctor *</label>
                    <select name="doctor_id" required>
                        <option value="">-- Select Doctor --</option>
                        <?php 
                        mysqli_data_seek($doctors, 0);
                        while ($doctor = mysqli_fetch_assoc($doctors)): 
                        ?>
                            <option value="<?php echo $doctor['doctor_id']; ?>">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                <?php if ($doctor['department_name']): ?>
                                    (<?php echo htmlspecialchars($doctor['department_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Initial Appointment Date *</label>
                    <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Initial Appointment Time *</label>
                    <input type="time" name="time" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Initial consultation (default if left blank)"></textarea>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Assign Patient</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>📊 Current Patient-Doctor Assignments</h2>
            <?php
            $assignments_query = "SELECT 
                p.first_name as patient_first,
                p.last_name as patient_last,
                d.first_name as doctor_first,
                d.last_name as doctor_last,
                dept.department_name,
                COUNT(a.appointment_id) as appointment_count,
                MAX(a.date) as last_appointment
            FROM patient p
            JOIN appointment a ON p.patient_id = a.patient_id
            JOIN doctor d ON a.doctor_id = d.doctor_id
            LEFT JOIN department dept ON d.department_id = dept.department_id
            GROUP BY p.patient_id, d.doctor_id
            ORDER BY last_appointment DESC
            LIMIT 10";
            
            $assignments = mysqli_query($conn, $assignments_query);
            ?>
            
            <?php if (mysqli_num_rows($assignments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Appointments</th>
                            <th>Last Appointment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($assignments)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['patient_first'] . ' ' . $row['patient_last']); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($row['doctor_first'] . ' ' . $row['doctor_last']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                <td><?php echo $row['appointment_count']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['last_appointment'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No assignments yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>