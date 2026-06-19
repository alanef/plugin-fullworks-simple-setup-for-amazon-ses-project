=== Fullworks Simple Setup for Amazon SES ===
Contributors: fullworks, alanfuller
Tags: email, aws, ses, smtp, amazon
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress emails through Amazon SES (Simple Email Service) with easy configuration.

== Description ==

Fullworks Simple Setup for Amazon SES replaces the default WordPress email function with Amazon SES, ensuring reliable email delivery for your WordPress site.

This plugin is an independent project and is not affiliated with, endorsed by, or sponsored by Amazon Web Services, Inc.

Features:
* Easy configuration through WordPress admin
* Optional credential configuration via wp-config.php constants (12-factor / env-var friendly)
* Supports all standard WordPress emails
* Test email functionality
* Credentials stored in the WordPress database, or defined as wp-config.php constants to keep them out of the database
* Support for HTML and plain text emails
* Staging-safe email redirect: reroute all outgoing mail to a catch-all address (always, or only on non-production environments) while still sending for real through SES

== Installation ==

1. Upload the `fullworks-simple-setup-for-amazon-ses` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Fullworks SES to configure your AWS credentials
4. Enter your AWS Access Key ID, Secret Access Key, and select your AWS region
5. Configure your sender email address (must be verified in AWS SES)
6. Send a test email to verify everything is working

== Configuration ==

Before using this plugin, you need to:

1. Have an AWS account
2. Set up Amazon SES in your preferred region
3. Verify your sender email address or domain in AWS SES
4. Create an IAM user with SES sending permissions
5. Generate Access Keys for the IAM user

== Defining Credentials in wp-config.php ==

Instead of storing AWS credentials in the database via the settings UI, you can define them as PHP constants in `wp-config.php`. This is the recommended approach for production sites and works well with environment variables on managed hosts.

Add the following before the `/* That's all, stop editing! */` line in `wp-config.php`:

`
define( 'FSSFAS_ACCESS_KEY_ID',     getenv( 'FSSFAS_ACCESS_KEY_ID' ) ?: '' );
define( 'FSSFAS_SECRET_ACCESS_KEY', getenv( 'FSSFAS_SECRET_ACCESS_KEY' ) ?: '' );
define( 'FSSFAS_REGION',            getenv( 'FSSFAS_REGION' ) ?: 'us-east-1' );
`

Each constant is independent — you can define one, two, or all three. Any constant that is defined takes precedence over the value saved in the settings page, and the matching field in the admin UI is locked while the constant is in effect.

== Email Redirect for Staging ==

When you copy a production site to a staging or development environment, real WordPress and WooCommerce emails (order confirmations, password resets, etc.) can be accidentally sent to real customers. The Email Redirect feature prevents this: every outgoing email is still sent for real through Amazon SES, but the recipients are rewritten to one or more catch-all addresses you control. You see exactly what would have been delivered, fully rendered, without any real recipient being contacted.

* The original To, Cc and Bcc are preserved in `X-Original-To`, `X-Original-Cc` and `X-Original-Bcc` headers, and the subject is prefixed with the original recipient (e.g. `[SES redirected → customer@example.com] Your order`).
* Cc and Bcc are dropped from actual delivery — only the catch-all address(es) receive mail.
* While redirect is active, a warning banner is shown in wp-admin and each redirected send is written to the error log, so trapped mail is never silent.
* The admin "Send Test Email" button always sends to the address you type, ignoring redirect.

Redirect Mode has three settings: Never (default), "Redirect when environment is not production" (uses the WordPress environment type, `WP_ENVIRONMENT_TYPE`), and Always.

Like credentials, the redirect settings can be defined as constants in `wp-config.php` so a staging environment can enforce them via environment variables:

`
define( 'FSSFAS_REDIRECT_MODE', getenv( 'FSSFAS_REDIRECT_MODE' ) ?: 'non_production' );
define( 'FSSFAS_REDIRECT_TO',   getenv( 'FSSFAS_REDIRECT_TO' )   ?: 'staging-mail@example.com' );
`

`FSSFAS_REDIRECT_MODE` accepts `never`, `non_production` or `always`. `FSSFAS_REDIRECT_TO` is a comma-separated list of catch-all addresses. When defined, the matching field in the admin UI is locked.

== External Services ==

This plugin sends your site's outgoing email through Amazon Simple Email Service (Amazon SES), a service provided by Amazon Web Services, Inc. When WordPress sends an email and the plugin has been configured with valid AWS credentials, the message is transmitted to Amazon SES instead of being delivered by your server's default mailer.

The following data is sent to Amazon SES for each email:

* The recipient address(es), and any Cc, Bcc and Reply-To addresses
* The sender name and address
* The email subject and message body
* Any file attachments included with the email
* Your AWS Access Key ID and the AWS region you select, used to authenticate the request

