#!/bin/bash

# ByFly Payment Center - Build & Package Script
# This script builds the frontend and packages everything for deployment

echo "======================================"
echo "ByFly Payment Center - Build Script"
echo "======================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}Error: npm is not installed${NC}"
    exit 1
fi

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    npm install
    if [ $? -ne 0 ]; then
        echo -e "${RED}Failed to install dependencies${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Dependencies installed${NC}"
    echo ""
fi

# Build frontend
echo -e "${YELLOW}Building frontend...${NC}"
npm run build
if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to build frontend${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Frontend built successfully${NC}"
echo ""

# Create deployment package
echo -e "${YELLOW}Creating deployment package...${NC}"

# Create temp directory for packaging
TEMP_DIR="byfly-payment-center-deploy"
rm -rf $TEMP_DIR
mkdir -p $TEMP_DIR

# Copy dist files (frontend)
echo "  - Copying frontend files..."
cp -r dist/* $TEMP_DIR/

# Copy API files
echo "  - Copying API files..."
mkdir -p $TEMP_DIR/api
cp -r api/* $TEMP_DIR/api/

# Copy database schema
echo "  - Copying database schema..."
mkdir -p $TEMP_DIR/database
cp database/schema.sql $TEMP_DIR/database/
cp database/migration-client-info.sql $TEMP_DIR/database/ 2>/dev/null || true
cp database/fix-payment-methods.sql $TEMP_DIR/database/ 2>/dev/null || true

# Copy .htaccess
echo "  - Copying .htaccess..."
cp .htaccess $TEMP_DIR/

# Copy uploads directory
echo "  - Copying uploads directory..."
mkdir -p $TEMP_DIR/uploads
cp -r uploads/* $TEMP_DIR/uploads/ 2>/dev/null || true

# Copy test files to root (not in dist)
echo "  - Copying test files..."
cp test-api.html $TEMP_DIR/
cp test-payment-methods-api.html $TEMP_DIR/
cp diagnose-payment-page.html $TEMP_DIR/
cp check-version.html $TEMP_DIR/
cp test-approve-payment.html $TEMP_DIR/

# Copy README
echo "  - Copying README..."
cp README.md $TEMP_DIR/

# Create archive with standard name
ARCHIVE_NAME="deploy.zip"

# Remove old archive if exists
if [ -f "../$ARCHIVE_NAME" ]; then
    rm "../$ARCHIVE_NAME"
fi

echo "  - Creating archive: $ARCHIVE_NAME"

if command -v zip &> /dev/null; then
    cd $TEMP_DIR
    zip -r ../$ARCHIVE_NAME . > /dev/null
    cd ..
else
    echo -e "${RED}Error: zip command not found${NC}"
    exit 1
fi

# Cleanup
rm -rf $TEMP_DIR

echo ""
echo -e "${GREEN}✓ Build completed successfully!${NC}"
echo ""
echo "Deployment package: $ARCHIVE_NAME"
echo ""
echo "Next steps:"
echo "1. Upload $ARCHIVE_NAME to your server"
echo "2. Extract the archive in your web root directory"
echo "3. Run: php api/init.php (to initialize database)"
echo "4. Configure your web server to point to the extracted directory"
echo "5. Access admin panel at: https://byfly-pay.com/"
echo ""
