WP Migrate DB Anonymization Addon
==========================================

## Installation

The addon should be installed on the live site. This ensures any time the site's database is exported, pushed or pulled, the user data is anonymized.

When the plugin is activated it will anonymize by default. You will need to deactivate the plugin to turn it off.

## Configuration

To preserve specific rows in the users table, use the `WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST` constant to set a whitelist of comma separated user logins.

To replace all passwords with a hashed default password, set the password using the `WPMDB_ANONYMIZATION_DEFAULT_PASSWORD` constant.

These constants should be defined in the site's wp-config.php file, for example:

    define( 'WPMDB_ANONYMIZATION_USER_LOGIN_WHITELIST', 'your.admin.email@gmail.com' );
    define( 'WPMDB_ANONYMIZATION_DEFAULT_PASSWORD', 'password123' );
    
You can also programtically control which users are whitelisted with the following filter:

    function my_wpmdb_anonymization_user_whitelisted( $whitelisted, $user_login )  {
        if ( false !== strpos( $user_login, '@somedomain.com ) ) {
            // All users with the login containing an email address of somedomain.com are whitelisted
            return true;
        }
        
        return $whitelisted;
    }
    add_filter( 'wpmdb_anonymization_user_whitelisted', 'my_wpmdb_anonymization_user_whitelisted', 10, 2 );
    
## Extending

The rules for anonymization can be extended using the `wpmdb_anonymization_config` filter:

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
