#!/bin/sh
# Check if the artisan file exists
if [ -f "$APP_BASE_DIR/artisan" ]; then
  # Run the custom artisan command
  php "$APP_BASE_DIR/artisan" app:enable-services-on-boot
else
  # If the artisan file is not found, stop the container
  echo "❌ Artisan file not found in $APP_BASE_DIR"
  exit 1
fi

# Exit with a success code
exit 0
