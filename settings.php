<?php

function value($value, $empty = '')
{
    if (is_bool($value))
        return sprintf('value="%s"', $value ? 1 : 0);

    return empty($value) ? '' : sprintf('value="%s"', $value);
}

add_action('admin_init', function () {
    // Whitelist 'mediastorage' option for the 'mediastorage' page
    register_setting('mediastorage', 'mediastorage');
    register_setting('mediastorage', 'upload_url_path');

    // Fetch current settings or defaults
    $options = get_option('mediastorage', [
        'rewriteAttachmentUrls' => false,
        'objectStore' => 'amazon',
        'openstack' => [
            'useWrapper' => true,
            'authUrl' => '', 'authVersion' => 'v2.0',
            'username' => '', 'password' => '', 'tenantId' => '', 'tenantName' => '',
            'region' => '', 'container' => ''
        ]
    ]);
    $openstack = $options['openstack'];


    /********* GENERAL **********/
    $section = 'general';
    add_settings_section("mediastorage_$section", __('General'), function () use($options) {
        echo "<p>General description</p>";
    }, 'mediastorage');

    add_settings_field($name = 'upload_url_path', __('Media URL'), function () use($name, $section) {
        echo '
            <input size="40" name="'.$name.'"'. value(get_option($name)) .' placeholder="https://storage.public.url/container">
        ';
    }, 'mediastorage', "mediastorage_$section");

//    add_settings_field($name = 'useWrapper', __('Use stream wrapper'), function () use($name, $options) {
//        echo '
//            <input type="checkbox" name="mediastorage['.$name.']"'. checked($options[$name] ?? 0, 1, false) .' value="1">
//        ';
//    }, 'mediastorage', "mediastorage_$section");

//    add_settings_field($name = 'rewriteAttachmentUrls', __('Rewrite attachment URLs'), function () use($name, $options) {
//        echo '
//            <input type="checkbox" name="mediastorage['.$name.']"'. checked($options[$name] ?? 0, 1, false) .' value="1">
//        ';
//    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'objectStore', __('Object store'), function () use($name, $options) {
        echo '
        <select name="mediastorage['.$name.']">
            <option value="0"'. selected(false, boolval($options[$name]), false) .'>disabled</option>
            <option value="openstack"'. selected('openstack', $options[$name], false) .'>OpenStack (Swift)</option>
            <!--
            <option value="s3"'. selected('s3', $options[$name], false) .'>Amazon S3</option>
            <option value="google"'. selected('google', $options[$name], false) .'>Google Cloud Storage</option>
            -->
        </select>
        ';
    }, 'mediastorage', "mediastorage_$section");


    /********* SWIFT **********/
    $section = 'openstack';
    add_settings_section("mediastorage_$section", __('OpenStack'), function () {
        echo "<p>Swift object store</p>";
    }, 'mediastorage');

    add_settings_field($name = 'authUrl', __('Identity URL'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .' placeholder="https://auth.cloud.net/">
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'authVersion', __('Identity version'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <select name="mediastorage['.$section.']['.$name.']">
                <option value="v2.0"'. selected('v2.0', $options[$name], false) .'>v2.0</option>
                <option value="v3.0"'. selected('v3.0', $options[$name], false) .'>v3.0</option>
            </select>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'username', __('Username'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'password', __('Password'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'tenantId', __('Tenant id'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'tenantName', __('Tenant name'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'region', __('Region'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");

    add_settings_field($name = 'container', __('Container'), function () use($name, $section, $openstack) {
        $options = $openstack;
        echo '
            <input size="40" name="mediastorage['.$section.']['.$name.']"'. value($options[$name]) .'>
        ';
    }, 'mediastorage', "mediastorage_$section");
});

add_action( 'admin_menu', function () {
    // Page id = 'mediastorage'
    add_options_page( 'Media Storage', 'Media Storage', 'manage_options', 'mediastorage', function () { ?>
        <form action='options.php' method='post' id="mediastorage">
            <h1>Media Storage</h1>

            <?php
            do_settings_sections('mediastorage');

            settings_fields('mediastorage');
            ?><p><?php
                submit_button(null, 'primary', 'submit', false);
                submit_button('Test Settings', 'secondary', 'test', false, ['style'=>'margin-left: 10px;']);
                ?><span id="result" style="padding-left: 10px;"></span></p>
        </form>

        <script>
            function updateTestButton(storeValue) {
                if (storeValue == '0')
                    jQuery("input[name='test']").prop('disabled', true);
                else
                    jQuery("input[name='test']").prop('disabled', false);
            }
            jQuery(function () {
                updateTestButton(jQuery("select[name='mediastorage[objectStore]").val());
            });

            jQuery("select[name='mediastorage[objectStore]").change(function (e) {
                updateTestButton(e.target.value);
            });
            jQuery("input[name='test']").click(function (e) {
                e.preventDefault();

                var data = jQuery("#mediastorage").serializeArray();
                var postData = {};
                data.forEach(function (o) {
                    postData[o.name] = o.value;
                });

                jQuery.ajax({
                    url: '<?php print plugins_url('plugin.php', '/wp-media-storage/plugin.php') ?>?test=1',
                    type: 'POST',
                    data: postData,
                    dataType: 'json'
                }).success(function (data) {
                    console.log(data);
                    jQuery('#result').html('<span style="color: #25c609;">SUCCESS: found '+ data['objectCount'] +' objects in the container</span>');
                }).error(function (data) {
                    console.log(data);
                    jQuery('#result').html('<span style="color: red">ERROR: '+ data['responseText'] +'</span>');
                });
            });
        </script>
    <?php });
});
