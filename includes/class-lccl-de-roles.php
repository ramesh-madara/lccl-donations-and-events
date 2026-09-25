<?php
/**
 * Reviewer role and dashboard pages.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the program reviewer role and frontend pages.
 */
class LCCL_DE_Roles {

	/**
	 * WordPress role slug. Holds only view_lccl_submissions — no read.
	 */
	const ROLE = 'lccl_blood_donation_reviewer';

	/**
	 * Capability that unlocks frontend dashboards and submission REST routes.
	 */
	const CAP = 'view_lccl_submissions';

	/**
	 * Bump when the role's capability set or display name changes.
	 */
	const VERSION = 2;

	/**
	 * Option that stores the installed role version.
	 */
	const OPTION = 'lccl_de_roles_version';

	/**
	 * Option that stores the blood donation admin page ID.
	 */
	const PAGE_OPTION = 'lccl_de_admin_page_id';

	/**
	 * Option that stores the Join Our Projects admin page ID.
	 */
	const PROJECTS_PAGE_OPTION = 'lccl_de_projects_admin_page_id';

	/**
	 * Option that stores the Join Our Projects public form page ID.
	 */
	const JOIN_PAGE_OPTION = 'lccl_de_join_form_page_id';

	/**
	 * Option that stores the Free Spectacles admin page ID.
	 */
	const SPECTACLES_PAGE_OPTION = 'lccl_de_spectacles_admin_page_id';

	/**
	 * Option that stores the Payment Dashboard page ID.
	 */
	const PAYMENT_DASHBOARD_PAGE_OPTION = 'lccl_de_payment_dashboard_page_id';

	/**
	 * Bump when a new plugin-owned dashboard page is added so it is created once.
	 */
	const PAGES_VERSION = 2;

	/**
	 * Option that stores the dashboard-page bootstrap version.
	 */
	const PAGES_OPTION = 'lccl_de_pages_version';

	/**
	 * Once set, the plugin never looks up or deletes a public join form page.
	 */
	const JOIN_PAGE_RETIRED = 'lccl_de_join_form_page_retired';

	/**
	 * User meta: "1" means the reviewer cannot sign in.
	 */
	const DISABLED_META = 'lccl_de_reviewer_disabled';

	/**
	 * Install or upgrade the role if the stored version is behind.
	 *
	 * Dashboard pages are created once (activation or a pages-version bump),
	 * never rewritten or deleted on ordinary requests.
	 */
	public static function maybe_install() {
		$installed = (int) get_option( self::OPTION, 0 );

		if ( $installed < self::VERSION ) {
			self::install();
		}

		if ( (int) get_option( self::PAGES_OPTION, 0 ) < self::PAGES_VERSION ) {
			self::bootstrap_pages();
		}
	}

	/**
	 * Activation: role plus a one-shot page bootstrap.
	 */
	public static function activate() {
		self::install();
		self::bootstrap_pages();
	}

