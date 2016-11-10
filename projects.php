<?php
/*
* Plugin Name: Projects
* Description: Projects
* Version: 0.0.1
* Author: SoftJourn
* Author URI: https://softjourn.com
*/

define( 'PROJECT_PLUGIN_FILE',  __FILE__ );
define( 'PROJECT_BASENAME', plugin_basename( PROJECT_PLUGIN_FILE ) );
define( 'PROJECT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROJECT_URL', plugin_dir_url( __FILE__ ) );
define( 'PROJECT_ADMIN', PROJECT_PATH . 'admin' . DIRECTORY_SEPARATOR );
define( 'PROJECT_ADMIN_TEMPLATE_PATH', PROJECT_ADMIN . 'templates' . DIRECTORY_SEPARATOR );
define( 'PROJECT_ASSETS_DIR', PROJECT_ADMIN . 'assets' . DIRECTORY_SEPARATOR );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once(ABSPATH . 'wp-content/plugins/rest-api/plugin.php');

require PROJECT_PATH . 'WP_REST_Project_Controller.php';
require PROJECT_ADMIN . 'SJProjectsApi.php';

add_action('rest_api_init', function () {
    $myProductController = new WP_REST_Project_Controller('project_type');
    $myProductController->register_routes();
});


function project_post_type() {

    $labels = array(
        'name'                  => _x( 'Project Type', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Project Type', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Projects', 'text_domain' ),
        'name_admin_bar'        => __( 'Project', 'text_domain' ),
        'archives'              => __( 'Project Archives', 'text_domain' ),
        'parent_item_colon'     => __( 'Parent Item:', 'text_domain' ),
        'all_items'             => __( 'All Projects', 'text_domain' ),
        'add_new_item'          => __( 'Add New Project', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'new_item'              => __( 'New Project', 'text_domain' ),
        'edit_item'             => __( 'Edit Project', 'text_domain' ),
        'update_item'           => __( 'Update Project', 'text_domain' ),
        'view_item'             => __( 'View Project', 'text_domain' ),
        'search_items'          => __( 'Search Project', 'text_domain' ),
        'not_found'             => __( 'Not found', 'text_domain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
        'featured_image'        => __( 'Featured Image', 'text_domain' ),
        'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
        'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
        'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
        'insert_into_item'      => __( 'Insert into project', 'text_domain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this project', 'text_domain' ),
        'items_list'            => __( 'Project list', 'text_domain' ),
        'items_list_navigation' => __( 'Projects list navigation', 'text_domain' ),
        'filter_items_list'     => __( 'Filter project list', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Project Type', 'text_domain' ),
        'description'           => __( 'Project', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions'),
        'taxonomies'            => array( 'category' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-format-aside',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'rewrite'            => array( 'slug' => 'project' ),
        'show_in_rest'          => true,
        'rest_base'             => 'projects',
        'rest_controller_class' => 'WP_REST_Project_Controller',
//        'rest_controller_class' => 'WP_REST_Posts_Controller',
    );
    register_post_type( 'project_type', $args );
}
add_action( 'init', 'project_post_type', 0 );

function init_project_meta_box() {
    require_once ( PROJECT_ADMIN . 'project_metabox.php' );
    $project_metabox = new ProjectMetabox();
    $project_metabox->init();
}

function my_rest_prepare_post( $data, $post, $request ) {
    $_data = $data->data;
    $thumbnail_id = get_post_thumbnail_id( $post->ID );
    $thumbnail = wp_get_attachment_image_src( $thumbnail_id, 'project-image-size' );
    $_data['featured_image_thumbnail_url'] = $thumbnail[0];

    $priceTaxonomy = wp_get_post_terms($post->ID, 'sj_project_price', array('fields' => 'all'));
    $dueDateTaxonomy = wp_get_post_terms($post->ID, 'sj_project_due_date', array('fields' => 'all'));

    $price = '';
    $dueDate = '';
    if (isset($priceTaxonomy[0])) {
        $price = $priceTaxonomy[0]->name;
    }
    if (isset($dueDateTaxonomy[0])) {
        $dueDate = $dueDateTaxonomy[0]->name;
    }

    $_data['price'] = $price;
    $_data['due_date'] = $dueDate;
    $_data['api_data'] = SJProjectsApi::getProject($post->ID);
    $_data['transactions'] = SJProjectsApi::getProjectTransactions($post->ID);

    $data->data = $_data;
    return $data;
}
add_filter( 'rest_prepare_project_type', 'my_rest_prepare_post', 10, 3 );
add_image_size( 'project-image-size', 620, 320 );
function wpdocs_custom_excerpt_length( $length ) {
    return 30;
}
add_filter( 'excerpt_length', 'wpdocs_custom_excerpt_length', 999 );

init_project_meta_box();

/*
 * HIDE ADMIN BAR
 */
add_filter('show_admin_bar', '__return_false');


/**
 *
 * CREATE/UPDATE USER ACTIONS
 */
add_action( 'user_register', 'registration_save', 10, 1 );
function registration_save( $user_id ) {

    if ( isset( $_POST['email'] ) ) {
        SJProjectsApi::createUser($user_id, $_POST['email'], $_POST['first_name'].' '.$_POST['last_name']);
    }

}
add_action( 'profile_update', 'my_profile_update', 10, 2 );
function my_profile_update( $user_id ) {
    if ( isset( $_POST['email'] ) ) {
        SJProjectsApi::createUser($user_id, $_POST['email'], $_POST['first_name'].' '.$_POST['last_name']);
    }
}
