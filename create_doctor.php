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

// Get departments for dropdown
$departments_query = "SELECT department_id, department_name FROM department ORDER BY department_name";
$departments = mysqli_query($conn, $departments_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $specialization = clean_input($_POST['specialization']);
    $phone = clean_input($_POST['phone']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    
    // Validate
    if (empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert doctor
        $query = "INSERT INTO doctor (first_name, last_name, specialization, phone, department_id) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssi", $first_name, $last_name, $specialization, $phone, $department_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $new_doctor_id = mysqli_insert_id($conn);
            $success = "Doctor created successfully! Doctor ID: $new_doctor_id. You can now create a login account for them.";
            $_POST = array();
        } else {
            $error = "Error creating doctor: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Doctor - Medical Hybrid System</title>
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
            <h1>👨‍⚕️ Create New Doctor</h1>
            <p>Add a new doctor to the system. After creating, you can create a login account for them in PHPMyAdmin.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <br><br>
                <strong>Next Steps:</strong>
                <ol style="margin: 10px 0; padding-left: 20px;">
                    <li>Go to PHPMyAdmin → <code>users</code> table</li>
                    <li>Insert new row with: username, password_hash, role_id=2 (doctor), linked_type='doctor', linked_id=[new doctor ID]</li>
                    <li>Doctor can then login to the web application</li>
                </ol>
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
                    <label>Specialization</label>
                    <input type="text" name="specialization" placeholder="e.g., Cardiology, Neurology, General Medicine" value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="04XX XXX XXX" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">-- No Department (Assign Later) --</option>
                        <?php 
                        mysqli_data_seek($departments, 0);
                        while ($dept = mysqli_fetch_assoc($departments)): 
                        ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Create Doctor</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>👨‍⚕️ All Doctors</h2>
            <?php
            $doctors_query = "SELECT 
                d.doctor_id,
                d.first_name,
                d.last_name,
                d.specialization,
                d.phone,
                dept.department_name,
                COUNT(DISTINCT a.appointment_id) as appointment_count
            FROM doctor d
            LEFT JOIN department dept ON d.department_id = dept.department_id
            LEFT JOIN appointment a ON d.doctor_id = a.doctor_id
            GROUP BY d.doctor_id
            ORDER BY d.last_name, d.first_name";
            
            $doctors = mysqli_query($conn, $doctors_query);
            ?>
            
            <?php if (mysqli_num_rows($doctors) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Specialization</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Appointments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($doctors)): ?>
                            <tr>
                                <td><?php echo $row['doctor_id']; ?></td>
                                <td>Dr. <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'Not assigned'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo $row['appointment_count']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No doctors yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>