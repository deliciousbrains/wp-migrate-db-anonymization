=== WP Migrate DB Anonymization ===
Contributors: deliciousbrains
Tags: gdpr, anonymization, anonymize, anonymizer, anonymise, migrate, migration, export, data dump, backup, database, mysql
Requires at least: 3.6
Tested up to: 5.2
Stable tag: 0.3.4
Requires PHP: 5.3.3
License: GPLv3

Addon for WP Migrate DB and WP Migrate DB Pro to anonymize user data on database export, pull or push.

== Description ==

This addon requires [WP Migrate DB Pro](https://deliciousbrains.com/wp-migrate-db-pro) or [WP Migrate DB](https://wordpress.org/plugins/wp-migrate-db/).

Ensure no user personal data is included when a database is exported, pulled or pushed using [WP Migrate DB](https://wordpress.org/plugins/wp-migrate-db/) (export) or [WP Migrate DB Pro](https://deliciousbrains.com/wp-migrate-db-pro) (export, pull, push).

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

== Installation ==

1. Use WordPress' built-in installer
2. Activate plugin

== Changelog ==

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