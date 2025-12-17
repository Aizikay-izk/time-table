#!/bin/bash

# Heroku Deployment Script for Academic Timetable System
# Run this script to deploy to Heroku

echo "🚀 Academic Timetable System - Heroku Deployment"
echo "================================================"

# Check if Heroku CLI is installed
if ! command -v heroku &> /dev/null; then
    echo "❌ Heroku CLI is not installed."
    echo "Please install from: https://devcenter.heroku.com/articles/heroku-cli"
    exit 1
fi

# Check if logged in to Heroku
if ! heroku whoami &> /dev/null; then
    echo "🔐 Please login to Heroku:"
    heroku login
fi

# Ask for app name
read -p "Enter Heroku app name (or press enter for auto-generated): " APP_NAME

if [ -z "$APP_NAME" ]; then
    APP_NAME="academic-timetable-$(date +%s)"
    echo "Using auto-generated name: $APP_NAME"
fi

# Create Heroku app
echo "📦 Creating Heroku app: $APP_NAME"
heroku create $APP_NAME

# Add ClearDB MySQL addon
echo "🗄️ Adding ClearDB MySQL database..."
heroku addons:create cleardb:ignite -a $APP_NAME

# Add Papertrail for logs
echo "📝 Adding Papertrail for logging..."
heroku addons:create papertrail:choklad -a $APP_NAME

# Get database URL and parse it
echo "🔧 Configuring database..."
CLEARDB_URL=$(heroku config:get CLEARDB_DATABASE_URL -a $APP_NAME)

if [ -z "$CLEARDB_URL" ]; then
    echo "⚠️ Could not get ClearDB URL. Please check addon status."
    read -p "Enter database URL manually (mysql://user:pass@host/db): " CLEARDB_URL
fi

# Parse the URL
if [[ $CLEARDB_URL =~ mysql://([^:]+):([^@]+)@([^/]+)/(.+) ]]; then
    DB_USER=${BASH_REMATCH[1]}
    DB_PASS=${BASH_REMATCH[2]}
    DB_HOST=${BASH_REMATCH[3]}
    DB_NAME=${BASH_REMATCH[4]}
    
    # Set environment variables
    echo "⚙️ Setting environment variables..."
    heroku config:set DB_HOST=$DB_HOST -a $APP_NAME
    heroku config:set DB_USER=$DB_USER -a $APP_NAME
    heroku config:set DB_PASSWORD=$DB_PASS -a $APP_NAME
    heroku config:set DB_NAME=$DB_NAME -a $APP_NAME
    heroku config:set BASE_URL="https://$APP_NAME.herokuapp.com" -a $APP_NAME
    heroku config:set APP_ENV=production -a $APP_NAME
    
    echo "✅ Database configured:"
    echo "   Host: $DB_HOST"
    echo "   Database: $DB_NAME"
    echo "   User: $DB_USER"
else
    echo "❌ Could not parse database URL"
    exit 1
fi

# Deploy the app
echo "🚀 Deploying to Heroku..."
git init
heroku git:remote -a $APP_NAME
git add .
git commit -m "Initial deploy of Academic Timetable System"
git push heroku master

# Run setup
echo "🔧 Running setup script..."
heroku run "php setup.php" -a $APP_NAME

# Open the app
echo "🌐 Opening application..."
heroku open -a $APP_NAME

echo ""
echo "================================================"
echo "🎉 Deployment Complete!"
echo ""
echo "📋 Application Information:"
echo "   URL: https://$APP_NAME.herokuapp.com"
echo "   Admin: https://$APP_NAME.herokuapp.com/superadmin"
echo "   Default login: superadmin / admin123"
echo ""
echo "🔧 Management Commands:"
echo "   View logs: heroku logs --tail -a $APP_NAME"
echo "   Run console: heroku run bash -a $APP_NAME"
echo "   Config vars: heroku config -a $APP_NAME"
echo ""
echo "⚠️ Important: Change the default superadmin password!"
echo "================================================"
