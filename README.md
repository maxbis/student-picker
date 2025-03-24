# Student Picker

A web-based application that helps teachers randomly select students from their classes. Built with PHP and MySQL.

## Features

- Secure teacher login system
- Class management (create and organize classes)
- Student management (add, activate/deactivate, and delete students)
- Bulk student import
- Random student selection with animation
- Session tracking to prevent the same student from being selected twice
- Responsive design that works on all devices

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/student-picker.git
cd student-picker
```

2. Create the database:
```bash
mysql -u root -p < database.sql
```

3. Set up database credentials:
   - Copy `db_credentials.template.php` to `db_credentials.php`
   - Update the database credentials in `db_credentials.php` with your local settings:
```php
define('DB_HOST', 'localhost');     // Your database host
define('DB_USER', 'your_username'); // Your database username
define('DB_PASS', 'your_password'); // Your database password
define('DB_NAME', 'student_picker'); // Your database name
```

4. Configure your web server to point to the project directory

5. Access the application through your web browser

## Default Login

- Username: `admin`
- Password: `admin123`

## Usage

### Managing Classes

1. Log in to the dashboard
2. Create a new class using the form at the bottom
3. Use the class cards to:
   - Edit class details and manage students
   - Start the random student picker

### Managing Students

1. Click "Edit Class" on any class card
2. Add individual students or bulk import them
3. Activate/deactivate students as needed
4. Delete students if necessary

### Picking Students

1. Click "Pick Student" on any class card
2. Click the "Pick Random Student" button to start the selection
3. The animation will automatically stop after 3 seconds
4. Previously selected students are tracked in the session
5. Use the "Reset Selected Students" button to clear the selection history

## Security Features

- Password hashing for teacher accounts
- Session-based authentication
- SQL injection prevention using prepared statements
- XSS prevention through proper output escaping
- Secure database credentials management

## File Structure

```
student-picker/
├── config.php              # Database connection setup
├── db_credentials.php      # Database credentials (not in repo)
├── db_credentials.template.php  # Template for credentials
├── database.sql           # Database schema
├── index.php             # Login page
├── dashboard.php         # Main dashboard
├── manage_students.php   # Student management
├── picker.php           # Random student picker
├── logout.php           # Logout handler
└── style.css            # Application styles
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Font Awesome for icons
- Modern CSS Grid and Flexbox for layout
- PHP PDO for secure database operations 