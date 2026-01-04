# Habit Tracker PHP Backend API

This is the PHP backend API for the Habit Tracker mobile application.

## Setup Instructions

### 1. Database Setup

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Import the database schema:
   - Go to "Import" tab
   - Choose the file `database/schema.sql`
   - Click "Go" to import

   OR manually run the SQL commands from `database/schema.sql`

### 2. Configuration

1. Edit `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');  // Your database username
   define('DB_PASS', '');      // Your database password
   define('DB_NAME', 'habit_tracker');
   ```

2. Edit `config/config.php`:
   - Change `JWT_SECRET` to a random string for security:
   ```php
   define('JWT_SECRET', 'your-random-secret-key-here');
   ```

### 3. File Structure

Place the `backend` folder in your web server directory:
- XAMPP: `C:\xampp\htdocs\habit_tracker_api\`
- WAMP: `C:\wamp64\www\habit_tracker_api\`
- MAMP: `/Applications/MAMP/htdocs/habit_tracker_api/`

### 4. Update Flutter App API URL

In `lib/config/api_config.dart`, update the base URL:
- For Android emulator: `http://10.0.2.2/habit_tracker_api`
- For iOS simulator: `http://localhost/habit_tracker_api`
- For physical device: `http://YOUR_COMPUTER_IP/habit_tracker_api`

## API Endpoints

### Authentication

- `POST /auth/register.php` - Register a new user
- `POST /auth/login.php` - Login user
- `POST /auth/forgot_password.php` - Request password reset
- `POST /auth/logout.php` - Logout user

### Habits

- `GET /habits/index.php?user_id={userId}` - Get all habits for a user
- `GET /habits/index.php?id={habitId}` - Get a single habit
- `POST /habits/index.php` - Create a new habit
- `PUT /habits/index.php?id={habitId}` - Update a habit
- `DELETE /habits/index.php?id={habitId}` - Delete a habit

## Request/Response Format

All requests should use `Content-Type: application/json`.

All responses are in JSON format:
```json
{
  "success": true,
  "data": {...},
  "message": "..."
}
```

## Authentication

Most endpoints require authentication via Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

The token is obtained from the login or register endpoints.

## Testing

You can test the API using:
- Postman
- cURL
- Browser (for GET requests only)

Example login request:
```bash
curl -X POST http://localhost/habit_tracker_api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

