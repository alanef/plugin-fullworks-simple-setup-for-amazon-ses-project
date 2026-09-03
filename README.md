# Fullworks Simple Setup for Amazon SES — WordPress Plugin

A WordPress plugin that routes all emails through Amazon SES (Simple Email Service) for reliable email delivery.

This plugin is an independent project and is not affiliated with, endorsed by, or sponsored by Amazon Web Services, Inc.

## Overview

This repository contains the development environment for the Fullworks Simple Setup for Amazon SES WordPress plugin. The actual plugin code is located in the `fullworks-simple-setup-for-amazon-ses` subdirectory.

## Features

- Easy AWS credential configuration through WordPress admin
- Automatic interception of all WordPress emails
- Support for HTML and plain text emails
- Test email functionality
- Secure credential storage
- Fallback to default mail if SES fails

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm (for development tools)
- WP-CLI (for building releases)

### Installation

1. Clone the repository:
```bash
git clone https://github.com/alanef/plugin-fullworks-simple-setup-for-amazon-ses-project.git
cd plugin-fullworks-simple-setup-for-amazon-ses-project
```

2. Install development dependencies:
```bash
composer install
npm install
```

3. Install plugin dependencies:
```bash
cd fullworks-simple-setup-for-amazon-ses
composer install
cd ..
```

### Local Development

Start the WordPress development environment:
```bash
npm run env:start
```

The plugin will be available at `http://localhost:8414`

Stop the environment:
```bash
npm run env:stop
```

## Building for Release

Build a distributable version of the plugin:
```bash
composer run-script build
```

This creates `zipped/fullworks-simple-setup-for-amazon-ses.zip` with production dependencies only.

## Code Quality

### PHP Code Standards

Check code against WordPress coding standards:
```bash
composer run-script phpcs
```

Fix auto-fixable issues:
```bash
composer run-script phpcs-fix
```

### PHP Compatibility

Check compatibility with PHP 8.2+:
```bash
composer run-script phpcompat
```

## GitHub Actions

The project includes automated workflows:

- **Quality Checks** - Runs on all pushes and PRs:
  - Validates composer.json
  - Checks coding standards
  - Verifies PHP compatibility
  - Ensures version consistency
  - Runs WordPress plugin checks

- **Release** - Triggered by version tags (e.g., `v1.0.0`):
  - Runs all quality checks
  - Builds production version
  - Creates GitHub release with zip file

## AWS Configuration

### Prerequisites

1. AWS account with SES access
2. Verified sender email address or domain in SES
3. IAM user with SES permissions

### Required IAM Permissions

Your IAM user needs the following permissions. You can either use the AWS managed policy `AmazonSESSendingAccess` or create a custom policy:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "*"
        }
    ]
}
```

**Important**: If you're getting authorization errors, ensure:
1. Your IAM user has the correct permissions (check IAM console)
2. The sending email address is verified in SES
3. You're using the correct AWS region where your email is verified
4. If in SES sandbox mode, recipient emails must also be verified

### Plugin Configuration

1. Install and activate the plugin
2. Go to Settings > Fullworks SES
3. Enter your AWS credentials:
   - Access Key ID
   - Secret Access Key
   - Select your AWS region
4. Configure sender information
5. Send a test email to verify setup

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Ensure all tests pass
5. Submit a pull request

## License

GPL v2 or later. See LICENSE file for details.

<!-- tooling:start (managed by wordpress-plugin-boilerplate/tooling - do not edit by hand) -->
## Development

This repository uses the standard Fullworks free-plugin tooling, documented in
[wordpress-plugin-boilerplate](https://github.com/alanef/wordpress-plugin-boilerplate/blob/main/CLAUDE.md).

[![Plugin Check](https://github.com/alanef/plugin-fullworks-simple-setup-for-amazon-ses-project/actions/workflows/checks.yml/badge.svg)](https://github.com/alanef/plugin-fullworks-simple-setup-for-amazon-ses-project/actions/workflows/checks.yml)

```
plugin-fullworks-simple-setup-for-amazon-ses-project/                     # repository root: development tooling
├── .github/workflows/             # checks.yml on push/PR, release.yml on tag
├── tests/                         # PHPUnit suite, run inside wp-env
├── .wp-env.json                   # dev :8414, tests :8415
├── composer.json                  # dev dependencies and quality scripts
├── package.json                   # wp-env and test scripts
├── phpunit.xml.dist / run-tests.sh
└── fullworks-simple-setup-for-amazon-ses/                # the plugin (shipped as-is via .distignore)
```

```bash
composer install && npm install        # dev tools
npm run start                          # http://localhost:8414  (admin / password)
composer run check                     # PHPCompatibility + security sniffs
npm test                               # PHPUnit in the wp-env tests container
composer run build                     # zipped/fullworks-simple-setup-for-amazon-ses-free.zip
```

Releases: set the version in the plugin header and `readme.txt`, update `CHANGELOG.md`,
tag `vX.Y.Z` and push. CI builds the zip, creates the GitHub release and deploys to
WordPress.org.
<!-- tooling:end -->
