<?php
/**
 * RK_Migrate_Marketplace — in-admin browser for community / premium bundles.
 *
 * The marketplace catalog is served by an external endpoint (filterable). With
 * no endpoint configured, a built-in starter catalog is shown so the UI is
 * functional out of the box; one-click install pulls a bundle zip into the
 * local library.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_Marketplace {

	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {}

	/** Catalog of installable bundles. */
	public static function catalog() {
		$endpoint = apply_filters( 'rk_migrate_marketplace_endpoint', '' );
		if ( $endpoint ) {
			$resp = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
			if ( ! is_wp_error( $resp ) ) {
				$data = json_decode( wp_remote_retrieve_body( $resp ), true );
				if ( isset( $data['items'] ) ) { return $data['items']; }
			}
		}
		return apply_filters( 'rk_migrate_marketplace_catalog', self::starter_catalog() );
	}

	private static function starter_catalog() {
		return array(
			array( 'id' => 'services-co', 'title' => 'Local Services Co.', 'category' => 'Services', 'price' => 'Free', 'pages' => 7, 'zip' => '', 'local' => 'services-co.zip', 'desc' => 'Home, services, about, contact + header/footer. Ideal for trades & contractors.' ),
			array( 'id' => 'resto-one', 'title' => 'Restaurant One', 'category' => 'Restaurant', 'price' => '$29', 'pages' => 6, 'zip' => '', 'desc' => 'Menu, reservations, gallery, story.' ),
			array( 'id' => 'saas-launch', 'title' => 'SaaS Launch', 'category' => 'SaaS', 'price' => '$39', 'pages' => 9, 'zip' => '', 'desc' => 'Landing, pricing, features, blog, docs shell.' ),
			array( 'id' => 'shop-starter', 'title' => 'Shop Starter (Woo)', 'category' => 'E-commerce', 'price' => '$49', 'pages' => 10, 'zip' => '', 'desc' => 'Shop, product, cart, checkout, account templates.' ),
		);
	}

	/** Install a catalog item by id (stores its bundle in the library). */
	public static function install( $id ) {
		foreach ( self::catalog() as $item ) {
			if ( $item['id'] !== $id ) { continue; }

			// 1) Bundle that ships inside the plugin (free templates).
			if ( ! empty( $item['local'] ) ) {
				$src = RK_MIGRATE_DIR . 'data/marketplace/' . basename( $item['local'] );
				if ( ! file_exists( $src ) ) { return new WP_Error( 'nolocal', 'Bundled template file is missing from the plugin.' ); }
				return RK_Migrate_Library::store_zip( $src, $item['title'] );
			}

			// 2) Remote bundle from a marketplace endpoint.
			if ( ! empty( $item['zip'] ) ) {
				$resp = wp_remote_get( $item['zip'], array( 'timeout' => 120 ) );
				if ( is_wp_error( $resp ) ) { return $resp; }
				$tmp = trailingslashit( RK_MIGRATE_EXPORT_DIR ) . 'mkt-' . sanitize_file_name( $id ) . '.zip';
				if ( ! file_exists( RK_MIGRATE_EXPORT_DIR ) ) { wp_mkdir_p( RK_MIGRATE_EXPORT_DIR ); }
				file_put_contents( $tmp, wp_remote_retrieve_body( $resp ) );
				$slug = RK_Migrate_Library::store_zip( $tmp, $item['title'] );
				@unlink( $tmp );
				return $slug;
			}

			// 3) Premium item with no local bundle and no marketplace server.
			return new WP_Error( 'premium', 'This is a premium template. Free templates install directly; premium bundles need the RK marketplace server, which is not configured on this site.' );
		}
		return new WP_Error( 'notfound', 'Catalog item not found.' );
	}
}
