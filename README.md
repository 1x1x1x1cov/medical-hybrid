# Medical Hybrid System

A comprehensive web-based medical management system for hospitals and clinics, built with PHP and MySQL. This system provides role-based access control for administrators and doctors to manage patients, appointments, lab tests, and medical records.

## Features

### User Management
- **Role-Based Access Control**: Admin and Doctor roles with different permissions
- **Secure Authentication**: Password hashing and session management
- **User Accounts**: Link users to doctors or administrative staff

### Patient Management
- Create and manage patient records
- View detailed patient information
- Track patient history and medical records
- Assign patients to doctors

### Doctor Management
- Create and manage doctor profiles
- Track doctor specializations and departments
- Manage doctor user accounts
- View doctor-patient assignments

### Appointments
- Schedule patient appointments
- Track appointment history
- View appointments by doctor or patient

### Lab Tests
- Create and manage lab test records
- Upload lab test results
- Track various test types (blood tests, X-rays, etc.)
- Store test data in JSON format

### File Management
- Upload patient-related files
- Store medical documents and reports
- Support for multiple file formats
- Organized file storage system

### Reports & Analytics
- Generate multiple report types
- Export data in various formats
- PDF report generation using FPDF
- Department metrics and statistics
- Patient medical history reports

## Technology Stack

- **Backend**: PHP 7.x+
- **Database**: MySQL (MariaDB)
- **PDF Generation**: FPDF library
- **Frontend**: HTML5, CSS3, JavaScript
- **Authentication**: Session-based with password hashing

## Project Structure

```
medical-hybrid/
├── api/                    # API endpoints
│   └── generate_report.php
├── assets/                 # Static assets
│   └── css/
│       └── style.css
├── data/                   # Data storage
│   ├── images/            # Medical images
│   ├── json/              # JSON data files
│   ├── reports/           # Generated reports
│   └── uploads/           # Uploaded files
├── includes/              # Shared PHP files
│   ├── fpdf/             # PDF library
│   └── functions.php     # Helper functions
├── config.php             # Database configuration
├── index.php              # Login page
├── dashboard.php          # Main dashboard
├── patients.php           # Patient listing
├── patient_detail.php     # Patient details
├── create_patient.php     # Patient creation
├── create_doctor.php      # Doctor creation
├── create_appointment.php # Appointment scheduling
├── create_lab_test.php    # Lab test creation
├── assign_patient.php     # Patient-doctor assignment
├── admin_manage_doctors.php # Doctor account management
├── upload_file.php        # File upload handler
├── export.php             # Data export
├── reports.php            # Reports dashboard
├── report_1.php           # Report type 1
├── report_2.php           # Report type 2
├── report_3.php           # Report type 3
└── logout.php             # Logout handler
```

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.x
- Apache/Nginx web server
- phpMyAdmin (recommended for database management)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd medical-hybrid
   ```

2. **Configure the database**

   Edit `config.php` and update the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'pphase_3');
   ```

3. **Import the database schema**

   Create a database named `pphase_3` and import the schema (if provided):
   ```bash
   mysql -u your_db_user -p pphase_3 < database.sql
   ```

4. **Set up file permissions**
   ```bash
   chmod 755 data/
   chmod 755 data/uploads/
   chmod 755 data/json/
   chmod 755 data/reports/
   chmod 755 data/images/
   ```

5. **Configure timezone**

   The system is configured for `Australia/Melbourne` timezone. Update in `config.php` if needed:
   ```php
   date_default_timezone_set('Australia/Melbourne');
   ```

6. **Access the application**

   Navigate to `http://localhost/medical-hybrid/` in your web browser.

## Default Login Credentials

The system comes with demo accounts:

- **Admin Account**
  - Username: `admin`
  - Password: `password`

- **Doctor Accounts**
  - Username: `dr_emily_chen` / Password: `password`
  - Username: `dr_david_lee` / Password: `password`

**Important**: Change these passwords immediately in a production environment.

## Database Schema

The system uses the following main tables:

- `users` - User accounts with authentication
- `roles` - User roles (admin, doctor)
- `patient` - Patient records
- `doctor` - Doctor information
- `appointment` - Appointment records
- `file_storage` - File metadata
- Additional tables for lab tests, medical records, etc.

## Usage

### Admin Functions
- Create and manage patients
- Create and manage doctors
- Assign patients to doctors
- Manage doctor user accounts
- Access all system features
- View system-wide statistics
- Generate reports

### Doctor Functions
- View assigned patients
- Create appointments
- Add lab test results
- Upload patient files
- Generate patient reports
- View personal statistics

## Security Features

- Password hashing using PHP's `password_hash()`
- Prepared statements to prevent SQL injection
- Session-based authentication
- Input sanitization and validation
- Role-based access control
- HTTPS recommended for production

## File Storage

The system stores various file types:

- **JSON files**: Lab test results, department metrics
- **Text files**: Patient notes and documents
- **Images**: Medical imaging files
- **PDFs**: Generated reports

All files are organized in the `data/` directory structure.

## Development

### Adding New Features

1. Follow the existing file structure
2. Use the helper functions in `includes/functions.php`
3. Implement proper authentication checks
4. Maintain consistent UI/UX with existing pages

### Database Queries

Always use prepared statements:
```php
$stmt = mysqli_prepare($conn, "SELECT * FROM patient WHERE patient_id = ?");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
```

## Troubleshooting

### Database Connection Issues
- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database user permissions

### File Upload Issues
- Verify directory permissions
- Check PHP upload settings in `php.ini`
- Ensure `upload_max_filesize` and `post_max_size` are sufficient

### Session Issues
- Ensure sessions are enabled in PHP
- Check session directory permissions
- Verify `session_start()` is called before any output

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For issues, questions, or contributions, please contact the development team or open an issue in the repository.

## Future Enhancements

- Patient portal for self-service
- Mobile application
- Email notifications
- Advanced analytics dashboard
- Integration with medical devices
- Telemedicine support
- Prescription management
- Billing and insurance integration
