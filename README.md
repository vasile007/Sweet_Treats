
# Sweet Treats

**Sweet_Treats** is a bakery web application developed as part of a university project. This project demonstrates skills in PHP, MySQL, HTML, CSS, and secure web development practices.

---

## Project Overview

Sweet_Treats offers a user-friendly platform to browse bakery products, manage user authentication, and administer content through an admin panel. The application prioritizes security by using password hashing and sanitizing user input to prevent common web vulnerabilities.

---

## Installation & Setup

1. Clone the repository:  
   `git clone https://github.com/vasile007/Sweet_Treats.git`

2. Open your XAMPP control panel and start Apache and MySQL.

3. Import the provided SQL database (found in `/database/sweet_treats_db.sql`) into phpMyAdmin.

4. Configure your database connection in the `config.php` file.

5. Place the project folder inside your XAMPP `htdocs` directory.

6. Open your browser and go to:  
   `http://localhost/Sweet_Treats_VASI/`

---

## Database

The MySQL database for Sweet_Treats includes three main tables designed to support the application's functionality:

- **admins**: Stores admin user information with columns for email and securely hashed passwords.

- **customer_feedback**: Contains customer feedback data including name, email, message content, and submission timestamp.

- **menu_items**: Holds bakery product details such as name, description, price, image path, and timestamps for creation and updates.

For convenience, a full database export file (`/database/sweet_treats_db.sql`) is included in the repository to simplify setup.

---

## Security Considerations

- Passwords are securely hashed using PHP’s `password_hash` function.

- User inputs are sanitized to protect against SQL injection and Cross-Site Scripting (XSS) attacks.

---

## Configuration

Please update the `config.php` file with your local database credentials before running the project.

Make sure **NOT** to commit sensitive information like passwords or private keys.

---

## Technologies Used

- PHP 8  
- MySQL  
- HTML5 & CSS3  
- XAMPP Server  
- Tailwind CSS (for styling)

---

## Project Structure

- `/admin_panel.php` — Admin panel for managing the site.  
- `/change_password.php` — Password change functionality.  
- `/config.php` — Database connection configuration.  
- `/feedback.php` — User feedback handling.  
- `/generate_hash_password.php` — Password hashing utility.  
- `/index.php` — Main homepage.  
- `/test_password.php` — Password validation script.  
- `/verify_password.php` — Password verification logic.  
- `/uploads/` — Folder containing product and site images.  
- `/style.css` — Styling for the website.

---

## Contact

For questions or collaboration, feel free to reach out:

**Vasile Bejan**  
✉️ bejan.vasi@yahoo.com

---

*This project was developed as part of a university assignment and serves as a portfolio piece for professional development.*

