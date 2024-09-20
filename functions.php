<?php 
add_action('wp_enqueue_scripts', 'addweb_enqueue_styles');
function addweb_enqueue_styles() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css'); 
} 

function create_car_post_type() {
    register_post_type('car', array(
        'labels' => array(
            'name' => __('Cars'),
            'singular_name' => __('Car'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'thumbnail'),
    ));
}
add_action('init', 'create_car_post_type');

function create_car_taxonomies() {
    register_taxonomy('make', 'car', array('labels' => array('name' => __('Makes')), 'hierarchical' => true));
    register_taxonomy('model', 'car', array('labels' => array('name' => __('Models')), 'hierarchical' => true));
    register_taxonomy('fuel_type', 'car', array('labels' => array('name' => __('Fuel Types')), 'hierarchical' => false));
}
add_action('init', 'create_car_taxonomies');

function car_entry_form() {
    ob_start(); ?>
     <style>
        #car-entry-form {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        #car-entry-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        #car-entry-form input[type="text"],
        #car-entry-form select,
        #car-entry-form input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        #car-entry-form input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        #car-entry-form input[type="submit"]:hover {
            background-color: #45a049;
        }
        #response {
            margin-top: 15px;
            text-align: center;
        }
    </style>

    <form id="car-entry-form" enctype="multipart/form-data">
        <?php wp_nonce_field('car_entry_nonce', 'car_entry_nonce_field'); ?>
        <label for="car-name">Car Name:</label>
        <input type="text" id="car-name" name="car_name" required>

        <label for="make">Make:</label>
        <select id="make" name="make" required>
            <?php 
            $makes = get_terms(array('taxonomy' => 'make', 'hide_empty' => false));
            foreach ($makes as $make) {
                echo '<option value="' . $make->term_id . '">' . $make->name . '</option>';
            }
            ?>
        </select>

        <label for="model">Model:</label>
        <select id="model" name="model" required>
            <?php 
            $models = get_terms(array('taxonomy' => 'model', 'hide_empty' => false));
            foreach ($models as $model) {
                echo '<option value="' . $model->term_id . '">' . $model->name . '</option>';
            }
            ?>
        </select>

        <label>Fuel Type:</label>
        <?php 
        $fuel_types = get_terms(array('taxonomy' => 'fuel_type', 'hide_empty' => false));
        foreach ($fuel_types as $fuel) {
            echo '<label><input type="radio" name="fuel_type" value="' . $fuel->term_id . '" required> ' . $fuel->name . '</label><br>';
        }
        ?>

        <label for="car-image">Image Upload:</label>
        <input type="file" id="car-image" name="car_image" accept="image/*" required>

        <input type="submit" value="Submit">
    </form>
    <div id="response"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('car_entry', 'car_entry_form');

function car_entry_scripts() {
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'car_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'car_entry_scripts');

function handle_car_entry() {
    if (!isset($_POST['car_entry_nonce_field']) || !wp_verify_nonce($_POST['car_entry_nonce_field'], 'car_entry_nonce')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }

    error_log(print_r($_POST, true));
    error_log(print_r($_FILES, true));

    if (!isset($_POST['car_name'], $_POST['make'], $_POST['model'], $_POST['fuel_type'])) {
        wp_send_json_error('All fields are required.');
        return;
    }

    $car_name = sanitize_text_field($_POST['car_name']);
    $make = intval($_POST['make']);
    $model = intval($_POST['model']);
    $fuel_type = intval($_POST['fuel_type']);

    $image_id = null;
    if (!empty($_FILES['car_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded_file = $_FILES['car_image'];
        $upload = wp_handle_upload($uploaded_file, array('test_form' => false));

        if (isset($upload['file'])) {
            $image_url = $upload['url'];
            $image_id = wp_insert_attachment(array(
                'guid' => $image_url,
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name($uploaded_file['name']),
                'post_content' => '',
                'post_status' => 'inherit',
            ), $upload['file']);

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($image_id, $upload['file']);
            wp_update_attachment_metadata($image_id, $attach_data);
        } else {
            wp_send_json_error('Image upload failed: ' . $upload['error']);
            return;
        }
    }

    $post_id = wp_insert_post(array(
        'post_title' => $car_name,
        'post_type' => 'car',
        'post_status' => 'publish',
    ));

    if ($post_id) {
        wp_set_object_terms($post_id, array($make), 'make');
        wp_set_object_terms($post_id, array($model), 'model');
        wp_set_object_terms($post_id, array($fuel_type), 'fuel_type');
        if ($image_id) {
            set_post_thumbnail($post_id, $image_id);
        }
        wp_send_json_success('Car added successfully!');
    } else {
        wp_send_json_error('Failed to create post.');
    }
}
add_action('wp_ajax_handle_car_entry', 'handle_car_entry');
add_action('wp_ajax_nopriv_handle_car_entry', 'handle_car_entry');

function car_list_shortcode() {
    $args = array('post_type' => 'car', 'posts_per_page' => -1);
    $query = new WP_Query($args);
    
    ob_start();
    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li>' . get_the_title() . '</li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    } else {
        echo 'No cars found.';
    }
    return ob_get_clean();
}
add_shortcode('car-list', 'car_list_shortcode');

function my_custom_scripts() {
    wp_enqueue_script('jquery'); 

    $custom_js = "
        jQuery(document).ready(function($) {
            $('#car-entry-form').on('submit', function(event) {
                event.preventDefault(); // Prevent default form submission

                var formData = new FormData(this);
                formData.append('action', 'handle_car_entry'); 

                $.ajax({
                    url: car_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        if (response.success) {
                            $('#response').html('<p>' + response.data + '</p>');
                            $('#car-entry-form')[0].reset(); // Reset the form
                        } else {
                            $('#response').html('<p>' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#response').html('<p>An error occurred while submitting the form.</p>');
                    }
                });
            });
        });
    ";

    wp_add_inline_script('jquery', $custom_js);
}
add_action('wp_enqueue_scripts', 'my_custom_scripts');
?>
