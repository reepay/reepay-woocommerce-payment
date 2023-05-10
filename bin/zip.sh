#!/usr/bin/env bash

# Exit if any command fails.
set -ex

# Change to the expected directory.
DIR=$(pwd)
BUILD_DIR="$DIR/build/reepay-woocommerce-payment"

# Enable nicer messaging for build status.
BLUE_BOLD='\033[1;34m';
GREEN_BOLD='\033[1;32m';
RED_BOLD='\033[1;31m';
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';
error () {
    echo -e "\n${RED_BOLD}$1${COLOR_RESET}\n"
}
status () {
    echo -e "\n${BLUE_BOLD}$1${COLOR_RESET}\n"
}
success () {
    echo -e "\n${GREEN_BOLD}$1${COLOR_RESET}\n"
}
warning () {
    echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}

status "💃 Time to build 🕺"

# remove the build directory if exists and create one
rm -rf "$DIR/build"
mkdir -p "$BUILD_DIR"

# Install npm dependencies.
if [ ! -d "./node_modules" ];
then
  status "Installing npm dependencies... 📦"
  npm ci  --ignore-scripts
fi

# Install composer no-dev dependencies.
status "Installing composer no-dev dependencies... 📦"
composer install --optimize-autoloader --no-dev -q

status "Generating build... 👷‍♀️"
npm run build

# Copy all files
status "Copying files... ✌️"
FILES=(includes languages templates updates vendor Readme.txt reepay-woocommerce-payment.php)

for file in ${FILES[@]}; do
  cp -R $file $BUILD_DIR
done

mkdir -p "$BUILD_DIR/assets/dist"
cp -R assets/dist "$BUILD_DIR/assets"
cp -R assets/images "$BUILD_DIR/assets"

# go one up, to the build dir
if command -v zip
then
  status "Creating archive... 🎁"

  cd ./build
  zip -r -q reepay-woocommerce-payment.zip reepay-woocommerce-payment

  # remove the source directory
#  rm -rf ./reepay-woocommerce-payment
else
  warning "zip command not found. Create archive by yourself ./build/reepay-woocommerce-payment"
fi

success "Done. You've built plugin! 🎉 "