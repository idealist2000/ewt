<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aktif CPT için "Seviye" taxonomy terimlerini rozet olarak yazdırır.
 */
function ewt_print_level_badges( $post_id, $post_type ) {
	$tax = $post_type . '_seviye';
	if ( ! taxonomy_exists( $tax ) ) return;
	$terms = get_the_terms( $post_id, $tax );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return;
	echo '<div class="ewt-level-badges">';
	foreach ( $terms as $term ) {
		echo '<a class="ewt-badge-level" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
	}
	echo '</div>';
}

/**
 * Breadcrumb (basit)
 */
function ewt_breadcrumb() {
	if ( is_front_page() ) return;
	echo '<nav class="ewt-breadcrumb" aria-label="breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Ana Sayfa</a>';
	if ( is_singular() ) {
		$pt = get_post_type();
		$obj = get_post_type_object( $pt );
		if ( $obj && $obj->has_archive ) {
			echo ' &raquo; <a href="' . esc_url( get_post_type_archive_link( $pt ) ) . '">' . esc_html( $obj->labels->name ) . '</a>';
		}
		echo ' &raquo; <span>' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_post_type_archive() ) {
		echo ' &raquo; <span>' . esc_html( post_type_archive_title( '', false ) ) . '</span>';
	} elseif ( is_tax() ) {
		echo ' &raquo; <span>' . esc_html( single_term_title( '', false ) ) . '</span>';
	}
	echo '</nav>';
}

/**
 * İÇİNDEKİLER (TOC) OLUŞTURUCU
 *
 * Render edilmiş içerikteki <h2> ve <h3> başlıklarını tarar, her birine (yoksa) bir "id" ekler
 * ve bu id'lere bağlanan bir İçindekiler kutusu (nav > details > ul) üretir.
 * En az 2 başlık yoksa TOC göstermeye değmeyeceği için boş döner.
 *
 * @param string $content Zaten 'the_content' filtrelerinden geçmiş (apply_filters ile render edilmiş) HTML.
 * @return array { 'content' => string (id'ler eklenmiş içerik), 'toc' => string (TOC HTML, yoksa boş) }
 */
function ewt_add_toc_anchors_and_build( $content ) {
	if ( ! $content || ! preg_match_all( '/<h([23])\b[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
		return array( 'content' => $content, 'toc' => '' );
	}

	if ( count( $matches ) < 2 ) {
		return array( 'content' => $content, 'toc' => '' ); // çok az başlık varsa TOC göstermeye değmez
	}

	$used_slugs = array();
	$toc_items  = array();

	foreach ( $matches as $m ) {
		$full_tag = $m[0];
		$level    = intval( $m[1] );
		$text     = trim( wp_strip_all_tags( $m[2] ) );
		if ( ! $text ) continue;

		if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $full_tag, $idm ) ) {
			// Başlıkta zaten bir id varsa onu kullan (üzerine yazma)
			$slug = $idm[1];
		} else {
			$slug = sanitize_title( $text );
			if ( ! $slug ) $slug = 'baslik';
			$base_slug = $slug;
			$i = 2;
			while ( in_array( $slug, $used_slugs, true ) ) {
				$slug = $base_slug . '-' . $i;
				$i++;
			}
			$new_tag = preg_replace( '/^<h([23])/i', '<h$1 id="' . esc_attr( $slug ) . '"', $full_tag, 1 );
			$content = str_replace( $full_tag, $new_tag, $content );
		}

		$used_slugs[] = $slug;
		$toc_items[]  = array( 'level' => $level, 'text' => $text, 'slug' => $slug );
	}

	if ( count( $toc_items ) < 2 ) {
		return array( 'content' => $content, 'toc' => '' );
	}

	$toc_html = '<nav class="ewt-toc" aria-label="İçindekiler"><details open><summary>📑 İçindekiler</summary><ul>';
	foreach ( $toc_items as $item ) {
		$class = $item['level'] === 3 ? ' class="ewt-toc-sub"' : '';
		$toc_html .= '<li' . $class . '><a href="#' . esc_attr( $item['slug'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
	}
	$toc_html .= '</ul></details></nav>';

	return array( 'content' => $content, 'toc' => $toc_html );
}
