<?php
namespace RK\SEO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Metabox — optional per-post SEO overrides. Leave blank and RK SEO keeps
 * auto-generating everything; fill a field to override just that one signal.
 * Also the destination for imported Yoast / Rank Math data.
 */
class Metabox {

	const T_TITLE = '_rk_seo_title';
	const T_DESC  = '_rk_seo_desc';
	const T_NOIDX = '_rk_seo_noindex';
	const T_CANON = '_rk_seo_canonical';
	const T_OGIMG = '_rk_seo_og_image';

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) { return; }
		wp_enqueue_media();
	}

	public function add() {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
			if ( 'attachment' === $pt ) { continue; }
			add_meta_box( 'rk-seo-box', 'RK SEO', array( $this, 'render' ), $pt, 'normal', 'default' );
		}
	}

	/** Read a single override for a post (empty string when unset). */
	public static function get( $post_id, $key ) {
		$v = get_post_meta( $post_id, $key, true );
		return is_string( $v ) ? $v : ( $v ? (string) $v : '' );
	}

	public function render( $post ) {
		wp_nonce_field( 'rk_seo_metabox', 'rk_seo_metabox_nonce' );
		$title = self::get( $post->ID, self::T_TITLE );
		$desc  = self::get( $post->ID, self::T_DESC );
		$noidx = self::get( $post->ID, self::T_NOIDX );
		$canon = self::get( $post->ID, self::T_CANON );
		$ogimg = self::get( $post->ID, self::T_OGIMG );

		echo '<style>.rk-seo-mb label{font-weight:600;display:block;margin:12px 0 4px}.rk-seo-mb input[type=text],.rk-seo-mb textarea{width:100%}.rk-seo-mb .rk-count{float:right;font-weight:400;color:#787c82;font-size:11px}.rk-seo-mb .rk-hint{color:#787c82;font-size:12px;margin:3px 0 0}</style>';
		echo '<div class="rk-seo-mb">';
		echo '<label>SEO title <span class="rk-count" data-for="rkt">' . esc_html( strlen( $title ) ) . '</span></label>';
		echo '<input type="text" id="rkt" name="rk_seo_title" value="' . esc_attr( $title ) . '" maxlength="120" oninput="document.querySelector(\'[data-for=rkt]\').textContent=this.value.length" placeholder="Leave blank to auto-generate from the title" />';
		echo '<label>Meta description <span class="rk-count" data-for="rkd">' . esc_html( strlen( $desc ) ) . '</span></label>';
		echo '<textarea id="rkd" name="rk_seo_desc" rows="3" maxlength="320" oninput="document.querySelector(\'[data-for=rkd]\').textContent=this.value.length" placeholder="Leave blank to auto-extract from the content">' . esc_textarea( $desc ) . '</textarea>';
		echo '<p class="rk-hint">Aim for ~50–60 chars (title) and ~150–160 chars (description).</p>';
		echo '<label><input type="checkbox" name="rk_seo_noindex" value="1" ' . checked( '1', $noidx, false ) . ' /> Hide this from search engines (noindex)</label>';
		echo '<label>Canonical URL</label>';
		echo '<input type="text" name="rk_seo_canonical" value="' . esc_attr( $canon ) . '" placeholder="Leave blank to use this page\'s URL" />';
		echo '<label>Social share image URL</label>';
		echo '<div class="rk-ogimg-row" style="display:flex;gap:6px;align-items:center">';
		echo '<input type="text" id="rk_seo_og_image" name="rk_seo_og_image" value="' . esc_attr( $ogimg ) . '" placeholder="Leave blank to use the featured image" style="flex:1" />';
		echo '<button type="button" class="button rk-ogimg-pick">Choose</button>';
		echo '<button type="button" class="button rk-ogimg-clear" title="Clear">&times;</button>';
		echo '</div>';
		echo '<p class="rk-hint">Pick from the media library, paste a URL, or leave blank to auto-use this page\'s featured image.</p>';
		echo '<div class="rk-ogimg-preview" style="margin-top:8px">' . ( $ogimg ? '<img src="' . esc_url( $ogimg ) . '" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:6px" />' : '' ) . '</div>';
		echo '<script>(function(){var w=document.getElementById("rk_seo_og_image");if(!w)return;var box=w.closest(".rk-seo-mb");var pv=box.querySelector(".rk-ogimg-preview");function show(u){pv.innerHTML=u?\'<img src="\'+u+\'" style="max-width:180px;height:auto;border:1px solid #dcdcde;border-radius:6px" />\':"";}box.querySelector(".rk-ogimg-pick").addEventListener("click",function(e){e.preventDefault();if(!window.wp||!wp.media){return;}var fr=wp.media({title:"Select social share image",button:{text:"Use image"},multiple:false});fr.on("select",function(){var a=fr.state().get("selection").first().toJSON();w.value=a.url;show(a.url);});fr.open();});box.querySelector(".rk-ogimg-clear").addEventListener("click",function(e){e.preventDefault();w.value="";show("");});})();</script>';
		echo '</div>';
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['rk_seo_metabox_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['rk_seo_metabox_nonce'] ), 'rk_seo_metabox' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$set = function ( $key, $value ) use ( $post_id ) {
			$value = trim( (string) $value );
			if ( '' === $value ) { delete_post_meta( $post_id, $key ); } else { update_post_meta( $post_id, $key, $value ); }
		};
		$set( self::T_TITLE, isset( $_POST['rk_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['rk_seo_title'] ) ) : '' );
		$set( self::T_DESC,  isset( $_POST['rk_seo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rk_seo_desc'] ) ) : '' );
		$set( self::T_CANON, isset( $_POST['rk_seo_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['rk_seo_canonical'] ) ) : '' );
		$set( self::T_OGIMG, isset( $_POST['rk_seo_og_image'] ) ? esc_url_raw( wp_unslash( $_POST['rk_seo_og_image'] ) ) : '' );
		if ( isset( $_POST['rk_seo_noindex'] ) ) { update_post_meta( $post_id, self::T_NOIDX, '1' ); }
		else { delete_post_meta( $post_id, self::T_NOIDX ); }
	}
}
