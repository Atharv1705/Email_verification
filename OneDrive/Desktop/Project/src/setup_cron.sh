#!/bin/bash

CRON_FILE="mycronjob"
PHP_PATH=$(which php)
CRON_JOB="*/5 * * * * $PHP_PATH $(pwd)/cron.php > /dev/null 2>&1"

# Write current crontab to temp file
crontab -l > $CRON_FILE 2>/dev/null

# Add new job if not already present
if ! grep -Fq "$CRON_JOB" $CRON_FILE; then
  echo "$CRON_JOB" >> $CRON_FILE
  crontab $CRON_FILE
  echo "Cron job added to run every 5 minutes."
else
  echo "Cron job already exists."
fi

rm $CRON_FILE
