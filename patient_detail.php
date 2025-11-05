<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

$is_admin_user = is_admin();
$is_doctor_user = is_doctor();

// Get patient ID
if (!isset($_GET['id'])) {
    header('Location: patients.php');
    exit();
}

$patient_id = (int)$_GET['id'];

// Get patient details (with RBAC check)
if ($is_doctor_user) {
    $doctor_id = $_SESSION['linked_id'];
    // Check if doctor has access to this patient
    $access_check = "SELECT COUNT(*) as count FROM appointment WHERE patient_id = $patient_id AND doctor_id = $doctor_id";
    $has_access = mysqli_fetch_assoc(mysqli_query($conn, $access_check))['count'] > 0;
    
    if (!$has_access) {
        die("Access denied. This patient is not assigned to you.");
    }
}

// Get patient info
$patient_query = "SELECT * FROM patient WHERE patient_id = $patient_id";
$patient = mysqli_fetch_assoc(mysqli_query($conn, $patient_query));

if (!$patient) {
    die("Patient not found.");
}

// Get medical records
$medical_records_query = "SELECT * FROM medical_record WHERE patient_id = $patient_id ORDER BY date DESC";
$medical_records = mysqli_query($conn, $medical_records_query);

// Get appointments with doctor info
$appointments_query = "SELECT a.*, d.first_name, d.last_name, dept.department_name 
                       FROM appointment a
                       JOIN doctor d ON a.doctor_id = d.doctor_id
                       LEFT JOIN department dept ON d.department_id = dept.department_id
                       WHERE a.patient_id = $patient_id
                       ORDER BY a.date DESC, a.time DESC";
$appointments = mysqli_query($conn, $appointments_query);

// Get uploaded files
$files_query = "SELECT f.*, d.first_name, d.last_name 
                FROM file_storage f
                LEFT JOIN doctor d ON f.uploaded_by_doctor_id = d.doctor_id
                WHERE f.patient_id = $patient_id
                ORDER BY f.upload_date DESC";
$files = mysqli_query($conn, $files_query);

