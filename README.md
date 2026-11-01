WP Migrate DB Anonymization Addon
==========================================

## Requirements

- PHP 7.4+
- WordPress 5.9+
- [WP Migrate](https://deliciousbrains.com/wp-migrate-db-pro/) or [WP Migrate Lite](https://wordpress.org/plugins/wp-migrate-db/)

## Installation

If you clone from this repository you will need to run `composer install` to complete the install of the plugin. The `vendor` directory is packaged with the plugin on the WordPress plugin repository:
https://wordpress.org/plugins/wp-migrate-db-anonymization/#developers 

The addon should be installed on the live site. This ensures any time the site's database is exported, pushed or pulled, the user data is anonymized.

When the plugin is activated it will anonymize by default. You will need to deactivate the plugin to turn it off.

## Configuration

To preserve specific rows in the users table, use the `WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST` constant to set a whitelist of comma separated user logins.

To replace all passwords with a hashed default password, set the password using the `WPMDB_ANONYMIZATION_DEFAULT_PASSWORD` constant.

These constants should be defined in the site's wp-config.php file, for example:

    define( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST', 'somelogin, anotherloginname' );
    define( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD', 'password123' );
    
You can also programmatically control which users are whitelisted with the following filter, added to a plugin or mu-plugin file:

    /**
     * @param bool     $whitelisted
     * @param \WP_User $user
     *
     * @return bool
     */
    function my_wpmdb_anonymization_user_whitelisted( $whitelisted, $user )  {
        if ( false !== strrpos( $user->user_email, '@somedomain.com' ) ) {
            // All users with the login containing an email address of somedomain.com are whitelisted
            return true;
        }
        
        return $whitelisted;
    }
    add_filter( 'wpmdb_anonymization_user_whitelisted', 'my_wpmdb_anonymization_user_whitelisted', 10, 2 );
    
## Covered data

The anonymization rules live in the `config` directory, one file per plugin. Order totals, dates, statuses, product data and IDs are never changed, so reports run on an anonymized copy still add up.

- `wordpress.php` (always loaded)
    - `users`: login, password, nicename, email, URL and display name
    - `usermeta`: first name, last name, nickname, description, `session_tokens` and `community-events-location` (both store IP addresses), `_new_email` (pending email change) and `signup_ip` (registration IP written by membership and registration plugins)
    - `comments`: author, email, URL, IP address and user agent
- `woocommerce.php`, including WooCommerce Subscriptions, which stores subscriptions as orders
    - `usermeta`: customer billing and shipping details, VAT number, order attribution user agent, Stripe, WooPayments and PayPal Express customer IDs and PayPal billing agreement
    - `postmeta` and `wc_orders_meta`: order billing and shipping details, address search index, customer IP address and user agent (including order attribution), VAT number and VAT location evidence (YITH WooCommerce EU VAT), Stripe customer and card IDs, PayPal Express customer and payer IDs, PayPal Standard payer details
    - `postmeta`: coupon email restrictions, replaced with a single fake address so the coupon stays restricted
    - `wc_orders`: billing email, IP address, user agent and customer note
    - `wc_order_addresses`: name, company, address, email and phone
    - `wc_customer_lookup`: username, name, email, location and phone numbers
    - `woocommerce_payment_tokens` and `woocommerce_payment_tokenmeta`: gateway token, card last 4 digits and expiry, PayPal email
    - `woocommerce_downloadable_product_permissions`: customer email
    - `wc_download_log`: IP address
    - `woocommerce_sessions`: replaced with an empty session
    - `wc_stock_notifications`: back in stock sign-up email
    - `wc_email_unsubscribes`: email hash (WooCommerce 11.0 and 11.1), replaced with a random hash
    - `comments`: content of order notes
- `easy-digital-downloads.php` (EDD 3.x, plus EDD 2.x data left after upgrading)
    - `edd_customers`, `edd_customer_email_addresses` and `edd_customer_addresses`: name, email, address and EDD 2.x customer notes
    - `edd_orders` and `edd_order_addresses`: email, IP address, name and address
    - `edd_customermeta` and `edd_ordermeta`: company, phone, last login IP, additional emails, Stripe and PayPal customer IDs, PayPal payer email, PayPal Express payer ID, EDD Invoices company and VAT number
    - `edd_notes`: note content
    - `edd_payment_tokens`: gateway token and customer ID, label (e.g. PayPal email or card last 4 digits), replaced with a generic label, and payment source details, replaced with an empty JSON object
    - `edd_logs_api_requests`, `edd_logs_file_downloads` and `edd_logs_emails`: IP address, user agent and email
    - `edd_subscriptions` (Recurring Payments): notes
    - `edd_license_activations` (Software Licensing): site URL
    - `usermeta` and `postmeta`: Stripe customer IDs, PayPal Express payer ID, EDD 2.x customer address, payment email and IP address, and file download and API request log IP addresses

Table names are given without the table prefix. On multisite, subsite tables such as `wp_2_postmeta` use the same rules as `wp_postmeta`.

A plugin's rules are only loaded if its data is in the database, whether or not the plugin is active: WooCommerce if a `woocommerce_order_items`, `wc_orders` or `wc_customer_lookup` table exists for any site, or customer billing details remain in `usermeta`; Easy Digital Downloads if an `edd_customers` or `edd_orders` table exists. Sites without these plugins don't spend time checking their rules during a migration. The rules are loaded, and the `wpmdb_anonymization_config` filter applied, the first time they are needed during a migration rather than on every request.

To force a plugin's rules on or off, use the `wpmdb_anonymization_load_config_file` filter:

    /**
     * @param bool   $load Whether the plugin's data was found in the database.
     * @param string $name Config file name without extension, e.g. 'woocommerce'.
     *
     * @return bool
     */
    function my_wpmdb_anonymization_load_config_file( $load, $name ) {
        if ( 'easy-digital-downloads' === $name ) {
            return true;
        }

        return $load;
    }
    add_filter( 'wpmdb_anonymization_load_config_file', 'my_wpmdb_anonymization_load_config_file', 10, 2 );

### Column constraints

A rule's `constraint` can limit it to rows where another column has a particular value. Besides an exact value, it accepts a list of values or a regular expression (including delimiters):

    // Exact value
    'constraint' => array( 'meta_key' => 'billing_phone' ),

    // Any of these values
    'constraint' => array( 'meta_key' => array( 'in' => array( 'billing_phone', 'shipping_phone' ) ) ),

    // Shorthand for 'in'
    'constraint' => array( 'meta_key' => array( 'billing_phone', 'shipping_phone' ) ),

    // Regular expression, e.g. tel-1, tel-110, tel-211
    'constraint' => array( 'field_name' => array( 'regex' => '/^tel-\d+$/' ) ),

If a constraint lists several columns, all of them must match.

## WP-CLI

The `wp migrate-anonymize run` command anonymizes the current database in place, using the same rules as exports, pushes and pulls. WP Migrate doesn't need to be active, so you can use it on a database restored from a backup, straight after importing it locally.

**This permanently overwrites data. Only run it on a copy of a database, never on a live site.**

    # Report how many rows would change, without writing anything
    wp migrate-anonymize run --dry-run

    # Anonymize every configured table
    wp migrate-anonymize run --yes

    # Only some tables, with a secret for deterministic output (see below)
    wp migrate-anonymize run --tables=users,usermeta --secret="$ANONYMIZATION_SECRET" --yes

    # Add private rules kept outside the plugin (see "Private rule files" below)
    wp migrate-anonymize run --rules=/path/private-rules.php --yes

    # A second WordPress install in the same database, using the shop_ prefix
    wp migrate-anonymize run --prefix=shop_ --yes

    # Unprefixed tables, named exactly as in the rules, e.g. my_backup_table
    wp migrate-anonymize run --prefix='' --rules=/path/private-rules.php --tables=my_backup_table --yes

Options:

- `--tables=<tables>`: comma-separated config tables, without the table prefix (`users`, `usermeta`, `comments`, `postmeta`, plus any added with the `wpmdb_anonymization_config` filter or a rules file). Defaults to all of them.
- `--prefix=<prefix>`: process the tables with this prefix instead of the site's `$table_prefix`, for example a second WordPress install in the same database or plugin tables without the WordPress prefix. `--prefix=''` means no prefix: table names are used exactly as written in the rules, so a rule for `my_backup_table` processes the `my_backup_table` table. The prefix may only contain letters, digits and underscores. It is treated as a single site: multisite subsites aren't processed, and the global `users` and `usermeta` tables use this prefix too. The checks for which plugins' data exists (e.g. WooCommerce tables) also use it.
- `--rules=<files>`: comma-separated paths to extra [rule files](#private-rule-files), added to the bundled rules for this run. The command stops with an error if a file doesn't exist or doesn't return an array.
- `--secret=<secret>`: enables [deterministic anonymization](#deterministic-anonymization). Takes precedence over the constant and filter.
- `--batch-size=<number>`: rows fetched per query. Default `1000`.
- `--dry-run`: count the rows that would change and write nothing.
- `--yes`: skip the confirmation prompt.
- `--force`: allow running when `wp_get_environment_type()` is `production`. Without it the command refuses.

Example output:

    Deterministic mode: the same original value always becomes the same fake value.
    Anonymizing wp_usermeta  100% [24/24]
    Anonymizing wp_postmeta  100% [13/13]
    Anonymizing wp_users     100% [4/4]
    Anonymizing wp_comments  100% [2/2]
    +-------------+---------+---------+--------+
    | table       | scanned | changed | errors |
    +-------------+---------+---------+--------+
    | wp_usermeta | 24      | 20      | 0      |
    | wp_postmeta | 13      | 13      | 0      |
    | wp_users    | 4       | 3       | 0      |
    | wp_comments | 2       | 2       | 0      |
    +-------------+---------+---------+--------+
    Success: Anonymized 38 row(s).

How it works:

- Rows are read in batches ordered by primary key and changed values are written back row by row. Tables without a single-column primary key are skipped with a warning.
- When every rule for a table is limited to specific values of a column, as with the `meta_key` rules for `usermeta` and `postmeta`, only those rows are read (`WHERE meta_key IN (...)`) instead of the whole table.
- The user whitelist constant and filter are respected, and empty values are left empty.
- On multisite, `users` and `usermeta` are processed once and the other tables are processed for every site, unless `--prefix` is given.
- The object cache is flushed afterwards.

Run it once per database. Running it again anonymizes the already fake values, which then no longer match values anonymized from the original data.

## Deterministic anonymization

By default every fake value is random, so the same email address becomes a different fake address in each table and row. That breaks joins and distinct counts on the anonymized data.

Fake email addresses, user names, domain names and URLs are unique in both modes, so columns with a unique index, such as a newsletter plugin's subscriber email or EDD Software Licensing's activation sites, don't break on large databases. Email addresses and user names look like `kshlerin.rosalind.3f9a0c1b2d4e@example.com`: a Faker user name, a 12-character hex fragment and, for email addresses, the reserved `example.com` domain. Domain names and URLs are subdomains of `example.com`, e.g. `schaden-3f9a0c1b2d4e.example.com` and `https://schaden-3f9a0c1b2d4e.example.com/`, so they never point at a real site. Without a secret the fragment is random (from `random_bytes()`); with one it comes from the HMAC described below. Other values, such as names and cities, are not made unique and can repeat in random mode, just as they do in real data.

Configure a secret to make anonymization deterministic, in wp-config.php:

    define( 'WPMDB_ANONYMIZATION_SECRET', 'a-long-random-string' );

or with the `--secret` option of the WP-CLI command, or with a filter, for example to read it from an environment variable:

    add_filter( 'wpmdb_anonymization_secret', function ( $secret ) {
        return getenv( 'WPMDB_ANONYMIZATION_SECRET' ) ?: $secret;
    } );

With a secret, each fake value is generated by seeding Faker from an HMAC-SHA256 of the original value, keyed by the secret. This applies to WP-CLI runs and to WP Migrate exports, pushes and pulls:

- The same original value always becomes the same fake value, in every table, row and run that uses the same secret. For example, a customer's email address in `users.user_email`, the `billing_email` user meta and the `_billing_email` order meta all become the same fake address, so joins and `COUNT(DISTINCT ...)` give the same answers as on the original data.
- Email addresses, user names and domain names are trimmed and lower-cased first, so `Alice@Example.org` and `alice@example.org` map to the same fake value. Other values are only trimmed.
- Fake email addresses, user names, domain names and URLs include a 12-character fragment of the HMAC so different originals don't collide, e.g. `kshlerin.rosalind.3f9a0c1b2d4e@example.com`. Fake email addresses always use the reserved `example.com` domain.
- Empty values stay empty. Passwords are never deterministic.

**Keep the secret private.** Use a long random value, don't commit it to version control and don't share it with the anonymized database. Anyone who has the secret can test guesses: they can anonymize a candidate value, such as a known email address, and check whether the result appears in the data. Without the secret, the fake values can't be linked back to the originals.

The fake values come from Faker, so upgrading Faker or changing its locale can change the readable part of a value (the hash fragment in email addresses, user names, domain names and URLs stays the same). To join or compare datasets, anonymize them with the same secret and plugin version. Faker formatters that are relative to the current date, such as `dateTimeThisYear`, will also change over time.

## Extending

The rules for anonymization can be extended using the `wpmdb_anonymization_config` filter, added to a plugin or mu-plugin file:

    /**
     * Anonymizes a users date of birth.
     *
     * @param array $config
     *
     * @return array
     */
    function my_wpmdb_anonymization_rules( $config ) {
        $config['usermeta']['meta_value'][] = array(
            'constraint'     => array( 'meta_key' => 'dob' ),
            'fake_data_type' => 'dateTimeThisCentury',
        );

        return $config;
    }
    
    add_filter( 'wpmdb_anonymization_config', 'my_wpmdb_anonymization_rules' );

If you need to pass arguments to the Faker data method then you can define this using the `fake_data_args` key:

    /**
     * Anonymizes a users date of birth.
     *
     * @param array $config
     *
     * @return array
     */
    function my_wpmdb_anonymization_rules( $config ) {
        $config['usermeta']['meta_value'][] = array(
            'constraint'     => array( 'meta_key' => 'dob' ),
            'fake_data_type' => 'date',
            'fake_data_args' => array( 'Y-m-d' ),
        );

        return $config;
    }
    
    add_filter( 'wpmdb_anonymization_config', 'my_wpmdb_anonymization_rules' );

### Private rule files

Rules you'd rather not put in a plugin, for example for a custom table, can live in a plain PHP file that returns an array in the same format as the bundled `config/*.php` files:

    <?php
    // private-rules.php
    return array(
        'my_backup_table' => array(
            'email'     => array(
                'fake_data_type' => 'email',
            ),
            'full_name' => array(
                'fake_data_type' => 'name',
            ),
        ),
        'usermeta'        => array(
            'meta_value' => array(
                array(
                    'constraint'     => array( 'meta_key' => 'private_phone' ),
                    'fake_data_type' => 'phoneNumber',
                ),
            ),
        ),
    );

Add it to every export, push and pull with the `wpmdb_anonymization_config_files` filter, which receives the absolute paths of the rule files to load, bundled files first:

    add_filter( 'wpmdb_anonymization_config_files', function ( $files ) {
        $files[] = WP_CONTENT_DIR . '/private-rules.php';

        return $files;
    } );

or to a single WP-CLI run with `--rules=/path/private-rules.php` (see [WP-CLI](#wp-cli)).

- Table names are written without the table prefix, as in the bundled files. With the WP-CLI `--prefix=''` option they are used exactly as written, so a rule for `my_backup_table` processes the table `my_backup_table`.
- Extra files are merged with the bundled rules. If both define the same table and column, both sets of rules are kept, bundled rules first, and each value uses the first rule whose constraint matches. Rules with different constraints, such as different `meta_key` values, therefore all apply. To replace a bundled rule for the same rows, change it with the `wpmdb_anonymization_config` filter instead.
- Extra files have no requirements, so they are always loaded. The `wpmdb_anonymization_load_config_file` filter receives their file name without `.php` (e.g. `private-rules`) and can turn them off. Don't name a file after a bundled one (`wordpress.php`, `woocommerce.php`, `easy-digital-downloads.php`), or that file's requirements apply to it.
- The file should only return an array, without declaring functions or classes: the WP-CLI command includes it once to check it before the rules are loaded. Paths that aren't files are skipped by the filter; `--rules` reports them as an error.

## Development

Install the development dependencies with `composer install`.

### Running tests

The unit tests use PHPUnit and run without a WordPress install. The small set of WordPress functions and classes the plugin uses (`apply_filters()`, `wp_hash_password()`, `WP_User`, `wpdb` and so on) are stubbed in `tests/stubs/`, as is the WP-CLI API. The WP-CLI command tests run against an in-memory SQLite database, so they need the `pdo_sqlite` extension.

    composer test

To run against a specific PHP version, call PHPUnit with that binary, e.g. `/path/to/php7.4 vendor/bin/phpunit`. GitHub Actions runs the suite on PHP 7.4, 8.1, 8.2 and 8.3 for every push and pull request.

### Building the plugin zip

    composer build

This runs `bin/build.sh`, which copies the plugin to a temporary directory, installs production dependencies only (`composer install --no-dev --optimize-autoloader`), strips everything listed in `.distignore` (tests, CI config, `.gitignore` and other development files) and writes `build/wp-migrate-db-anonymization.zip`, ready for the WordPress.org plugin repository. The script needs `bash`, `rsync`, `zip` and `composer`. Set `PHP_BIN=/path/to/php` to run Composer with a specific PHP version.