	/**
	 * Create the role and strip read.
	 */
	public static function install() {
		$label = __( 'LCCL Program Reviewer', 'lccl-de' );
		$role  = get_role( self::ROLE );

		if ( ! $role ) {
			add_role(
				self::ROLE,
				$label,
				array(
					self::CAP => true,
				)
			);
			$role = get_role( self::ROLE );
		} else {
			self::rename_role( $label );
		}

		if ( $role ) {
			$role->add_cap( self::CAP );
			$role->remove_cap( 'read' );
		}

		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Whether this account is a reviewer (and not a site admin).
	 *
	 * @param WP_User|int|null $user User or ID. Defaults to the current user.
	 * @return bool
	 */
	public static function user_is_reviewer( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		return in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * Whether this account should be kept out of wp-admin.
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function should_lock_admin( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return self::user_is_reviewer( $user ) || user_can( $user, self::CAP );
	}

	/**
	 * Whether the user may see program submissions (reviewer or site admin).
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function can_view_submissions( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		if ( ! user_can( $user, self::CAP ) ) {
			return false;
		}

		return self::is_active( $user->ID );
	}

	/**
	 * Whether the user may edit or delete registrations.
	 *
	 * Site administrators only — reviewers can view, not write.
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function can_manage_donors( $user = null ) {
		$user = self::resolve_user( $user );

		return $user && $user->ID && user_can( $user, 'manage_options' );
	}

	/**
	 * Whether a reviewer account is allowed to authenticate.
	 *
	 * Site admins are always treated as active.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_active( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return '1' !== get_user_meta( $user_id, self::DISABLED_META, true );
	}

	/**
	 * Blood donation frontend dashboard URL. Falls back to home if the page is missing.
	 *
	 * @return string
	 */
	public static function dashboard_url() {
		return self::page_url( self::stored_page_id( self::PAGE_OPTION ) );
	}

	/**
	 * Join Our Projects review dashboard URL.
	 *
	 * @return string
	 */
	public static function projects_dashboard_url() {
		return self::page_url( self::stored_page_id( self::PROJECTS_PAGE_OPTION ) );
	}

	/**
	 * Free Spectacles review dashboard URL.
	 *
	 * @return string
	 */
	public static function spectacles_dashboard_url() {
		return self::page_url( self::stored_page_id( self::SPECTACLES_PAGE_OPTION ) );
	}

	/**
	 * Payment dashboard URL.
	 *
	 * @return string
	 */
	public static function payment_dashboard_url() {
		return self::page_url( self::stored_page_id( self::PAYMENT_DASHBOARD_PAGE_OPTION ) );
	}

	/**
	 * Whether the user can access wp-admin.
	 *
	 * Only users with wp-admin access (administrators, editors, authors, etc.)
	 * and who are not blocked by reviewer lockdown may access.
	 *
	 * @param WP_User|int|null $user User or ID.
	 * @return bool
	 */
	public static function can_access_wp_admin( $user = null ) {
		$user = self::resolve_user( $user );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		if ( self::should_lock_admin( $user ) ) {
			return false;
		}

		return user_can( $user, 'read' );
	}

	/**
	 * Public form URL if a host page still exists. The form is a shortcode now.
	 *
	 * @return string
	 */
	public static function join_form_url() {
		$page_id = (int) get_option( self::JOIN_PAGE_OPTION, 0 );
		if ( $page_id ) {
			return self::page_url( $page_id );
		}

		return home_url( '/' );
	}

	/**
	 * Create missing dashboard pages once, then remember that bootstrap ran.
	 *
	 * Never edits or deletes an existing page.
	 */
	public static function bootstrap_pages() {
		self::ensure_page();
		self::ensure_projects_page();
		self::ensure_spectacles_page();
		self::ensure_payment_dashboard_page();
		self::remove_join_page();
		update_option( self::PAGES_OPTION, self::PAGES_VERSION, false );
	}

	/**
	 * Create or recover the page that hosts [lccl_blood_donation_admin].
	 *
	 * @return int Page ID or 0.
	 */
	public static function ensure_page() {
		return self::ensure_named_page(
			self::PAGE_OPTION,
			'blood-donation-admin',
			__( 'BLOOD DONATION ADMIN', 'lccl-de' ),
			'[lccl_blood_donation_admin]'
		);
	}

	/**
	 * Create or recover the page that hosts [lccl_our_projects_admin].
	 *
	 * @return int Page ID or 0.
	 */
	public static function ensure_projects_page() {
		return self::ensure_named_page(
			self::PROJECTS_PAGE_OPTION,
			'our-projects-admin',
			__( 'OUR PROJECTS ADMIN', 'lccl-de' ),
			'[lccl_our_projects_admin]'
		);
	}

	/**
	 * Create or recover the page that hosts [lccl_spectacles_registration_admin].
	 *
	 * @return int Page ID or 0.
	 */
	public static function ensure_spectacles_page() {
		return self::ensure_named_page(
			self::SPECTACLES_PAGE_OPTION,
			'spectacles-registration-admin',
			__( 'SPECTACLES REGISTRATION ADMIN', 'lccl-de' ),
			'[lccl_spectacles_registration_admin]'
		);
	}

	/**
	 * Create or recover the page that hosts [lccl_payment_dashboard].
	 *
	 * @return int Page ID or 0.
	 */
	public static function ensure_payment_dashboard_page() {
		return self::ensure_named_page(
			self::PAYMENT_DASHBOARD_PAGE_OPTION,
			'payment-dashboard',
			__( 'PAYMENT DASHBOARD', 'lccl-de' ),
			'[lccl_payment_dashboard]'
		);
	}

	/**
	 * Forget the old plugin-owned public form page option. Never delete the page.
	 */
	public static function remove_join_page() {
		if ( get_option( self::JOIN_PAGE_RETIRED ) ) {
			return;
		}

		delete_option( self::JOIN_PAGE_OPTION );
		update_option( self::JOIN_PAGE_RETIRED, 1, false );
	}

	/**
	 * Stored dashboard page ID if the post is still a usable page. Read-only.
	 *
	 * @param string $option Option that stores the page ID.
	 * @return int
	 */
	private static function stored_page_id( $option ) {
		$page_id = (int) get_option( $option, 0 );

		if ( $page_id && self::is_usable_page( get_post( $page_id ) ) ) {
			return $page_id;
		}

		return 0;
	}

	/**
	 * Permalink for a page ID, or home if it is missing.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	private static function page_url( $page_id ) {
		$page_id = (int) $page_id;

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Create the plugin dashboard page if the slug is free. Never edit or delete pages.
	 *
	 * @param string $option  Option that stores the page ID.
	 * @param string $slug    post_name.
	 * @param string $title   Page title when inserting.
	 * @param string $content Shortcode markup.
	 * @return int Page ID or 0.
	 */
	private static function ensure_named_page( $option, $slug, $title, $content ) {
		$page_id = self::stored_page_id( $option );
		if ( $page_id ) {
			return $page_id;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 5,
				'name'           => $slug,
			)
		);

		foreach ( $existing as $page ) {
			if ( ! self::is_usable_page( $page ) ) {
				continue;
			}

			update_option( $option, (int) $page->ID, false );
			return (int) $page->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_option( $option, (int) $page_id, false );

		return (int) $page_id;
	}

	/**
	 * Whether a post is a live page this plugin may keep using.
	 *
	 * @param mixed $page Post object.
	 * @return bool
	 */
	private static function is_usable_page( $page ) {
		return $page instanceof WP_Post && 'page' === $page->post_type && 'trash' !== $page->post_status;
	}

	/**
	 * Update the stored display name for an existing role.
	 *
	 * @param string $label Role name shown in wp-admin.
	 */
	private static function rename_role( $label ) {
		global $wp_roles;

		if ( ! $wp_roles instanceof WP_Roles ) {
			$wp_roles = wp_roles();
		}

		if ( ! $wp_roles instanceof WP_Roles || ! isset( $wp_roles->roles[ self::ROLE ] ) ) {
			return;
		}

		$wp_roles->roles[ self::ROLE ]['name'] = $label;
		$wp_roles->role_names[ self::ROLE ]    = $label;
		update_option( $wp_roles->role_key, $wp_roles->roles );
	}

	/**
	 * Normalise a user argument to a WP_User or null.
	 *
	 * @param WP_User|int|null $user User, ID, or null for the current user.
	 * @return WP_User|null
	 */
	private static function resolve_user( $user ) {
		if ( $user instanceof WP_User ) {
			return $user;
		}

		if ( null === $user ) {
			if ( ! function_exists( 'wp_get_current_user' ) ) {
				return null;
			}

			$current = wp_get_current_user();
			return $current && $current->ID ? $current : null;
		}

		$loaded = get_userdata( (int) $user );
		return $loaded ? $loaded : null;
	}
}
