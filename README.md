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
    - `usermeta`: first name, last name, nickname, description, `session_tokens` and `community-events-location` (both store IP addresses)
    - `comments`: author, email, URL, IP address and user agent
- `woocommerce.php`, including WooCommerce Subscriptions, which stores subscriptions as orders
    - `usermeta`: customer billing and shipping details, Stripe and WooPayments customer IDs
    - `postmeta` and `wc_orders_meta`: order billing and shipping details, address search index, customer IP address and user agent, Stripe customer ID, PayPal Standard payer details
    - `wc_orders`: billing email, IP address, user agent and customer note
    - `wc_order_addresses`: name, company, address, email and phone
    - `wc_customer_lookup`: username, name, email, location and phone numbers
    - `woocommerce_payment_tokens` and `woocommerce_payment_tokenmeta`: gateway token, card last 4 digits and expiry
    - `woocommerce_downloadable_product_permissions`: customer email
    - `wc_download_log`: IP address
    - `woocommerce_sessions`: replaced with an empty session
    - `comments`: content of order notes
- `easy-digital-downloads.php` (EDD 3.x, plus EDD 2.x data left after upgrading)
    - `edd_customers`, `edd_customer_email_addresses` and `edd_customer_addresses`: name, email and address
    - `edd_orders` and `edd_order_addresses`: email, IP address, name and address
    - `edd_customermeta` and `edd_ordermeta`: company, phone, last login IP, additional emails, Stripe and PayPal customer IDs
    - `edd_notes`: note content
    - `edd_payment_tokens`: gateway token and customer ID
    - `edd_logs_api_requests`, `edd_logs_file_downloads` and `edd_logs_emails`: IP address, user agent and email
    - `usermeta` and `postmeta`: Stripe customer IDs and EDD 2.x payment email and IP address

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

## Development

Install the development dependencies with `composer install`.

### Running tests

The unit tests use PHPUnit and run without a WordPress install. The small set of WordPress functions and classes the plugin uses (`apply_filters()`, `wp_hash_password()`, `WP_User`, `wpdb` and so on) are stubbed in `tests/stubs/`.

    composer test

To run against a specific PHP version, call PHPUnit with that binary, e.g. `/path/to/php7.4 vendor/bin/phpunit`. GitHub Actions runs the suite on PHP 7.4, 8.1, 8.2 and 8.3 for every push and pull request.

### Building the plugin zip

    composer build

This runs `bin/build.sh`, which copies the plugin to a temporary directory, installs production dependencies only (`composer install --no-dev --optimize-autoloader`), strips everything listed in `.distignore` (tests, CI config, `.gitignore` and other development files) and writes `build/wp-migrate-db-anonymization.zip`, ready for the WordPress.org plugin repository. The script needs `bash`, `rsync`, `zip` and `composer`. Set `PHP_BIN=/path/to/php` to run Composer with a specific PHP version.
