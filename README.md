WP Migrate DB Anonymization Addon
==========================================

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
        if ( false !== strpos( $user->user_email, '@somedomain.com' ) ) {
            // All users with the login containing an email address of somedomain.com are whitelisted
            return true;
        }
        
        return $whitelisted;
    }
    add_filter( 'wpmdb_anonymization_user_whitelisted', 'my_wpmdb_anonymization_user_whitelisted', 10, 2 );
    
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