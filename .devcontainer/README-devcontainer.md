# README-devcontainer.md
# VS Code Dev Container Setup Guide for Defect Tracker
# Created: 26th January 2025, 17:30 GMT

This guide will help you set up and use the VS Code Dev Container for the Defect Tracker project. No technical knowledge required - just follow these simple steps!

## What is a Dev Container?

A Dev Container is like having a pre-configured computer inside your computer that has everything needed to work on this project. It includes PHP, MySQL database, and all the tools you need, without installing anything directly on your machine.

## Before You Start

You'll need these programs installed on your computer:

### Step 1: Install Docker Desktop
1. Go to [https://www.docker.com/products/docker-desktop/](https://www.docker.com/products/docker-desktop/)
2. Download Docker Desktop for your operating system (Windows, Mac, or Linux)
3. Install it by following the installation wizard
4. Start Docker Desktop (you'll see a whale icon in your system tray/menu bar when it's running)

### Step 2: Install VS Code
1. Go to [https://code.visualstudio.com/](https://code.visualstudio.com/)
2. Download and install VS Code for your operating system
3. Launch VS Code

### Step 3: Install the Dev Containers Extension
1. Open VS Code
2. Click the Extensions icon in the left sidebar (looks like four squares)
3. Search for "Dev Containers" by Microsoft
4. Click "Install"

## Opening the Project in a Dev Container

### Step 1: Open the Project
1. Open VS Code
2. Go to File → Open Folder
3. Select the defect-tracker project folder

### Step 2: Reopen in Container
1. VS Code should show a popup asking if you want to "Reopen in Container"
2. Click "Reopen in Container"
3. **Alternative method**: Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on Mac), type "Dev Containers: Reopen in Container" and press Enter

### Step 3: Wait for Setup
- The first time will take 5-10 minutes as it downloads and sets up everything
- You'll see progress messages at the bottom of VS Code
- Once complete, you'll see "Dev container setup complete!" message

## What You Get

Once the container is running, you'll have access to:

### **Web Application**: http://localhost:8000
- This is where you can view and test the defect tracker website
- Any changes you make to the code will immediately appear here

### **Database Admin (Adminer)**: http://localhost:8080
- Username: `defecttracker`
- Password: `password`
- Database: `defecttracker`
- This lets you view and manage the database without technical knowledge

### **Direct Database Access**: Port 3306
- For advanced users who want to connect database tools directly

## Working with Files

### Where to Find Your Files
- All your project files are in `/var/www/html/` inside the container
- They're exactly the same as the files on your computer - changes sync automatically!

### Live Editing
- Edit any `.php`, `.html`, `.css`, or `.js` file in VS Code
- Save the file (`Ctrl+S` or `Cmd+S`)
- Refresh your web browser at http://localhost:8000 to see changes instantly

### Important Folders
- `uploads/` - Where uploaded images and files are stored
- `logs/` - Application log files for troubleshooting
- `config/` - Database and application settings

## Stopping the Environment

### Quick Stop
1. Close VS Code
2. The containers will stop automatically

### Complete Shutdown
1. Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on Mac)
2. Type "Dev Containers: Reopen Folder Locally"
3. Press Enter
4. This closes the container and returns you to normal VS Code

## Troubleshooting

### Container Won't Start
- Make sure Docker Desktop is running (check for whale icon)
- Try restarting Docker Desktop
- Check that no other programs are using ports 8000, 8080, or 3306

### Website Shows Error
- Wait a minute after container startup for database to initialize
- Check the database connection at http://localhost:8080
- Look at log files in the `logs/` folder

### Can't See Changes
- Make sure you saved the file (`Ctrl+S` or `Cmd+S`)
- Hard refresh your browser (`Ctrl+Shift+R` or `Cmd+Shift+R`)
- Check the VS Code terminal for error messages

### Starting Fresh
1. Close VS Code
2. Open Docker Desktop
3. Go to Containers and delete any "defect-tracker" containers
4. Go to Images and delete "defect-tracker" images
5. Reopen the project in VS Code and select "Reopen in Container"

## Security Note

⚠️ **Important**: This development environment is for local development only. The database passwords and settings are not secure and should never be used in production or on a public server.

## Getting Help

If you're stuck:
1. Check this README again
2. Look at the VS Code terminal for error messages (View → Terminal)
3. Try the troubleshooting steps above
4. Ask for help from the development team

Remember: This container keeps your computer clean by isolating all the development tools. When you're done with the project, you can simply delete the containers and nothing stays behind on your system!