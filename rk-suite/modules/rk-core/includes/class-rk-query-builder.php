<?php
/**
 * RK_Query_Builder — reusable query definitions (posts / terms / users) stored
 * as JSON, executable anywhere (e.g. the RK Elements Listing Grid can consume a
 * saved query by ID). JetEngine-style Query Builder, minimal scope.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Query_Builder {

	const OPTION = 'rk_core_queries';

	public static function all() {
		$d = get_option( self::OPTION, array() );
		return is_array( $d ) ? $d : array();
	}

	public static function get( $id ) {
		$id = sanitize_key( $id );
		foreach ( self::all() as $q ) {
			if ( isset( $q['id'] ) && $q['id'] === $id ) { return $q; }
		}
		return null;
	}

	/** id => name map for select controls. */
	public static function choices() {
		$out = array();
		foreach ( self::all() as $q ) { $out[ $q['id'] ] = $q['name']; }
		return $out;
	}

	public static function save( $input ) {
		$def = self::sanitize( $input );
		if ( is_wp_error( $def ) ) { return $def; }
		$all = self::all();
		$found = false;
		foreach ( $all as $i => $ex ) {
			if ( $ex['id'] === $def['id'] ) { $all[ $i ] = $def; $found = true; break; }
		}
		if ( ! $found ) { $all[] = $def; }
		update_option( self::OPTION, array_values( $all ) );
		return $def['id'];
	}

	public static function delete( $id ) {
		$id = sanitize_key( $id );
		$all = array();
		foreach ( self::all() as $q ) { if ( $q['id'] !== $id ) { $all[] = $q; } }
		update_option( self::OPTION, array_values( $all ) );
	}

	public static function sanitize( $in ) {
		$id   = isset( $in['id'] ) && '' !== $in['id'] ? sanitize_key( $in['id'] ) : sanitize_key( uniqid( 'qry_' ) );
		$name = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : 'Query';
		$src  = isset( $in['source'] ) ? sanitize_key( $in['source'] ) : 'posts';
		if ( ! in_array( $src, array( 'posts', 'terms', 'users' ), true ) ) { $src = 'posts'; }

		return array(
			'id'         => $id,
			'name'       => $name,
			'source'     => $src,
			'post_type'  => isset( $in['post_type'] ) ? sanitize_key( $in['post_type'] ) : 'post',
			'taxonomy'   => isset( $in['taxonomy'] ) ? sanitize_key( $in['taxonomy'] ) : '',
			'number'     => isset( $in['number'] ) ? max( 1, (int) $in['number'] ) : 10,
			'orderby'    => isset( $in['orderby'] ) ? sanitize_key( $in['orderby'] ) : 'date',
			'order'      => ( isset( $in['order'] ) && 'ASC' === strtoupper( $in['order'] ) ) ? 'ASC' : 'DESC',
			'meta_key'   => isset( $in['meta_key'] ) ? sanitize_text_field( $in['meta_key'] ) : '',
			'meta_value' => isset( $in['meta_value'] ) ? sanitize_text_field( $in['meta_value'] ) : '',
			'term'       => isset( $in['term'] ) ? sanitize_title( $in['term'] ) : '',
			'status'     => isset( $in['status'] ) ? sanitize_key( $in['status'] ) : 'publish',
		);
	}

	/** Execute a query def by id or array. Returns array of objects. */
	public static function results( $query ) {
		$q = is_array( $query ) ? $query : self::get( $query );
		if ( ! $q ) { return array(); }

		if ( 'terms' === $q['source'] ) {
			$args = array(
				'taxonomy'   => $q['taxonomy'] ? $q['taxonomy'] : 'category',
				'number'     => $q['number'],
				'orderby'    => 'date' === $q['orderby'] ? 'name' : $q['orderby'],
				'order'      => $q['order'],
				'hide_empty' => false,
			);
			$terms = get_terms( $args );
			return is_wp_error( $terms ) ? array() : $terms;
		}

		if ( 'users' === $q['source'] ) {
			$args = array( 'number' => $q['number'], 'orderby' => 'date' === $q['orderby'] ? 'registered' : $q['orderby'], 'order' => $q['order'] );
			if ( '' !== $q['meta_key'] ) { $args['meta_key'] = $q['meta_key']; if ( '' !== $q['meta_value'] ) { $args['meta_value'] = $q['meta_value']; } }
			$u = new WP_User_Query( $args );
			return $u->get_results();
		}

		// posts
		$args = array(
			'post_type'      => $q['post_type'] ? $q['post_type'] : 'post',
			'posts_per_page' => $q['number'],
			'orderby'        => $q['orderby'],
			'order'          => $q['order'],
			'post_status'    => $q['status'] ? $q['status'] : 'publish',
			'ignore_sticky_posts' => true,
		);
		if ( '' !== $q['meta_key'] ) {
			$mq = array( 'key' => $q['meta_key'] );
			if ( '' !== $q['meta_value'] ) { $mq['value'] = $q['meta_value']; }
			$args['meta_query'] = array( $mq );
		}
		if ( '' !== $q['taxonomy'] && '' !== $q['term'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => $q['taxonomy'], 'field' => 'slug', 'terms' => array( $q['term'] ) ) );
		}
		$wpq = new WP_Query( $args );
		return $wpq->posts;
	}
}
