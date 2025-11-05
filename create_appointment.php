<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Only doctors can create appointments
if (!is_doctor()) {
    die("Access denied. Only doctors can create appointments.");
}

$doctor_id = $_SESSION['linked_id'];
$success = '';
$error = '';

// Get doctor's patients
$patients_query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name 
                   FROM patient p
                   JOIN appointment a ON p.patient_id = a.patient_id
                   WHERE a.doctor_id = $doctor_id
                   ORDER BY p.last_name, p.first_name";
$patients = mysqli_query($conn, $patients_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $date = clean_input($_POST['date']);
    $time = clean_input($_POST['time']);
    $description = clean_input($_POST['description']);
    
    // Validate
    if (empty($patient_id) || empty($date) || empty($time)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert appointment
        $query = "INSERT INTO appointment (patient_id, doctor_id, date, time, description) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iisss", $patient_id, $doctor_id, $date, $time, $description);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Appointment created successfully!";
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Error creating appointment: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Appointment - Medical Hybrid System</title>
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
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>📅 Create New Appointment</h1>
            <p>Schedule an appointment for your patients</p>
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
                    <label>Appointment Date *</label>
                    <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Appointment Time *</label>
                    <input type="time" name="time" required>
                </div>

                <div class="form-group">
                    <label>Description / Reason for Visit</label>
                    <textarea name="description" rows="4" placeholder="e.g., Follow-up consultation, Routine checkup, etc."></textarea>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Create Appointment</button>
                    <a href="patients.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>📋 Your Recent Appointments</h2>
            <?php
            $recent_query = "SELECT 
                a.date, a.time, 
                p.first_name, p.last_name,
                a.description
            FROM appointment a
            JOIN patient p ON a.patient_id = p.patient_id
            WHERE a.doctor_id = $doctor_id
            ORDER BY a.date DESC, a.time DESC
            LIMIT 5";
            
            $recent = mysqli_query($conn, $recent_query);
            ?>
            
            <?php if (mysqli_num_rows($recent) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($row['time'])); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No appointments yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>