No data is sent to Amazon SES unless you have entered AWS credentials in the plugin settings (or defined them as `wp-config.php` constants). Without credentials the plugin does nothing and WordPress sends email using its normal method.

This service is provided by Amazon Web Services, Inc. Your use of it is governed by their terms and privacy policy:

* Amazon SES: https://aws.amazon.com/ses/
* AWS Service Terms: https://aws.amazon.com/service-terms/
* AWS Privacy Notice: https://aws.amazon.com/privacy/

== Frequently Asked Questions ==

= What permissions does my AWS IAM user need? =

Your IAM user needs the `ses:SendEmail` and `ses:SendRawEmail` permissions.

= What data does this plugin send to Amazon? =

See the "External Services" section above — in short, the contents of any email WordPress sends (recipients, sender, subject, body, attachments) plus your AWS Access Key ID and region for authentication.

= How do I stop a staging site from emailing real customers? =

Set Redirect Mode (under Settings > Fullworks SES) to "Always" or "Redirect when environment is not production" and enter one or more catch-all addresses in "Redirect To". All outgoing mail is then rerouted to those addresses while still being sent for real through SES, so you can verify it without any customer receiving it. See the "Email Redirect for Staging" section above. You can also enforce this from `wp-config.php` with the `FSSFAS_REDIRECT_MODE` and `FSSFAS_REDIRECT_TO` constants.

= My emails are not being sent. What should I check? =

1. Verify your AWS credentials are correct
2. Ensure your sender email is verified in AWS SES
3. Check if you're still in the SES sandbox (new accounts have sending limits)
4. Review your WordPress error logs for specific error messages

== Changelog ==

= 1.5.0 =
* Added staging-safe email redirect: reroute all outgoing mail to one or more catch-all addresses (Never / non-production / Always) while still sending for real through SES. Original recipients are preserved in `X-Original-To`/`X-Original-Cc`/`X-Original-Bcc` headers, Cc/Bcc are dropped from delivery, and the subject is prefixed with the original recipient.
* A warning banner is shown in wp-admin while redirect is active, and each redirected send is logged, so trapped mail is never silent.
* Redirect can be configured in the settings UI or via `wp-config.php` constants (`FSSFAS_REDIRECT_MODE`, `FSSFAS_REDIRECT_TO`).

= 1.4.0 =
* First WP org release

= 1.3.3 =
* Updated bundled `aws/aws-sdk-php` to the latest stable release.
* Updated tested to to wp7.0

= 1.3.2 =
* Updated bundled `aws/aws-sdk-php` to the latest stable release.
* Changed 6 escape late outputs to comply with reviewer recommendations.

= 1.3.1 =
* Updated bundled `aws/aws-sdk-php` to the latest stable release.
* Updated URI to correct path.

= 1.3.0 =
* Renamed plugin to "Fullworks Simple Setup for Amazon SES" (previously "Simple AWS SES"). Slug, namespace (`Fullworks\SimpleSetupForAmazonSes\`), prefix (`fssfas` / `FSSFAS`), option key (`fssfas_settings`), AJAX action and wp-config constants (`FSSFAS_ACCESS_KEY_ID`, `FSSFAS_SECRET_ACCESS_KEY`, `FSSFAS_REGION`) all updated.
* All admin UI strings, AJAX responses, and the test email body are now translatable via the `fullworks-simple-setup-for-amazon-ses` text domain.
* Security: outgoing messages are now assembled with WordPress' bundled PHPMailer, which validates addresses and strips line breaks from headers, closing a potential email header injection path when other plugins pass untrusted data to `wp_mail()`.
* The settings page JavaScript and CSS are now enqueued properly (`wp_enqueue_script` / `wp_localize_script`) instead of being printed inline.
* Added a privacy policy suggestion via `wp_add_privacy_policy_content()`, and an "External Services" section in the readme documenting exactly what data is sent to Amazon SES.
* Updated bundled `aws/aws-sdk-php` to the latest stable release and stripped the unused AWS service clients so the plugin download is dramatically smaller.

= 1.2.1 =
* Test email button now sends via AWS SES directly instead of `wp_mail()`, so it no longer reports success when SES has actually failed and WordPress fell back to the default mailer.
* AWS errors (code, message, request ID) are now surfaced in the test email response and written to `debug.log` when `WP_DEBUG` is enabled. SES failures during normal `wp_mail()` flow are also logged before the WordPress default mailer takes over.

= 1.2.0 =
* Added support for defining AWS credentials via PHP constants in wp-config.php (`FSSFAS_ACCESS_KEY_ID`, `FSSFAS_SECRET_ACCESS_KEY`, `FSSFAS_REGION`). When a constant is defined, the matching field in the settings UI is locked.

= 1.1.0 =
* Fixed HTML email detection - emails with common HTML tags are now correctly sent as text/html

= 1.0.0 =
* Initial release
