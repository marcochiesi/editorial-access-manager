<?php

class Editorial_Access_Manager {

	/**
	 * Placeholder constructor
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Register actions and filters
	 *
	 * @since 0.1.0
	 */
	public function setup() {
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap_roles' ), 10, 4 );
	}

	/**
	 * Map the edit_post meta cap based on whether the users role is whitelisted by the post
	 *
	 * @param array $caps
	 * @param string $cap
	 * @param int $user_id
	 * @param array $args
	 * @since 0.1.0
	 * @return array
	 */
	public function map_meta_cap_roles( $caps, $cap, $user_id, $args ) {
		if ( 'edit_post' == $cap ) {

			$enable_custom_access = get_post_meta( (int) $args[0], 'eam_enable_custom_access', true );

			if ( $enable_custom_access ) {

				$allowed_roles = get_post_meta( (int) $args[0], 'eam_allowed_roles', true );
				$user = new WP_User( $user_id );

				if ( ! in_array( 'administrator', $user->roles ) && ! empty( $allowed_roles ) && count( array_diff( $user->roles, $allowed_roles ) ) >= 1 ) {
					$caps[] = 'do_not_allow';
				}
			}
		}

		return $caps;
	}

	/**
	 * Enqueue backend JS and CSS for post edit screen
	 *
	 * @param string $hook
	 * @since 0.1.0
	 */
	public function action_admin_enqueue_scripts( $hook ) {

		if ( 'post.php' == $hook || 'post-new.php' == $hook ) {
			/**
			 * Setup JS stuff
			 */
			if ( true /*defined( SCRIPT_DEBUG ) && SCRIPT_DEBUG*/ ) {
				$js_path = '/js/post-admin.js';
				$css_path = '/build/css/post-admin.css';
			} else {
				$js_path = '/build/js/post-admin.min.js';
				$css_path = '/build/css/post-admin.min.css';
			}

			wp_register_script( 'jquery-chosen', plugins_url( '/bower_components/chosen_v1.1.0/chosen.jquery.js', dirname( __FILE__ ) ), array( 'jquery' ), '1.0', true );
			wp_enqueue_script( 'eam-post-admin', plugins_url( $js_path, dirname( __FILE__ ) ), array( 'jquery-chosen' ), '1.0', true );

			/**
			 * Setup CSS stuff
			 */
			wp_enqueue_style( 'jquery-chosen', plugins_url( '/bower_components/chosen_v1.1.0/chosen.min.css', dirname( __FILE__ ) ) );
			wp_enqueue_style( 'eam-post-admin', plugins_url( $css_path, dirname( __FILE__ ) ) );
		}
	}

	/**
	 * Register meta boxes
	 *
	 * @since 0.1.0
	 */
	public function action_add_meta_boxes() {
		$post_types = get_post_types();

		foreach( $post_types as $post_type ) {
			add_meta_box( 'eam_access_manager', __( 'Editorial Access Manager', 'editorial-access-manager' ), array( $this, 'meta_box_access_manager' ), $post_type, 'side', 'core' );
		}
	}

	/**
	 * Save access control information
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 */
	public function action_save_post( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || 'revision' == get_post_type( $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['eam_access_manager'] ) && wp_verify_nonce( $_POST['eam_access_manager'], 'eam_access_manager_action' ) ) {
			if ( ! empty( $_POST['eam_enable_custom_access'] ) ) {
				update_post_meta( $post_id, 'eam_enable_custom_access', (int) $_POST['eam_enable_custom_access'] );
			} else {
				delete_post_meta( $post_id, 'eam_enable_custom_access' );
			}

			if ( ! empty( $_POST['eam_allowed_roles'] ) ) {

				foreach( $_POST['eam_allowed_roles'] as $role ) {
					$allowed_roles[] = sanitize_text_field( $role );
				}

				update_post_meta( $post_id, 'eam_allowed_roles', $allowed_roles );

			} else {
				delete_post_meta( $post_id, 'eam_allowed_roles' );
			}

			if ( ! empty( $_POST['eam_allowed_users'] ) ) {
				update_post_meta( $post_id, 'eam_allowed_users', array_map( 'absint', $_POST['eam_allowed_users'] ) );

			} else {
				delete_post_meta( $post_id, 'eam_allowed_users' );
			}

		}
	}

	/**
	 * Output access manager meta box
	 *
	 * @param object $post
	 * @since 0.1.0
	 */
	public function meta_box_access_manager( $post ) {
		$post_type_object = get_post_type_object( get_post_type( $post->ID ) );
		$edit_post_cap = $post_type_object->cap->edit_post;
		$roles = get_editable_roles();

		$users = get_users();

		$allowed_roles = (array) get_post_meta( $post->ID, 'eam_allowed_roles', true );
		$allowed_users = (array) get_post_meta( $post->ID, 'eam_allowed_users', true );
		?>

		<?php wp_nonce_field( 'eam_access_manager_action', 'eam_access_manager' ); ?>

		<div>
			<input <?php checked( 1, get_post_meta( $post->ID, 'eam_enable_custom_access', true ) ); ?> type="checkbox" name="eam_enable_custom_access" id="eam_enable_custom_access" value="1">
		 	<?php esc_html_e( 'Enable custom access management', 'editorial-access-manager' ); ?>
		</div>
		<div id="eam_custom_access_controls">


			<label for="eam_allowed_roles"><?php esc_html_e( 'Manage access for roles:', 'editorial-access-manager' ); ?></label>
			<select multiple name="eam_allowed_roles[]" id="eam_allowed_roles">
				<?php foreach ( $roles as $role_name => $role_array ) : ?>
					<option
						value="<?php echo esc_attr( $role_name ); ?>"
						<?php if ( 'administrator' == $role_name ) : ?>selected disabled
						<?php elseif ( /*$role->has_cap( $edit_post_cap ) ||*/ in_array( $role_name, $allowed_roles ) ) : ?>selected<?php endif;?>
						>
						<?php echo esc_attr( ucwords( $role_name ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="eam_allowed_users"><?php esc_html_e( 'Manage access for users:', 'editorial-access-manager' ); ?></label>
			<select multiple name="eam_allowed_users[]" id="eam_allowed_users">
				<?php foreach ( $users as $user_object ) : $user = new WP_User( $user_object->ID ); ?>
					<option
						value="<?php echo absint( $user_object->ID ); ?>"
						<?php if ( in_array( 'administrator', $user->roles ) ) : ?>selected disabled
						<?php elseif ( /*user_can( $user_object->ID, $edit_post_cap )*/ in_array( $user_object->ID, $allowed_users ) ) : ?>selected<?php endif;?>
						>
						<?php echo esc_attr( $user->user_login ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 0.1.0
	 * @return object
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}