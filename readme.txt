=== WP Migrate DB Anonymization ===
Contributors: deliciousbrains
Tags: gdpr, anonymization, anonymize, anonymizer, anonymise, migrate, migration, export, data dump, backup, database, mysql
Requires at least: 5.9
Tested up to: 6.8
Stable tag: 0.3.4
Requires PHP: 7.4
License: GPLv3

Addon for WP Migrate DB and WP Migrate DB Pro to anonymize user data on database export, pull or push.

== Description ==

This addon requires [WP Migrate DB Pro](https://deliciousbrains.com/wp-migrate-db-pro) or [WP Migrate DB](https://wordpress.org/plugins/wp-migrate-db/).

Ensure no user personal data is included when a database is exported, pulled or pushed using [WP Migrate DB](https://wordpress.org/plugins/wp-migrate-db/) (export) or [WP Migrate DB Pro](https://deliciousbrains.com/wp-migrate-db-pro) (export, pull, push).

The addon should be installed on the live (source) site. This ensures any time the site's database is exported, pushed or pulled, the user data is anonymized. A notice is shown on the Plugins and WP Migrate screens as a reminder, tailored to the site's environment type.

When the plugin is activated it will anonymize by default. You will need to deactivate the plugin to turn it off.

User data is anonymized and includes data from the following tables:

- users
- usermeta
- comments

Supported plugins:

- WooCommerce

To preserve specific rows in the users table, use the `WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST` constant to set a whitelist of comma separated user logins.

To replace all passwords with a hashed default password, set the password using the `WPMDB_ANONYMIZATION_DEFAULT_PASSWORD` constant.

The configuration rules can be extended with a filter. [Learn how](https://github.com/deliciousbrains/wp-migrate-db-anonymization#extending).

= WP-CLI =

Anonymize the current database in place with `wp migrate-anonymize run`, using the same rules. WP Migrate doesn't need to be active, so it works on a database restored from a backup. It permanently overwrites data, so only run it on a copy of a database. Use `--dry-run` to see what would change, `--tables=users,usermeta` to limit the tables, `--rules=/path/private-rules.php` to add private rules from a file in the same format as the bundled ones, `--prefix=shop_` to process tables with another prefix (`--prefix=''` for table names exactly as in the rules) and `--yes` to skip the confirmation. It refuses to run when the environment type is `production` unless you pass `--force`. [Learn more](https://github.com/deliciousbrains/wp-migrate-db-anonymization#wp-cli).

= Deterministic anonymization =

Set a secret with the `WPMDB_ANONYMIZATION_SECRET` constant, the `wpmdb_anonymization_secret` filter or the `--secret` CLI option, and the same original value always becomes the same fake value across tables, rows and runs, so joins and distinct counts still work on the anonymized data. Fake email addresses, user names, domain names and URLs include a hash fragment so they don't collide. Without a secret they get a random fragment in the same format, so they are unique either way. Keep the secret private: anyone with it can test guesses against the fake values. [Learn more](https://github.com/deliciousbrains/wp-migrate-db-anonymization#deterministic-anonymization).

== Installation ==

1. Use WordPress' built-in installer
2. Activate plugin

== Changelog ==

= Unreleased =

* New: Admin notice on the Plugins and WP Migrate screens explaining that the addon belongs on the live (source) site, tailored to the site's environment type
* Improvement: Replace the abandoned fzaninotto/faker library with its maintained fork, fakerphp/faker
* Improvement: Require PHP 7.4+ and WordPress 5.9+, tested up to WordPress 6.8
* Improvement: PHP 8.x compatibility
* Bug Fix: Fatal error instead of a notice when the vendor directory is missing
* Improvement: Anonymize WooCommerce HPOS orders, customer lookup, payment tokens, downloads and sessions, Easy Digital Downloads 3 customers, orders and logs, and comment user agents; only load a plugin's rules when its data is in the database (#27); match multisite subsite tables (#28); support list and regex column constraints (#19)
* Improvement: Anonymize pending email changes and signup IP addresses in user meta; WooCommerce order attribution user agents, VAT numbers, PayPal Express and Stripe card IDs, PayPal token emails, coupon email restrictions and back in stock sign-ups; Easy Digital Downloads customer and subscription notes, license activation sites, EDD Invoices details, PayPal Express payer IDs and EDD 2.x addresses and log IP addresses
* New: `wp migrate-anonymize run` WP-CLI command to anonymize a database in place, without WP Migrate
* New: `--rules` and `--prefix` options for `wp migrate-anonymize run`, and a `wpmdb_anonymization_config_files` filter, to anonymize with private rule files and tables under another prefix or none
* New: Deterministic anonymization with a secret, keeping fake values consistent and unique across tables; fake email addresses, user names, domain names, URLs and hash-shaped values (md5, sha1, sha256, uuid) are also unique without a secret (#29)
* Improvement: Anonymize Gravity Forms entries, notes and saved drafts and Redirection logs, and replace API keys, tokens and secrets (WordPress application passwords, Two Factor, WooCommerce, Easy Digital Downloads and others) with random values of the same shape and disable WooCommerce webhooks; credentials can be kept with the `wpmdb_anonymization_load_config_file` filter

= 0.3.4 - 2019-05-21 =

* Improvement: Don't replace empty data with fake data

= 0.3.3 - 2018-11-21 =

* Bug Fix: Compatibility with WP Migrate DB 1.0.6

= 0.3.2 - 2018-09-11 =

* Improvement: Pass WP_User instance to whitelist user filter for greater flexibility

= 0.3.1 - 2018-07-18 =

* Bug Fix: Anonymization not running for CLI commands

= 0.3 - 2018-07-03 =

* Improvement: Support for Faker data method arguments defined in the configuration rules

= 0.2 - 2018-06-22 =

* New: Filter for whitelisting users to stop anonymization - `wpmdb_anonymization_user_whitelisted`
* Bug Fix: - Comments not anonymized
* Bug Fix: - Order data in post meta not anonymized
* Bug Fix: - Customer IP and PayPal data not anonymized

= 0.1 - 2018-05-22 =

* Initial release