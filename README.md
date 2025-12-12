# Automatic End for ACF Listings

**Author:** John Lim  
**Version:** 1.0.0  
**Requires PHP:** 7.4+  
**Requires WordPress:** 5.8+  

## Description

This plugin automates the status management of your 'listing' custom post types. It runs a daily scheduled job to check the **ACF Auction Start Date** of your listings. If a listing's start date matches the current date, its status in the `listing-status` taxonomy is automatically changed from **active** to **ended**.

## Features

*   **Automated Status Updates**: Moves listings from 'active' to 'ended' automatically.
*   **Precision Scheduling**: Configurable daily execution time via WordPress Settings API.
*   **Smart Querying**: Efficiently targets only relevant listings using `WP_Query`.
*   **Logging**: Basic error logging to tracking the start and completion of the automation job.

## Requirements

*   **Custom Post Type**: `listing`
*   **Taxonomy**: `listing-status` (with terms `active` and `ended`)
*   **ACF Field**: `auction_start_date` (Date format: `Ymd`)

## Installation

1.  Upload the plugin folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Auto End Listings** to configure the daily execution time.

## Configuration

### Execution Time
You can set the exact time of day you want the automation to run.
1.  Navigate to **Settings > Auto End Listings**.
2.  Enter the time in the **Daily Execution Time** field.
3.  Save Changes.

The scheduler uses your WordPress server time.

## Verification

To verify the job plays out as expected, you can use the **WP Crontrol** plugin:
1.  Go to **Tools > Cron Events**.
2.  Find `aeal_daily_event`.
3.  Click **Run Now** to force a check immediately.

## Changelog

### 1.0.0
*   Initial release.
*   Implemented settings page, cron scheduling, and automation logic.