<?php
/** Shared, deterministic SEO rules. Never invent counts or rewrite lesson prose to stuff keywords. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ewt_seo_lower( $text ) {
	return mb_strtolower( strtr( (string) $text, array( 'İ' => 'i', 'I' => 'ı' ) ), 'UTF-8' );
}
function ewt_seo_case( $text ) {
	$acronyms = array( 'toefl', 'ielts', 'ydt', 'yds', 'yökdil', 'gre', 'gmat', 'cefr', 'seo', 'pdf', 'bbc', 'it' );
	return preg_replace_callback( '/[\p{L}\p{N}]+/u', function ( $m ) use ( $acronyms ) {
		$lower = ewt_seo_lower( $m[0] );
		if ( in_array( $lower, $acronyms, true ) || in_array( strtolower( remove_accents( $m[0] ) ), $acronyms, true ) || preg_match( '/^[abc][12]$/', $lower ) ) return $lower === 'yökdil' ? 'YÖKDİL' : mb_strtoupper( $lower, 'UTF-8' );
		$first = mb_substr( $lower, 0, 1 );
		return ( $first === 'ı' ? 'I' : ( $first === 'i' ? 'İ' : mb_strtoupper( $first, 'UTF-8' ) ) ) . mb_substr( $lower, 1 );
	}, sanitize_text_field( $text ) );
}
function ewt_seo_focus( $id ) {
	$fk = get_post_meta( $id, '_ewt_focus_keyword', true );
	if ( ! is_string( $fk ) || ! trim( $fk ) ) {
		$fk = get_post_meta( $id, 'rank_math_focus_keyword', true );
		$fk = is_string( $fk ) ? explode( ',', $fk )[0] : '';
	}
	return sanitize_text_field( $fk );
}
function ewt_seo_content_text( $html ) {
	$html = preg_replace( '#<(script|style|code|pre)\b[^>]*>.*?</\1>#is', ' ', (string) $html );
	$html = preg_replace( '#</?(?:p|h[1-6]|li|div|br|tr|td|th|section|aside|ul|ol)\b[^>]*>#i', ' ', $html );
	return preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}
function ewt_seo_density( $html, $fk ) {
	$text = ewt_seo_content_text( $html );
	preg_match_all( '/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', $text, $words );
	$count = 0;
	if ( trim( $fk ) ) preg_match_all( ewt_link_build_pattern( $fk ), $text, $matches );
	if ( isset( $matches ) ) $count = count( $matches[0] );
	$total = count( $words[0] );
	return array( 'words' => $total, 'occurrences' => $count, 'percent' => $total ? round( 100 * $count / $total, 2 ) : 0, 'target' => 1.9, 'suggested_occurrences' => max( 0, (int) round( $total * .019 ) ) );
}
function ewt_seo_build_title( $fk, $title, $content = '', $level = '' ) {
	$prefix = ewt_seo_case( $fk );
	if ( mb_strlen( $prefix ) > EWT_SEO_TITLE_MAX ) return new WP_Error( 'focus_too_long', 'Focus keyword 55 karakteri aşıyor. Tam anahtar kelimeyi koruyan bir başlık için FK’yı kısaltın.' );
	if ( ! $prefix ) return mb_substr( ewt_seo_case( $title ), 0, EWT_SEO_TITLE_MAX );
	$title = ewt_seo_case( $title );
	$tail = $title;
	if ( mb_substr( ewt_seo_lower( $title ), 0, mb_strlen( $prefix ) ) === ewt_seo_lower( $prefix ) ) $tail = trim( mb_substr( $title, mb_strlen( $prefix ) ), " -–—:|" );
	preg_match_all( '#<h2\b[^>]*>.*?</h2>#is', $content, $sections );
	$count = count( $sections[0] );
	$suffix = '';
	// A section count is verifiable from the actual source, unlike an invented promise.
	if ( $count >= 2 ) $suffix = $count . ' Bölüm';
	elseif ( in_array( $level, array( 'a1', 'a2' ), true ) || preg_match( '/\b(kolay|easy)\b/iu', $fk . ' ' . ewt_seo_content_text( $content ) ) ) $suffix = 'Kolay Anlatım';
	elseif ( preg_match( '/\b(hata|hatalar|mistakes)\b/iu', $fk . ' ' . ewt_seo_content_text( $content ) ) ) $suffix = 'Yaygın Hatalar';
	else $suffix = 'Rehber';
	if ( mb_strpos( ewt_seo_lower( $prefix . ' ' . $tail ), ewt_seo_lower( $suffix ) ) !== false ) $suffix = '';
	if ( $suffix && mb_strlen( $prefix . ' - ' . $suffix ) > EWT_SEO_TITLE_MAX ) {
		foreach ( array( 'Kolay', 'Rehber' ) as $fallback ) {
			if ( 'Kolay' === $fallback && ! in_array( $level, array( 'a1', 'a2' ), true ) ) continue;
			if ( mb_strlen( $prefix . ' ' . $fallback ) <= EWT_SEO_TITLE_MAX ) { $suffix = $fallback; break; }
		}
		if ( mb_strlen( $prefix . ' ' . $suffix ) > EWT_SEO_TITLE_MAX ) $suffix = '';
	}
	$separator = $suffix && mb_strlen( $prefix . ' - ' . $suffix ) <= EWT_SEO_TITLE_MAX ? ' - ' : ' ';
	$room = EWT_SEO_TITLE_MAX - mb_strlen( $prefix ) - ( $suffix ? mb_strlen( $separator . $suffix ) : 0 );
	$kept = '';
	foreach ( preg_split( '/\s+/u', trim( $tail ) ) as $word ) {
		$candidate = trim( $kept . ' ' . $word );
		if ( $candidate && mb_strlen( ' - ' . $candidate ) <= $room ) $kept = $candidate;
		else break;
	}
	return $prefix . ( $kept ? ' - ' . $kept : '' ) . ( $suffix ? $separator . $suffix : '' );
}
function ewt_seo_image_alt( $description, $fk ) {
	$description = sanitize_text_field( $description ); $fk = sanitize_text_field( $fk );
	if ( ! $fk ) return $description;
	if ( preg_match( ewt_link_build_pattern( $fk ), $description ) ) return $description;
	return trim( $description ) ? $description . ' — ' . $fk : $fk;
}
function ewt_seo_resource_html() {
	$resources = array(
		'Wikipedia: İngilizceyi ikinci dil olarak öğrenme' => 'https://en.wikipedia.org/wiki/English_as_a_second_or_foreign_language',
		'Medium: İngilizce öğrenme yazıları' => 'https://medium.com/tag/english-language-learning',
		'British Council LearnEnglish' => 'https://learnenglish.britishcouncil.org/',
		'BBC Learning English' => 'https://www.bbc.co.uk/learningenglish',
		'Purdue OWL: Akademik yazma (.edu)' => 'https://owl.purdue.edu/',
		'American English: Dil öğrenme kaynakları (.gov)' => 'https://americanenglish.state.gov/',
	);
	$html = '<aside class="ewt-learning-resources" data-ewt-seo-resources="1"><h3>İleri Okuma ve İngilizce Öğrenme Kaynakları</h3><p>Konuyu geliştirmek için ek okuma önerileri:</p><ul>';
	foreach ( $resources as $label => $url ) $html .= '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer' . ( strpos( $url, 'medium.com' ) !== false ? ' nofollow' : '' ) . '">' . esc_html( $label ) . '</a></li>';
	return $html . '</ul></aside>';
}
function ewt_seo_tag_attributes( $tag, $fk, $home_host ) {
	if ( 'IMG' === $tag->get_tag() && $fk && 'presentation' !== $tag->get_attribute( 'role' ) && 'true' !== $tag->get_attribute( 'aria-hidden' ) ) {
		$tag->set_attribute( 'alt', ewt_seo_image_alt( (string) $tag->get_attribute( 'alt' ), $fk ) );
	}
	if ( 'A' === $tag->get_tag() ) {
		$url = (string) $tag->get_attribute( 'href' );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( $host && strtolower( $host ) !== $home_host && ( in_array( $scheme, array( 'http', 'https' ), true ) || 0 === strpos( $url, '//' ) ) ) {
			$tag->set_attribute( 'target', '_blank' );
			$rel = preg_split( '/\s+/', trim( (string) $tag->get_attribute( 'rel' ) ) );
			$tag->set_attribute( 'rel', implode( ' ', array_unique( array_filter( array_merge( $rel, array( 'noopener', 'noreferrer' ) ) ) ) ) );
		}
	}
}
function ewt_seo_prepare_content( $content, $fk, $resources = true ) {
	$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor = new WP_HTML_Tag_Processor( $content );
		while ( $processor->next_tag() ) ewt_seo_tag_attributes( $processor, $fk, $host );
		$content = $processor->get_updated_html();
	} elseif ( class_exists( 'DOMDocument' ) ) {
		$dom = ewt_link_html_to_dom( $content );
		if ( $dom ) {
			foreach ( $dom->getElementsByTagName( 'img' ) as $img ) if ( $fk && $img->getAttribute('role') !== 'presentation' && $img->getAttribute('aria-hidden') !== 'true' ) $img->setAttribute('alt',ewt_seo_image_alt($img->getAttribute('alt'),$fk));
			foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
				$url=$a->getAttribute('href');$link_host=wp_parse_url($url,PHP_URL_HOST);
				if ($link_host && strtolower($link_host)!==$host && preg_match('#^(?:https?:)?//#i',$url)) { $a->setAttribute('target','_blank');$a->setAttribute('rel',implode(' ',array_unique(array_filter(array_merge(preg_split('/\s+/',trim($a->getAttribute('rel'))),array('noopener','noreferrer')))))); }
			}
			$content=ewt_link_dom_to_html($dom);
		}
	}
	if ( $resources && trim( $content ) && strpos( $content, 'data-ewt-seo-resources' ) === false ) {
		$block = ewt_seo_resource_html();
		$content .= has_blocks( $content ) ? "\n<!-- wp:html -->\n" . $block . "\n<!-- /wp:html -->" : "\n" . $block;
	}
	return $content;
}
function ewt_seo_prompt_rules( $fk, $words = 400 ) {
	$fk = sanitize_text_field( $fk );
	return "\nSEO: Odak anahtar kelime “" . $fk . '”. Tam ifadenin görülme sayısı / toplam kelime sayısı ölçümünde yaklaşık %1,9 yoğunluk hedefle (yaklaşık ' . max( 1, (int) round( (int) $words * .019 ) ) . ' doğal kullanım). Başlık, giriş ve uygun bölümlerde bağlama uygun kullan; aynı cümleyi tekrarlama, anahtar kelime doldurma yapma. Yoğunluğu tutturmak için dersin anlamını bozma. Görsellerin açıklayıcı alt metinlerine bu ifadeyi ekle. Kaynak bağlantısı gerekiyorsa gerçek, bilinen eğitim sayfalarını kullan; URL uydurma.';
}
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( ewt_seo_supported_post_types() ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) return $content;
	return ewt_seo_prepare_content( $content, ewt_seo_focus( get_the_ID() ) );
}, 30 );
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( is_admin() || ! is_singular( ewt_seo_supported_post_types() ) || strpos( $attr['class'] ?? '', 'custom-logo' ) !== false ) return $attr;
	$fk = ewt_seo_focus( get_queried_object_id() );
	if ( $fk && 'presentation' !== ( $attr['role'] ?? '' ) && 'true' !== ( $attr['aria-hidden'] ?? '' ) ) $attr['alt'] = ewt_seo_image_alt( $attr['alt'] ?? '', $fk );
	return $attr;
}, 20, 2 );
function ewt_seo_current_title( $original ) {
	if ( get_query_var( 'ewt_path' ) || get_query_var( 'ewt_nostalgia' ) || ! is_singular( ewt_seo_supported_post_types() ) ) return $original;
	$id = get_queried_object_id(); $fk = ewt_seo_focus( $id );
	if ( ! $fk ) return $original;
	$title = ewt_seo_build_title( $fk, $original, get_post_field( 'post_content', $id ), get_post_meta( $id, '_ewt_path_level', true ) );
	return is_wp_error( $title ) ? $original : $title;
}
add_filter( 'rank_math/frontend/title', 'ewt_seo_current_title', 99 );
add_filter( 'pre_get_document_title', 'ewt_seo_current_title', 99 );
add_action( 'rank_math/after_save_post', function ( $id ) {
	$fk = get_post_meta( $id, 'rank_math_focus_keyword', true );
	if ( ! is_string( $fk ) || ! $fk ) return;
	ewt_apply_seo_fields( $id, trim( explode( ',', $fk )[0] ), get_post_meta( $id, 'rank_math_title', true ), get_post_meta( $id, 'rank_math_description', true ), false );
} );

function ewt_seo_sync_content( $id, $fk ) {
	static $busy = array();
	if ( isset( $busy[$id] ) || ! $fk ) return;
	$busy[$id] = true;
	try {
		$original = get_post_field( 'post_content', $id );
		$prepared = ewt_seo_prepare_content( $original, $fk );
		if ( $original !== $prepared ) wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $prepared ) ) );
		update_post_meta( $id, '_ewt_seo_density', ewt_seo_density( $prepared, $fk ) );
		$images = get_posts( array( 'post_type'=>'attachment', 'post_mime_type'=>'image', 'post_status'=>'inherit', 'post_parent'=>$id, 'numberposts'=>-1, 'fields'=>'ids' ) );
		foreach ( $images as $image_id ) {
			$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			update_post_meta( $image_id, '_wp_attachment_image_alt', ewt_seo_image_alt( $alt, $fk ) );
		}
	} finally { unset( $busy[$id] ); }
}
add_action( 'save_post', function ( $id ) {
	static $busy = array();
	if ( isset( $busy[$id] ) || wp_is_post_revision($id) || wp_is_post_autosave($id) || ! current_user_can('edit_post',$id) || ! in_array(get_post_type($id),ewt_seo_supported_post_types(),true) ) return;
	$fk = ewt_seo_focus($id); if (!$fk) return;
	$busy[$id]=true;
	try { ewt_apply_seo_fields($id,$fk,get_post_meta($id,'_ewt_seo_title',true),get_post_meta($id,'_ewt_seo_desc',true),false); }
	finally {unset($busy[$id]);}
}, 90 );
