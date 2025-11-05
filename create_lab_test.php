<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_login();

// Only doctors can create lab tests
if (!is_doctor() && !is_admin()) {
    die("Access denied.");
}

$success = '';
$error = '';

// Get doctor's patients
if (is_doctor()) {
    $doctor_id = $_SESSION['linked_id'];
    $patients_query = "SELECT DISTINCT p.patient_id, p.first_name, p.last_name 
                       FROM patient p
                       JOIN appointment a ON p.patient_id = a.patient_id
                       WHERE a.doctor_id = $doctor_id
                       ORDER BY p.last_name, p.first_name";
} else {
    $patients_query = "SELECT patient_id, first_name, last_name FROM patient ORDER BY last_name, first_name";
}
$patients = mysqli_query($conn, $patients_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $test_type = clean_input($_POST['test_type']);
    $test_date = clean_input($_POST['test_date']);
    $status = clean_input($_POST['status']);
    $lab_technician = clean_input($_POST['lab_technician']);
    
    // Get dynamic test results fields
    $results = [];
    if (isset($_POST['result_name'])) {
        foreach ($_POST['result_name'] as $index => $name) {
            if (!empty($name) && !empty($_POST['result_value'][$index])) {
                $results[clean_input($name)] = clean_input($_POST['result_value'][$index]);
            }
        }
    }
    
    if (empty($patient_id) || empty($test_type) || empty($test_date)) {
        $error = "Please fill in all required fields.";
    } else {
        // Build JSON data
        $json_data = [
            'patient_id' => $patient_id,
            'test_type' => $test_type,
            'test_date' => $test_date,
            'results' => $results,
            'status' => $status,
            'lab_technician' => $lab_technician,
            'created_by' => $_SESSION['username'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Generate filename
        $doc_type = strtolower(str_replace(' ', '_', $test_type));
        $filename = 'patient_' . $patient_id . '_' . $doc_type . '_' . time() . '.json';
        $json_dir = DATA_PATH . 'json/';
        
        if (!file_exists($json_dir)) {
            mkdir($json_dir, 0777, true);
        }
        
        $file_path = $json_dir . $filename;
        $relative_path = 'data/json/' . $filename;
        
        // Save JSON file
        if (file_put_contents($file_path, json_encode($json_data, JSON_PRETTY_PRINT))) {
            // Insert into database
            $query = "INSERT INTO json_documents (patient_id, doc_type, file_path, created_at) 
                      VALUES (?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iss", $patient_id, $doc_type, $relative_path);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Lab test results saved successfully as JSON file!";
                $_POST = array();
            } else {
                $error = "Database error: " . mysqli_error($conn);
            }
        } else {
            $error = "Failed to save JSON file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Lab Test - Medical Hybrid System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        function addResultField() {
            const container = document.getElementById('results-container');
            const div = document.createElement('div');
            div.className = 'result-row';
            div.style.display = 'flex';
            div.style.gap = '10px';
            div.style.marginBottom = '10px';
            div.innerHTML = `
                <input type="text" name="result_name[]" placeholder="Test name (e.g., Hemoglobin)" style="flex: 1;">
                <input type="text" name="result_value[]" placeholder="Value (e.g., 14.2)" style="flex: 1;">
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-small">Remove</button>
            `;
            container.appendChild(div);
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
            <a href="create_appointment.php">New Appointment</a>
            <a href="reports.php">Reports</a>
            <?php if (is_admin()): ?>
                <a href="http://localhost/phpmyadmin" target="_blank">PHPMyAdmin</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h1>🧪 Create Lab Test Results (Saved as JSON)</h1>
            <p>Enter lab test details below. The system will automatically save this as a JSON file.</p>
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
                        <?php while ($patient = mysqli_fetch_assoc($patients)): ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Test Type *</label>
                    <select name="test_type" required>
                        <option value="">-- Select Test Type --</option>
                        <option value="Complete Blood Count">Complete Blood Count</option>
                        <option value="Lipid Panel">Lipid Panel</option>
                        <option value="Liver Function Test">Liver Function Test</option>
                        <option value="Kidney Function Test">Kidney Function Test</option>
                        <option value="Thyroid Function Test">Thyroid Function Test</option>
                        <option value="X-Ray Report">X-Ray Report</option>
                        <option value="MRI Report">MRI Report</option>
                        <option value="CT Scan Report">CT Scan Report</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Test Date *</label>
                    <input type="date" name="test_date" required max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Test Results</label>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                        Add individual test results (e.g., Hemoglobin: 14.2, WBC: 7200)
                    </p>
                    <div id="results-container">
                        <div class="result-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="result_name[]" placeholder="Test name (e.g., Hemoglobin)" style="flex: 1;">
                            <input type="text" name="result_value[]" placeholder="Value (e.g., 14.2)" style="flex: 1;">
                        </div>
                    </div>
                    <button type="button" onclick="addResultField()" class="btn btn-secondary btn-small">+ Add Another Result</button>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Normal">Normal</option>
                        <option value="Abnormal">Abnormal</option>
                        <option value="Pending">Pending</option>
                        <option value="Requires Follow-up">Requires Follow-up</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Lab Technician Name</label>
                    <input type="text" name="lab_technician" placeholder="Enter technician name">
                </div>

                <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <strong>💡 How This Works:</strong>
                    <p style="margin: 10px 0; font-size: 13px;">
                        When you submit this form, the system will:
                    </p>
                    <ol style="margin-left: 20px; font-size: 13px;">
                        <li>Collect all the data you entered</li>
                        <li>Convert it to JSON format using PHP's <code>json_encode()</code></li>
                        <li>Save it as a <code>.json</code> file in the <code>data/json/</code> directory</li>
                        <li>Store the file path in the database for future retrieval</li>
                        <li>The JSON file can then be read and displayed in reports using <code>json_decode()</code></li>
                    </ol>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Save as JSON</button>
                    <a href="patients.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>