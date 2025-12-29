# Viewer360 - 360° Panoramic Image Platform

A web-based platform for uploading, viewing, and sharing panoramic (equirectangular) images with an interactive 360° viewer.

## Features

- **User Authentication**: Secure registration and login with password hashing
- **Image Upload**: Support for JPG/PNG panoramic images up to 50MB
- **360° Viewer**: Interactive panoramic viewer using Photo Sphere Viewer
- **Interactive Markers**: Add, edit, and color-code annotation markers on panoramas
- **Deep Linking**: Share direct links to specific markers within panoramas
- **Fork/Remix**: Save public panoramas to your collection with preserved marker attribution
- **Privacy Controls**: Make panoramas public or private
- **Panorama Editing**: Update title, description, and visibility settings
- **Dockerized**: Complete Docker setup with PHP, MySQL, and phpMyAdmin

## Tech Stack

- PHP 8.2 (Native, no frameworks)
- MySQL 8.0
- Bootstrap 5
- Photo Sphere Viewer
- Docker & Docker Compose

## Quick Start

### Prerequisites

- Docker
- Docker Compose

### Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/Bogomil-Stoyanov/Viewer360.git
   cd Viewer360
   ```

2. Start the Docker containers:

   ```bash
   docker-compose up -d
   ```

3. Access the application:
   - **Application**: http://localhost:8080
   - **phpMyAdmin**: http://localhost:8081

4. Run database migrations (if updating from an older version):

   ```bash
   docker exec -it viewer360_web php /var/www/html/public/migrate.php
   ```

   This will safely add any new tables or columns to your existing database.

### Default Credentials

**phpMyAdmin:**

- Username: `viewer360_user`
- Password: `viewer360_pass`

## Project Structure

```
Viewer360/
├── docker/
│   └── init.sql           # Database initialization
├── public/                 # Web root
│   ├── assets/            # CSS, JS, images
│   ├── uploads/           # Uploaded panoramas
│   ├── index.php          # Landing page
│   ├── login.php          # Login page
│   ├── register.php       # Registration page
│   ├── dashboard.php      # User dashboard
│   ├── view.php           # 360° viewer
│   ├── api.php            # REST API endpoints
│   ├── migrate.php        # Database migration script
│   └── logout.php         # Logout handler
├── src/                    # PHP classes
│   ├── Config.php         # Configuration
│   ├── Database.php       # PDO database wrapper
│   └── Controllers/
│       ├── AuthController.php      # Authentication
│       ├── PanoramaController.php  # Panorama management
│       └── MarkerController.php    # Marker CRUD operations
├── views/                  # HTML templates
│   ├── header.php         # Common header
│   └── footer.php         # Common footer
├── docker-compose.yml     # Docker services
├── Dockerfile             # PHP/Apache container
└── README.md
```

## Database Schema

### users

| Column        | Type         | Description       |
| ------------- | ------------ | ----------------- |
| id            | INT          | Primary key       |
| username      | VARCHAR(50)  | Unique username   |
| email         | VARCHAR(100) | Unique email      |
| password_hash | VARCHAR(255) | Hashed password   |
| created_at    | TIMESTAMP    | Registration date |

### panoramas

| Column               | Type         | Description                |
| -------------------- | ------------ | -------------------------- |
| id                   | INT          | Primary key                |
| user_id              | INT          | Foreign key to users       |
| file_path            | VARCHAR(255) | Path to uploaded file      |
| title                | VARCHAR(200) | Panorama title             |
| description          | TEXT         | Optional description       |
| is_public            | BOOLEAN      | Privacy setting            |
| original_panorama_id | INT          | Source panorama if forked  |
| created_at           | TIMESTAMP    | Upload date                |

### markers

| Column      | Type         | Description              |
| ----------- | ------------ | ------------------------ |
| id          | INT          | Primary key              |
| panorama_id | INT          | Foreign key to panoramas |
| user_id     | INT          | Foreign key to users     |
| yaw         | DOUBLE       | Horizontal position      |
| pitch       | DOUBLE       | Vertical position        |
| type        | VARCHAR(50)  | Marker type (text)       |
| color       | VARCHAR(20)  | Marker color             |
| label       | VARCHAR(200) | Marker title             |
| description | TEXT         | Optional description     |
| created_at  | TIMESTAMP    | Creation date            |
| updated_at  | TIMESTAMP    | Last update date         |

## Security Features

- PDO prepared statements (SQL Injection prevention)
- Password hashing with `password_hash()`
- Session-based authentication
- File type validation (MIME + extension)
- Private panorama access control
- XSS prevention with `htmlspecialchars()`

## Configuration

Environment variables (set in `docker-compose.yml`):

| Variable | Default        | Description       |
| -------- | -------------- | ----------------- |
| DB_HOST  | db             | MySQL host        |
| DB_NAME  | viewer360      | Database name     |
| DB_USER  | viewer360_user | Database user     |
| DB_PASS  | viewer360_pass | Database password |

## Development

### Running Database Migrations

When updating the application or pulling new changes that include database schema updates, run the migration script:

```bash
docker exec -it viewer360_web php /var/www/html/public/migrate.php
```

The migration script is idempotent and safe to run multiple times - it checks for existing tables/columns before making changes.

### Stopping containers

```bash
docker-compose down
```

### Rebuilding containers

```bash
docker-compose up -d --build
```

### Viewing logs

```bash
docker-compose logs -f web
```

### Accessing MySQL CLI

```bash
docker exec -it viewer360_db mysql -u viewer360_user -p viewer360
```

## License

MIT License
