=== WP Migrate DB Anonymization ===
Contributors: deliciousbrains
Tags: gdpr, anonymization, anonymize, anonymizer, anonymise, migrate, migration, export, data dump, backup, database, mysql
Requires at least: 3.6
Tested up to: 4.9.5
Stable tag: 0.1
Requires PHP: 5.3.3
License: GPLv3

Integrates with WP Migrate DB and WP Migrate DB Pro to anonymize user data on database export, pull or push.

== Description ==

Ensure no user personal data is included when a database is exported, pulled or pushed using WP Migrate DB (export) or WP Migrate DB Pro (export, pull, push).

User data is anonymized and includes data from the following tables:

- users
- usermeta

Supported plugins:

- WooCommerce

To preserve specific rows in the users table, use the `WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST` constant to set a whitelist of comma separated user logins.

To replace all passwords with a hashed default password, set the password using the `WPMDB_ANONYMIZATION_DEFAULT_PASSWORD` constant.

== Installation ==

1. Use WordPress' built-in installer
2. Activate plugin

== Changelog ==

= 0.1 - 2018-05-14 =

* Initial release