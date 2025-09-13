# README-LOCAL-RUN.md
# Local Development Setup Guide for Defect Tracker
# This guide helps you run the defect tracking system on your local computer using Docker.
# The defect tracker is a PHP-based application (NOT Laravel) for managing construction project defects.

## What You'll Get

This setup provides:
- ✅ Complete defect tracking system running locally
- ✅ MySQL database with persistent data storage
- ✅ Adminer for easy database management
- ✅ Live code editing - changes appear immediately
- ✅ No need to install PHP, MySQL, or Apache on your computer

## Prerequisites

Before starting, make sure you have:
- Docker Desktop installed and running
- Git (to clone the repository)
- A text editor for making code changes

## Quick Start Guide

### Step 1: Copy Environment Configuration
First, create your local environment configuration file:
```bash
cp .env.example .env
```

The default settings in .env work perfectly for local development, but you can modify them if needed.

### Step 2: Build and Start the Environment
Run this command to build and start all services:
```bash
docker compose up --build
```

This will:
- Build the PHP/Apache web server with all required extensions
- Start a MySQL 8.0 database
- Start Adminer for database management
- Download and install all necessary components

**First run takes 2-3 minutes** - subsequent starts are much faster!

### Step 3: Install PHP Dependencies
Once the containers are running, open a new terminal and install the PHP dependencies:
```bash
docker compose exec web composer install
```

If there's no composer.json file in the project root, you can skip this step.

### Step 4: Access Your Application
Your defect tracker is now running! Open these URLs in your browser:

- **Main Application**: http://localhost:8000
- **Database Management (Adminer)**: http://localhost:8080

#### Accessing the Database via Adminer
1. Go to http://localhost:8080
2. Use these connection details:
   - **System**: MySQL
   - **Server**: db
   - **Username**: root
   - **Password**: root_password
   - **Database**: defect_tracker

## Live Code Editing

One of the best features of this setup is live editing:

- ✨ Edit any PHP file on your computer
- ✨ Refresh your browser to see changes immediately
- ✨ No need to restart containers or rebuild anything
- ✨ Database changes persist between sessions

## Managing the Environment

### View Running Containers
```bash
docker compose ps
```

### View Application Logs
```bash
docker compose logs web
```

### View Database Logs
```bash
docker compose logs db
```

### Stop the Environment
Press `Ctrl+C` in the terminal where docker compose is running, or run:
```bash
docker compose down
```

### Stop and Remove All Data
⚠️ **Warning**: This deletes your database!
```bash
docker compose down -v
```

### Restart Services
```bash
docker compose restart
```

## Alternative: PHP Built-in Server (Quick Testing)

If you have PHP installed locally and just want to test quickly:

1. Make sure you have PHP 7.4+ with required extensions
2. Create a simple database connection or use SQLite
3. Run: `php -S localhost:8000`
4. Visit http://localhost:8000

**Note**: This method doesn't include database setup and some features may not work.

## Troubleshooting

### Port Already in Use
If you get "port already in use" errors:
- Change the ports in docker-compose.yml (e.g., use 8001:80 instead of 8000:80)
- Or stop other applications using ports 8000, 8080, or 3306

### Permission Issues
If you get permission errors:
```bash
docker compose exec web chown -R www-data:www-data /var/www/html
```

### Database Connection Issues
1. Ensure the database service is running: `docker compose ps`
2. Check the .env file has correct database settings
3. Wait a minute after startup - MySQL takes time to initialize

### Starting Fresh
To completely reset everything:
```bash
docker compose down -v
docker compose up --build
```

## Project Structure

This is a **PHP-based defect tracking system** with these key directories:
- `api/` - REST-like endpoints for CRUD operations
- `classes/` - Core business logic (authentication, email, logging)
- `config/` - Database and application configuration
- `assets/` - Images, icons, and floor plans
- `admin/` - System administration tools

## Making Code Changes

1. Edit any PHP file using your preferred editor
2. Save the file
3. Refresh your browser - changes appear immediately
4. Use Adminer to inspect database changes
5. Check container logs if something isn't working: `docker-compose logs web`

## Database Management

- **Backup**: Use Adminer's export feature or the application's built-in backup tools
- **Import**: Use Adminer's import feature or SQL command line
- **Reset**: Stop containers and remove the db_data volume

## Getting Help

If you encounter issues:
1. Check the container logs: `docker compose logs`
2. Ensure Docker Desktop is running
3. Try stopping and restarting: `docker compose restart`
4. For database issues, use Adminer to inspect the database structure

---

**Created**: 13th December 2024  
**Last Updated**: 13th December 2024  
**Environment**: Docker Compose with PHP 8.2, MySQL 8.0, Apache 2.4