// Get JSON documents
$json_query = "SELECT * FROM json_documents WHERE patient_id = $patient_id ORDER BY created_at DESC";
$json_docs = mysqli_query($conn, $json_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Medical Hybrid System</title>
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
            <?php if ($is_doctor_user || $is_admin_user): ?>
                <a href="create_appointment.php">New Appointment</a>
                <a href="create_lab_test.php">New Lab Test</a>
            <?php endif; ?>
            <a href="reports.php">Reports</a>
            <?php if ($is_admin_user): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h1>
            <p>
                <strong>DOB:</strong> <?php echo date('F j, Y', strtotime($patient['dob'])); ?> |
                <strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?> |
                <strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?>
            </p>
        </div>

        <!-- Medical Records (Structured Data) -->
        <div class="card">
            <h2>📋 Medical Records</h2>
            <?php if (mysqli_num_rows($medical_records) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Medical History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($record = mysqli_fetch_assoc($medical_records)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($record['medical_history'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No medical records found.</p>
            <?php endif; ?>
        </div>

        <!-- Appointments (Structured Data) -->
        <div class="card">
            <h2>📅 Appointments</h2>
            <?php if (mysqli_num_rows($appointments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($appt = mysqli_fetch_assoc($appointments)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($appt['date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($appt['time'])); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($appt['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($appt['description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No appointments found.</p>
            <?php endif; ?>
        </div>

        <!-- JSON Lab Results (Semi-Structured Data) -->
        <div class="card">
            <h2>🧪 Lab Results (JSON Data)</h2>
            
            <?php if ($is_doctor_user || $is_admin_user): ?>
                <?php if (isset($_GET['json_upload']) && $_GET['json_upload'] == 'success'): ?>
                    <div class="alert alert-success">JSON file uploaded successfully!</div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (mysqli_num_rows($json_docs) > 0): ?>
                <?php 
                mysqli_data_seek($json_docs, 0);
                while ($json_doc = mysqli_fetch_assoc($json_docs)): 
                    $json_data = read_json_file($json_doc['file_path']);
                    if ($json_data):
                ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h3><?php echo htmlspecialchars($json_data['test_type'] ?? 'Lab Test'); ?></h3>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($json_data['test_date'] ?? 'N/A'); ?></p>
                                
                                <?php if (isset($json_data['results'])): ?>
                                    <h4>Results:</h4>
                                    <ul>
                                        <?php foreach ($json_data['results'] as $key => $value): ?>
                                            <li><strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <?php if (isset($json_data['status'])): ?>
                                    <p><strong>Status:</strong> <span style="color: green;"><?php echo htmlspecialchars($json_data['status']); ?></span></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="download_json.php?doc_id=<?php echo $json_doc['doc_id']; ?>" class="btn btn-success btn-small">Download JSON</a>
                            </div>
                        </div>
                    </div>
                <?php endif; endwhile; ?>
            <?php else: ?>
                <p>No lab results found.</p>
            <?php endif; ?>
        </div>

        <!-- Uploaded Files (Unstructured Data) -->
        <div class="card">
            <h2>📁 Uploaded Files</h2>
            
            <?php if ($is_doctor_user): ?>
                <?php if (isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
                    <div class="alert alert-success">File uploaded successfully!</div>
                <?php endif; ?>
                
                <form action="upload_file.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="form-group">
                        <label>Upload New File</label>
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx" required>
                        <small style="color: #666;">Accepted: Images (JPG, PNG, GIF), Documents (PDF, TXT, DOC, DOCX)</small>
                    </div>
                    <div class="form-group">
                        <label>Category (for images only)</label>
                        <select name="category">
                            <option value="xray">X-Ray</option>
                            <option value="prescription">Prescription</option>
                            <option value="lab_result">Lab Result</option>
                            <option value="scan">Scan</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Upload File</button>
                </form>
            <?php endif; ?>

            <?php if (mysqli_num_rows($files) > 0): ?>
                <div class="file-grid">
                    <?php while ($file = mysqli_fetch_assoc($files)): ?>
                        <div class="file-item">
                            <?php 
                            $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                            $file_full_path = BASE_PATH . $file['file_path'];
                            ?>
                            
                            <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?php echo htmlspecialchars($file['file_path']); ?>" alt="<?php echo htmlspecialchars($file['file_name']); ?>">
                            
                            <?php elseif ($file_extension == 'txt' && file_exists($file_full_path)): ?>
                                <div style="height: 150px; overflow: auto; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; font-size: 11px; text-align: left;">
                                    <?php 
                                    $text_content = file_get_contents($file_full_path);
                                    echo nl2br(htmlspecialchars(substr($text_content, 0, 300)));
                                    if (strlen($text_content) > 300) echo '...';
                                    ?>
                                </div>
                            
                            <?php else: ?>
                                <div style="height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #e9ecef; border-radius: 5px;">
                                    <span style="font-size: 48px;">
                                        <?php 
                                        if ($file_extension == 'pdf') echo '📕';
                                        elseif (in_array($file_extension, ['doc', 'docx'])) echo '📘';
                                        else echo '📄';
                                        ?>
                                    </span>
                                    <p style="margin-top: 10px; font-size: 10px; color: #666;"><?php echo strtoupper($file_extension); ?> file</p>
                                </div>
                            <?php endif; ?>
                            
                            <p><strong><?php echo htmlspecialchars($file['file_name']); ?></strong></p>
                            <p>Category: <?php echo htmlspecialchars($file['file_category']); ?></p>
                            <p>Uploaded: <?php echo date('M j, Y', strtotime($file['upload_date'])); ?></p>
                            <?php if ($file['first_name']): ?>
                                <p>By: Dr. <?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($file['notes']): ?>
                                <p><em><?php echo htmlspecialchars($file['notes']); ?></em></p>
                            <?php endif; ?>
                            
                            <?php if ($file_extension == 'txt'): ?>
                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-primary btn-small" style="margin-top: 5px;">View Full Text</a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No files uploaded yet.</p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="patients.php" class="btn btn-secondary">Back to Patients</a>
        </div>
    </div>
</body>
</html>