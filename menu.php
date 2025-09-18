<?php
// Add submenu under Tutor admin menu
add_action('admin_menu', 'lms_new_courses_admin_menu');

function lms_new_courses_admin_menu() {
    add_submenu_page(
        'tutor',
        __('LMS New Courses', 'lms-new-courses'),
        __('LMS New Courses', 'lms-new-courses'),
        'manage_options',
        'lms-new-courses',
        'lms_new_courses_settings_page'
    );
}

// Callback function to display the settings page
function lms_new_courses_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('LMS New Courses Settings', 'lms-new-courses'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('lms_new_courses_settings'); ?>
            <?php do_settings_sections('lms_new_courses_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings and fields
add_action('admin_init', 'lms_new_courses_register_settings');

function lms_new_courses_register_settings() {
    // Register setting for "enroll only subscribers" toggle
    register_setting('lms_new_courses_settings', 'lms_new_courses_enroll_subs');

    // Register setting for "choose certain users" toggle
    register_setting('lms_new_courses_settings', 'lms_new_courses_enroll_specific_users');

    // Add settings section
    add_settings_section(
        'lms_new_courses_section',
        __('LMS New Courses Settings', 'lms-new-courses'),
        'lms_new_courses_section_callback',
        'lms_new_courses_settings'
    );

    // Add settings fields
    add_settings_field(
        'lms_new_courses_enroll_subs',
        __('Enroll only subscribers', 'lms-new-courses'),
        'lms_new_courses_enroll_subs_callback',
        'lms_new_courses_settings',
        'lms_new_courses_section'
    );

    add_settings_field(
        'lms_new_courses_enroll_specific_users',
        __('Choose certain users', 'lms-new-courses'),
        'lms_new_courses_enroll_specific_users_callback',
        'lms_new_courses_settings',
        'lms_new_courses_section'
    );
}

// Callback function to display section description
function lms_new_courses_section_callback() {
    echo '<p>'.__('Configure settings for LMS New Courses.', 'lms-new-courses').'</p>';
}

// Callback function to display "enroll only subscribers" toggle
function lms_new_courses_enroll_subs_callback() {
    $only_subs = get_option('lms_new_courses_enroll_subs');
    ?>
    <label for="lms_new_courses_enroll_subs">
        <input type="checkbox" id="lms_new_courses_enroll_subs" name="lms_new_courses_enroll_subs" value="1" <?php checked(1, $only_subs); ?>>
        <?php _e('Enroll only subscribers', 'lms-new-courses'); ?>
    </label>
    <?php
}

// Callback function to display "choose certain users" toggle
function lms_new_courses_enroll_specific_users_callback() {
    $only_specific_users = get_option('lms_new_courses_enroll_specific_users');
    ?>
    <label for="lms_new_courses_enroll_specific_users">
        <input type="checkbox" id="lms_new_courses_enroll_specific_users" name="lms_new_courses_enroll_specific_users" value="1" <?php checked(1, $only_specific_users); ?>>
        <?php _e('Choose certain users', 'lms-new-courses'); ?>
    </label>
    <?php
}



// Enqueue de CSS
add_action('admin_enqueue_scripts', 'lms_new_courses_enqueue_styles');
function lms_new_courses_enqueue_styles() {
    // Obtener el slug de la página actual
    $current_page = get_current_screen();
    $current_page_slug = $current_page->id;

    // if (in_array($current_page_slug, array('toplevel_page_lms_impexp_page', 'lms_impexp_importador', 'lms_impexp_exportador'))) {
        css_file('css/styles.css');

        // Bootstrap
        css_file('/third_party/bootstrap/5.x/bootstrap.min.css', false, null, null, 'css-bt5');
        js_file( '/third_party/bootstrap/5.x/bootstrap.bundle.min.js', false, ['jquery-core'], null, 'js-bt5'); 
    // }
}