# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that routes all emails through Amazon SES. The repository has a dual structure:
- **Root directory**: Development environment with build tools and CI/CD
- **`fullworks-simple-setup-for-amazon-ses/` subdirectory**: The actual WordPress plugin code

## Essential Commands

### Development Setup
```bash
# Install all dependencies
composer install && npm install && cd fullworks-simple-setup-for-amazon-ses && composer install && cd ..

# Start local WordPress environment (http://localhost:8414)
npm run env:start

# Run code quality checks
composer run-script phpcs-security  # Security-focused checks only
composer run-script phpcompat       # PHP 8.2+ compatibility check

# Build for release (creates zipped/fullworks-simple-setup-for-amazon-ses.zip)
composer run-script build
```

### Before Creating a Release
1. Ensure version consistency across:
   - `fullworks-simple-setup-for-amazon-ses/fullworks-simple-setup-for-amazon-ses.php` (Version header)
   - `fullworks-simple-setup-for-amazon-ses/fullworks-simple-setup-for-amazon-ses.php` (FSSFAS_VERSION constant)
   - `fullworks-simple-setup-for-amazon-ses/readme.txt` (Stable tag)
2. Create a git tag matching the version (e.g., `v1.0.0`)
3. Push the tag to trigger automatic GitHub release

## Architecture

### Plugin Structure
The plugin uses PSR-4 autoloading with namespace `Fullworks\SimpleSetupForAmazonSes\`:
- `Plugin.php` - Singleton entry point, initializes admin and email handler
- `Admin/SettingsPage.php` - WordPress settings API integration, handles AWS credentials
- `Credentials.php` - Resolves AWS connection settings from constants then DB options
- `Redirect.php` - Resolves staging email-redirect settings (mode + catch-all addresses) from constants then DB options
- `Email/MailHandler.php` - Intercepts WordPress emails via `pre_wp_mail` filter; applies redirect when `Redirect::isActive()`
- `Email/SesSender.php` - AWS SES integration using `sendRawEmail` for full email flexibility

### Key Design Decisions
1. **Email Interception**: Uses `pre_wp_mail` filter (WordPress 5.7+) for clean interception
2. **AWS Integration**: Uses `sendRawEmail` instead of `sendEmail` for attachment support and full email control
3. **Fallback Behavior**: Returns `null` from filter to allow WordPress default mail on SES failure
4. **Settings Storage**: AWS credentials stored in WordPress options as `fssfas_settings`
5. **Staging Redirect**: When `Redirect::isActive()`, `MailHandler` passes a catch-all list to `SesSender`, which rewrites `To` to the catch-all, drops Cc/Bcc from delivery, preserves originals in `X-Original-*` headers, and prefixes the subject. The redirect decision lives in `MailHandler`, NOT `SesSender`, so the admin test-email path (which calls `SesSender::send()` directly without the `$redirect_to` argument) is never redirected. Active redirect shows an admin banner and logs each send.

### Naming Conventions
- **Slug / text domain**: `fullworks-simple-setup-for-amazon-ses`
- **Prefix**: `fssfas` (lowercase) / `FSSFAS` (uppercase) — used for option keys, hooks, AJAX actions, settings groups, sections, page IDs, constants
- **Namespace**: `Fullworks\SimpleSetupForAmazonSes\`
- **wp-config constants**: `FSSFAS_ACCESS_KEY_ID`, `FSSFAS_SECRET_ACCESS_KEY`, `FSSFAS_REGION`, `FSSFAS_REDIRECT_MODE`, `FSSFAS_REDIRECT_TO`

### AWS SES Implementation Details
- Builds complete MIME-formatted raw emails with proper headers
- Supports multipart messages for attachments
- Base64 encodes message bodies and attachments
- Automatically detects HTML vs plain text content
- Comprehensive error logging with AWS request IDs

## Important Notes

### GitHub Actions
- **checks.yml**: Runs on all pushes/PRs - validates code quality and compatibility
- **release.yml**: Triggered by version tags - builds and creates GitHub releases
- Both workflows require `wp-cli/dist-archive-command` package installation

### Development Constraints
- PHP 8.2+ required (driven by aws/aws-sdk-php ^3 minimum)
- Must maintain WordPress coding standards (PHPCS configured)
- Plugin must work in WordPress 5.0+ environment
- AWS credentials must have `ses:SendRawEmail` permission (NOT `ses:SendEmail`)

### Testing
Test emails can be sent from Settings > Fullworks SES using the AJAX-powered test button. Check WordPress debug.log for detailed AWS SES responses and errors.
