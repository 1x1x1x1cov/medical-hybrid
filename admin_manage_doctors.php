<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Only admin can access
if (!is_admin()) {
    die("Access denied. Admin only.");
}

$success = '';
$error = '';

// Handle password/username update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_doctor'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $new_username = clean_input($_POST['new_username']);
    $new_password = $_POST['new_password'];
    
    if (empty($new_username)) {
        $error = "Username cannot be empty.";
    } else {
        // Check if username already exists (for other users)
        $check_query = "SELECT user_id FROM users WHERE username = ? AND linked_type = 'doctor' AND linked_id != ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "si", $new_username, $doctor_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($exists) > 0) {
            $error = "Username already taken by another doctor.";
        } else {
            // Update username
            $update_query = "UPDATE users SET username = ? WHERE linked_type = 'doctor' AND linked_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $new_username, $doctor_id);
            mysqli_stmt_execute($stmt);
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_query = "UPDATE users SET password_hash = ? WHERE linked_type = 'doctor' AND linked_id = ?";
                $stmt = mysqli_prepare($conn, $pwd_query);
                mysqli_stmt_bind_param($stmt, "si", $hashed, $doctor_id);
                mysqli_stmt_execute($stmt);
                
                $success = "Username and password updated successfully!";
            } else {
                $success = "Username updated successfully!";
            }
        }
    }
}

// Get all doctors with their login info
$doctors_query = "SELECT 
    d.doctor_id,
    d.first_name,
    d.last_name,
    d.specialization,
    dept.department_name,
    u.username,
    u.user_id
FROM doctor d
LEFT JOIN department dept ON d.department_id = dept.department_id
LEFT JOIN users u ON u.linked_type = 'doctor' AND u.linked_id = d.doctor_id
ORDER BY d.last_name, d.first_name";

$doctors = mysqli_query($conn, $doctors_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctor Accounts - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        function toggleEditForm(doctorId) {
            const form = document.getElementById('edit-form-' + doctorId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
    </script>
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
            <a href="create_doctor.php">Add New Doctor</a>
            <a href="reports.php">Reports</a>
            <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>👥 Manage Doctor Login Accounts</h1>
            <p>Update doctor usernames and passwords. The login credentials can be different from the doctor's actual name.</p>
            <p style="color: #666; font-size: 14px;"><strong>Example:</strong> Dr. Emily Wilson can have username "jack" and password "david" - those credentials will still log into Emily Wilson's account.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>All Doctors</h2>
            <table>
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Specialization</th>
                        <th>Department</th>
                        <th>Current Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doctor = mysqli_fetch_assoc($doctors)): ?>
                        <tr>
                            <td><strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                            <td><?php echo htmlspecialchars($doctor['department_name']); ?></td>
                            <td><?php echo $doctor['username'] ? htmlspecialchars($doctor['username']) : '<em style="color: #999;">No login account</em>'; ?></td>
                            <td>
                                <?php if ($doctor['user_id']): ?>
                                    <button onclick="toggleEditForm(<?php echo $doctor['doctor_id']; ?>)" class="btn btn-primary btn-small">Change Login Info</button>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">No login - use "Add New Doctor"</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if ($doctor['user_id']): ?>
                            <tr id="edit-form-<?php echo $doctor['doctor_id']; ?>" style="display: none; background: #f8f9fa;">
                                <td colspan="5" style="padding: 20px;">
                                    <form method="POST" style="max-width: 600px;">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">
                                        
                                        <h3>Change Login Info for Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                                        <p style="color: #666; margin-bottom: 15px;">You can set any username and password. They don't need to match the doctor's name.</p>
                                        
                                        <div class="form-group">
                                            <label>New Username *</label>
                                            <input type="text" name="new_username" value="<?php echo htmlspecialchars($doctor['username']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>New Password (leave blank to keep current)</label>
                                            <input type="password" name="new_password" placeholder="Enter new password or leave blank">
                                            <small style="color: #666;">Only fill this if you want to change the password</small>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <button type="submit" name="update_doctor" class="btn btn-primary">Update Login Info</button>
                                            <button type="button" onclick="toggleEditForm(<?php echo $doctor['doctor_id']; ?>)" class="btn btn-secondary">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="action-buttons">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>