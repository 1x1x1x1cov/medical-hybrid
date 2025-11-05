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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $dob = clean_input($_POST['dob']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($dob)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert patient
        $query = "INSERT INTO patient (first_name, last_name, dob, email, phone) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssss", $first_name, $last_name, $dob, $email, $phone);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_patient_id = mysqli_insert_id($conn);
            $success = "Patient created successfully! Patient ID: $new_patient_id. Now assign them to a doctor.";
            $_POST = array();
        } else {
            $error = "Error creating patient: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Patient - Medical Hybrid System</title>
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
            <h1>➕ Create New Patient</h1>
            <p>Add a new patient to the system. After creating, assign them to a doctor.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><br>
                <a href="assign_patient.php" class="btn btn-primary btn-small">Assign to Doctor Now</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Date of Birth *</label>
                    <input type="date" name="dob" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="patient@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="04XX XXX XXX" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Create Patient</button>
                    <a href="patients.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>👥 Recently Added Patients (Last 10)</h2>
            <?php
            $recent_query = "SELECT 
                patient_id,
                first_name,
                last_name,
                dob,
                email,
                phone
            FROM patient
            ORDER BY patient_id DESC
            LIMIT 10";
            
            $recent = mysqli_query($conn, $recent_query);
            ?>
            
            <?php if (mysqli_num_rows($recent) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>DOB</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td><?php echo $row['patient_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['dob'])); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td>
                                    <a href="patient_detail.php?id=<?php echo $row['patient_id']; ?>" class="btn btn-primary btn-small">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No patients yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>