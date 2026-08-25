<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Schema — one connected JSON-LD @graph per page. Entities are linked by @id
 * so search engines understand relationships: Article → isPartOf WebPage →
 * partOf WebSite → publisher Organization; author → Person; plus BreadcrumbList.
 */
class Schema {

	public function hooks() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	private function id( $suffix ) { return home_url( '/' ) . '#' . $suffix; }

	public function output() {
		if ( ! Helpers::should_run() ) { return; }
		$graph = array();

		$org  = $this->organization();
		$site = $this->website( $org['@id'] );
		$graph[] = $org;
		$graph[] = $site;

		if ( is_singular() ) {
			$post = get_queried_object();
			$page = $this->webpage( $post, $site['@id'] );
			$bc   = $this->breadcrumb_list();
			if ( $bc ) { $page['breadcrumb'] = array( '@id' => $bc['@id'] ); }
			$graph[] = $page;

			$person = $this->person( (int) $post->post_author );
			$graph[] = $person;

			if ( is_singular( 'post' ) ) {
				$graph[] = $this->article( $post, $page['@id'], $org['@id'], $person['@id'] );
			}
			if ( $bc ) { $graph[] = $bc; }
		} elseif ( is_author() ) {
			$graph[] = $this->profile_page( $site['@id'] );
			$graph[] = $this->person( get_queried_object_id() );
		} elseif ( is_category() || is_tag() || is_tax() || is_post_type_archive() || is_home() ) {
			$graph[] = $this->collection_page( $site['@id'] );
			$bc = $this->breadcrumb_list();
			if ( $bc ) { $graph[] = $bc; }
		}

		$data = array( '@context' => 'https://schema.org', '@graph' => array_values( $graph ) );
		echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n";
	}

	private function organization() {
		$e = Helpers::site_entity();
		$node = array(
			'@type' => 'Organization',
			'@id'   => $this->id( 'organization' ),
			'name'  => $e['name'],
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $e['logo'] ) ) {
			$node['logo'] = array(
				'@type'   => 'ImageObject',
				'@id'     => $this->id( 'logo' ),
				'url'     => $e['logo'],
				'caption' => $e['name'],
			);
			$node['image'] = array( '@id' => $this->id( 'logo' ) );
		}
		return $node;
	}

	private function website( $org_id ) {
		return array(
			'@type'     => 'WebSite',
			'@id'       => $this->id( 'website' ),
			'url'       => home_url( '/' ),
			'name'      => Helpers::site_name(),
			'publisher' => array( '@id' => $org_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ),
				'query-input' => 'required name=search_term_string',
			),
			'inLanguage' => get_bloginfo( 'language' ),
		);
	}

	private function webpage( $post, $site_id ) {
		$url  = get_permalink( $post );
		$node = array(
			'@type'      => 'WebPage',
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => get_the_title( $post ),
			'isPartOf'   => array( '@id' => $site_id ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		if ( has_post_thumbnail( $post ) ) {
			$node['primaryImageOfPage'] = array( '@id' => $url . '#primaryimage' );
			$node['image'] = array(
				'@type' => 'ImageObject',
				'@id'   => $url . '#primaryimage',
				'url'   => get_the_post_thumbnail_url( $post, 'full' ),
			);
		}
		return $node;
	}

	private function article( $post, $page_id, $org_id, $person_id ) {
		$url  = get_permalink( $post );
		$node = array(
			'@type'            => 'Article',
			'@id'              => $url . '#article',
			'isPartOf'         => array( '@id' => $page_id ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array( '@id' => $person_id ),
			'publisher'        => array( '@id' => $org_id ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);
		if ( has_post_thumbnail( $post ) ) { $node['image'] = array( '@id' => $url . '#primaryimage' ); }
		$cats = get_the_category( $post->ID );
		if ( $cats ) { $node['articleSection'] = wp_list_pluck( $cats, 'name' ); }
		return $node;
	}

	private function person( $user_id ) {
		return array(
			'@type' => 'Person',
			'@id'   => $this->id( 'author-' . (int) $user_id ),
			'name'  => get_the_author_meta( 'display_name', $user_id ),
			'url'   => get_author_posts_url( $user_id ),
		);
	}

	private function profile_page( $site_id ) {
		$a = get_queried_object();
		return array(
			'@type'    => 'ProfilePage',
			'@id'      => Helpers::current_url() . '#webpage',
			'url'      => Helpers::current_url(),
			'name'     => $a ? $a->display_name : '',
			'isPartOf' => array( '@id' => $site_id ),
			'mainEntity' => array( '@id' => $this->id( 'author-' . get_queried_object_id() ) ),
		);
	}

	private function collection_page( $site_id ) {
		return array(
			'@type'    => 'CollectionPage',
			'@id'      => Helpers::current_url() . '#webpage',
			'url'      => Helpers::current_url(),
			'name'     => wp_get_document_title(),
			'isPartOf' => array( '@id' => $site_id ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
	}

	/** Breadcrumb graph node built from the shared Breadcrumbs trail. */
	private function breadcrumb_list() {
		if ( ! class_exists( '\RK\SEO\Breadcrumbs' ) ) { return null; }
		$items = Breadcrumbs::trail();
		if ( count( $items ) < 2 ) { return null; }
		$list = array();
		$pos  = 1;
		foreach ( $items as $it ) {
			$entry = array( '@type' => 'ListItem', 'position' => $pos, 'name' => $it['name'] );
			if ( ! empty( $it['url'] ) ) { $entry['item'] = $it['url']; }
			$list[] = $entry;
			$pos++;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => Helpers::current_url() . '#breadcrumb',
			'itemListElement' => $list,
		);
	}
}
