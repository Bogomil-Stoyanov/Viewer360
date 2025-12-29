# Viewer360 - 360° Panoramic Image Platform

A web-based platform for uploading, viewing, and sharing panoramic (equirectangular) images with an interactive 360° viewer.

## Features

- **User Authentication**: Secure registration and login with password hashing
- **Image Upload**: Support for JPG/PNG panoramic images up to 20MB
- **360° Viewer**: Interactive panoramic viewer using Photo Sphere Viewer
- **Privacy Controls**: Make panoramas public or private
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
│   └── logout.php         # Logout handler
├── src/                    # PHP classes
│   ├── Config.php         # Configuration
│   ├── Database.php       # PDO database wrapper
│   └── Controllers/
│       ├── AuthController.php      # Authentication
│       └── PanoramaController.php  # Panorama management
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

| Column      | Type         | Description           |
| ----------- | ------------ | --------------------- |
| id          | INT          | Primary key           |
| user_id     | INT          | Foreign key to users  |
| file_path   | VARCHAR(255) | Path to uploaded file |
| title       | VARCHAR(200) | Panorama title        |
| description | TEXT         | Optional description  |
| is_public   | BOOLEAN      | Privacy setting       |
| created_at  | TIMESTAMP    | Upload date           |

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
