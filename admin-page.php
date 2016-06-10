<?php
add_action( 'admin_menu', 'mediastorage_add_admin_menu' );
add_action( 'admin_init', 'mediastorage_settings_init' );


function mediastorage_add_admin_menu(  ) {

    add_options_page( 'Media Storage', 'Media Storage', 'manage_options', 'media_storage', 'mediastorage_options_page' );

}


function mediastorage_settings_init(  ) {

    register_setting( 'swift', 'mediastorage_settings' );

    add_settings_section(
        'mediastorage_swift_section',
        __( 'OpenStack Swift', 'wordpress' ),
        'mediastorage_settings_section_callback',
        'swift'
    );

    add_settings_field(
        'swift_username',
        __( 'Username', 'wordpress' ),
        'swift_username_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_password',
        __( 'Password', 'wordpress' ),
        'swift_password_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_tenantId',
        __( 'Tenant Id', 'wordpress' ),
        'swift_tenantId_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_tenantName',
        __( 'Tenant name', 'wordpress' ),
        'swift_tenantName_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_region',
        __( 'Region', 'wordpress' ),
        'swift_region_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_container',
        __( 'Container', 'wordpress' ),
        'swift_container_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_authUrl',
        __( 'Auth URL', 'wordpress' ),
        'swift_authUrl_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_publicUrl',
        __( 'Container public URL', 'wordpress' ),
        'swift_publicUrl_render',
        'swift',
        'mediastorage_swift_section'
    );

    add_settings_field(
        'swift_debugLog',
        __( 'Debug', 'wordpress' ),
        'swift_debugLog_render',
        'swift',
        'mediastorage_swift_section'
    );

}


function swift_username_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_username]' value='<?php echo $options['swift_username']; ?>'>
    <?php

}


function swift_password_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_password]' value='<?php echo $options['swift_password']; ?>'>
    <?php

}


function swift_tenantId_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_tenantId]' value='<?php echo $options['swift_tenantId']; ?>'>
    <?php

}


function swift_tenantName_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_tenantName]' value='<?php echo $options['swift_tenantName']; ?>'>
    <?php

}


function swift_region_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_region]' value='<?php echo $options['swift_region']; ?>'>
    <?php

}


function swift_container_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_container]' value='<?php echo $options['swift_container']; ?>'>
    <?php

}

function swift_publicUrl_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_publicUrl]' value='<?php echo $options['swift_publicUrl']; ?>'>
    <?php

}


function swift_authUrl_render(  ) {

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='text' name='mediastorage_settings[swift_authUrl]' value='<?php echo $options['swift_authUrl']; ?>'>
    <?php
}

function swift_debugLog_render(  )
{

    $options = get_option( 'mediastorage_settings' );
    ?>
    <input type='checkbox' name='mediastorage_settings[swift_debugLog]' <?php checked( $options['swift_debugLog'], 1 ); ?> value='1'>
    <?php
}


function mediastorage_settings_section_callback(  ) {

    echo __( 'This section description', 'wordpress' );

}


function mediastorage_options_page(  ) {

    ?>
    <form action='options.php' method='post'>

        <h2>Media Cloud</h2>

        <?php
        settings_fields( 'swift' );
        do_settings_sections( 'swift' );
        submit_button();
        ?>

    </form>
    <?php

}

?>