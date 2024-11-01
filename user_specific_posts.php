<?php
/**
 * Plugin Name:       User Specific Posts
 * Plugin URI:        https://www.mansurahamed.com/user-specific-posts/
 * Description:       Restrict any page, post visibility to a specific user only. It will be visible only by them when they're logged in. Shortcode provided to show all posts for specific user. 
 * Version:           1.0.2
 * Author:            mansurahamed
 * Author URI:        https://www.upwork.com/freelancers/~013259d08861bd5bd8
 * Text Domain:       user-specific-posts
 */


if(!class_exists('UserSpecificPosts'))
{
	class UserSpecificPosts
	{
		/**
		 * Hook into the appropriate actions when the class is constructed.
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post', array( $this, 'save') );
			add_action( 'template_redirect', array( &$this, 'template_redirect' ), apply_filters('usp_redirect_priority',0) );
			add_shortcode( 'usp_posts', array( $this, 'shortcode') );
		}

		/**
		 * Adds the meta box container.
		 */
		public function add_meta_box( $post_type ) {
			// Limit meta box to certain post types.
			$post_types = apply_filters('usp_post_types', array( 'post', 'page' ));

			if ( in_array( $post_type, $post_types ) ) {
				add_meta_box(
					'usp_admin_metabox01',
					__( 'User Specific Post To', 'user-specific-posts' ),
					array( $this, 'render_meta_box_content' ),
					$post_type,
					apply_filters('usp_metabox_position', 'advanced'),
					apply_filters('usp_metabox_priority', 'high')
				);
			}
		}

		/**
		 * Save the meta when the post is saved.
		 *
		 * @param int $post_id The ID of the post being saved.
		 */
		public function save( $post_id ) {

			/*
			 * We need to verify this came from the our screen and with proper authorization,
			 * because save_post can be triggered at other times.
			 */

			// Check if our nonce is set.
			if ( ! isset( $_POST['usp_admin_nonce'] ) ) {
				return $post_id;
			}

			$nonce = $_POST['usp_admin_nonce'];

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $nonce, 'usp_metabox' ) ) {
				return $post_id;
			}

			/*
			 * If this is an autosave, our form has not been submitted,
			 * so we don't want to do anything.
			 */
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return $post_id;
			}

			// Check the user's permissions.
			if ( 'page' == $_POST['post_type'] ) {
				if ( ! current_user_can( 'edit_page', $post_id ) ) {
					return $post_id;
				}
			} else {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return $post_id;
				}
			}

			/* OK, it's safe for us to save the data now. */

			// Sanitize the user input.
			$data = intval( $_POST['usp_user_id'] );

			// Update the meta field.
			update_post_meta( $post_id, 'usp_user_id', $data );
		}


		/**
		 * Render Meta Box content.
		 *
		 * @param WP_Post $post The post object.
		 */
		public function render_meta_box_content( $post ) {

			// Add an nonce field so we can check for it later.
			wp_nonce_field( 'usp_metabox', 'usp_admin_nonce' );

			// Use get_post_meta to retrieve an existing value from the database.
			$value = get_post_meta( $post->ID, 'usp_user_id', true );

			// Display the form, using the current value.
			$specific_user = get_post_meta( $post->ID, 'usp_user_id', true );
			$args = array(
				'name' => 'usp_user_id',
				'selected' => $value,
				'show_option_none' => __('No user selected'),
				'show' => 'display_name_with_login'
				 );
			wp_dropdown_users($args);

		}
		
		/**
		 * Restrict post to user by user ID..
		 */
		public function template_redirect()
		{
			if(is_home()) return;
			global $post;
			$value = get_post_meta( $post->ID, 'usp_user_id', true );
			if($value > 0)
			{
				if( !is_user_logged_in() || (get_current_user_id() != $value) )
				{
					wp_redirect( apply_filters('usp_redirect_url',home_url()) );
					exit;
				}
			}
		}
		
		/**
		 * Provide shortcode to lists the posts.
		 */
		public function shortcode($args)
		{
			?>
	<?php
	$btpgid=get_queried_object_id();
	$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
	isset($args['user_id']) ? $user_id = $args['user_id'] :	$user_id = apply_filters('usp_frontend_user_id', get_current_user_id() );
	$args = array( 
		'posts_per_page' => apply_filters('usp_frontend_post_per_page', 10 ) ,
		'paged' => $paged,
		'meta_key'         => 'usp_user_id',
		'meta_value'       => $user_id,
		'numberposts' => -1,
		'post_type' => apply_filters('usp_post_types', array( 'post', 'page' ) ) 
		);
		$postslist = new WP_Query( $args );
		ob_start();
		if ( $postslist->have_posts() ) :
			while ( $postslist->have_posts() ) : $postslist->the_post(); 
			 ?>

				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

				<header class="entry-header">
					<h1 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
				</header><!-- .entry-header -->

				<div class="entry-content">
				<?php the_excerpt(); ?>

				<!-- THIS IS WHERE THE FUN PART GOES -->

				</div><!-- .entry-content -->

				</article><!-- #post-## -->
	<?php
			 endwhile;  

				 next_posts_link( 'Older Entries', $postslist->max_num_pages );?> 
	<?php
				 previous_posts_link( 'Next Entries &raquo;' ); 
			wp_reset_postdata();
		endif;
			return ob_get_clean();
			}
	}
}

$user_specific_posts = new UserSpecificPosts(); 


