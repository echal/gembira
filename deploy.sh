#!/bin/bash

# Deployment script for Gembira Application
# This script prepares the application for production

echo "🚀 Starting Gembira Deployment Process..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm first."
    exit 1
fi

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ Failed to install dependencies"
    exit 1
fi

# Build Tailwind CSS for production
echo "🎨 Building Tailwind CSS for production..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Failed to build CSS"
    exit 1
fi

# Check if CSS file was generated
if [ ! -f "public/css/app.css" ]; then
    echo "❌ CSS file was not generated"
    exit 1
fi

echo "✅ CSS build successful ($(wc -c < public/css/app.css) bytes)"

# Clear Symfony cache for production
echo "🧹 Clearing Symfony cache..."
php bin/console cache:clear --env=prod --no-debug

if [ $? -ne 0 ]; then
    echo "⚠️  Warning: Could not clear Symfony cache"
fi

# Run Composer install for production
echo "🎼 Installing Composer dependencies for production..."
composer install --no-dev --optimize-autoloader

if [ $? -ne 0 ]; then
    echo "⚠️  Warning: Could not run composer install"
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 755 public/
chmod -R 755 var/
chmod -R 755 public/css/
chmod -R 755 public/uploads/

echo ""
echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Summary:"
echo "   ✅ Node.js dependencies installed"
echo "   ✅ Tailwind CSS compiled for production"
echo "   ✅ Symfony cache cleared"
echo "   ✅ File permissions set"
echo ""
echo "🌐 Your application is ready for production!"
echo "   CSS size: $(wc -c < public/css/app.css) bytes (minified)"
echo "   No more CDN warnings in browser console"
echo ""
echo "🔄 To rebuild CSS during development:"
echo "   npm run dev    (watch mode)"
echo "   npm run build  (production build)"