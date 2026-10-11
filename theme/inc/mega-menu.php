<?php
/**
 * MEGA MENU
 *
 * Standart WordPress "Görünüm > Menüler" ekranından yönetilir (dashboarddan değiştirilebilir).
 * Her üst seviye (top-level) menü öğesinin "Açıklama" (Description) alanına
 *   sütun-başlığı olarak kullanılacak kısa bir metin,
 * ve menü öğesi CSS sınıfına "mega-icon-XXX" yazarsanız dashicon ikon adı olarak kullanılır.
 * Alt menüler otomatik olarak mega-menu sütunlarına dönüşür (2. seviye = sütun başlığı, 3. seviye = link).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Menü öğesi düzenleme ekranına "Sütun İkonu" ve "Öne Çıkan (Highlight)" alanı ekle
 */
function ewt_nav_menu_item_custom_fields( $item_id, $item, $depth, $args ) {
	$icon      = get_post_meta( $item_id, '_ewt_menu_icon', true );
	$highlight = get_post_meta( $item_id, '_ewt_menu_highlight', true );
	?>
	<p class="field-ewt-icon description description-wide">
		<label for="edit-menu-item-ewt-icon-<?php echo esc_attr( $item_id ); ?>">
			Dashicon adı (opsiyonel, örn: dashicons-headphones)<br />
			<input type="text" id="edit-menu-item-ewt-icon-<?php echo esc_attr( $item_id ); ?>"
				class="widefat code" name="ewt-menu-icon[<?php echo esc_attr( $item_id ); ?>]"
				value="<?php echo esc_attr( $icon ); ?>" />
		</label>
	</p>
	<p class="field-ewt-highlight description description-thin">
		<label for="edit-menu-item-ewt-highlight-<?php echo esc_attr( $item_id ); ?>">
			<input type="checkbox" id="edit-menu-item-ewt-highlight-<?php echo esc_attr( $item_id ); ?>"
				name="ewt-menu-highlight[<?php echo esc_attr( $item_id ); ?>]" value="1"
				<?php checked( $highlight, '1' ); ?> />
			Bu öğeyi mega menüde "öne çıkan / yeni" rozetiyle göster
		</label>
	</p>
	<?php
}
add_action( 'wp_nav_menu_item_custom_fields', 'ewt_nav_menu_item_custom_fields', 10, 4 );

function ewt_save_nav_menu_item_custom_fields( $menu_id, $menu_item_db_id ) {
	if ( isset( $_POST['ewt-menu-icon'][ $menu_item_db_id ] ) ) {
		update_post_meta( $menu_item_db_id, '_ewt_menu_icon', sanitize_text_field( $_POST['ewt-menu-icon'][ $menu_item_db_id ] ) );
	}
	$highlight = isset( $_POST['ewt-menu-highlight'][ $menu_item_db_id ] ) ? '1' : '';
	update_post_meta( $menu_item_db_id, '_ewt_menu_highlight', $highlight );
}
add_action( 'wp_update_nav_menu_item', 'ewt_save_nav_menu_item_custom_fields', 10, 2 );

/**
 * Mega Menu Walker
 * - 1. seviye: üst menü (Listening, Speaking, TOEFL, vb.)
 * - 2. seviye: mega panel içindeki sütun başlıkları
 * - 3. seviye: sütun içindeki linkler
 */
class EWT_Mega_Menu_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth === 0 ) {
			$output .= '<div class="ewt-mega-panel"><div class="ewt-mega-panel-inner">';
		} else {
			$output .= '<ul class="ewt-mega-sublist">';
		}
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		if ( $depth === 0 ) {
			$output .= '</div></div>';
		} else {
			$output .= '</ul>';
		}
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$icon      = get_post_meta( $item->ID, '_ewt_menu_icon', true );
		$highlight = get_post_meta( $item->ID, '_ewt_menu_highlight', true );
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );

		if ( $depth === 0 ) {
			$output .= '<li class="ewt-menu-item' . ( $has_children ? ' has-mega' : '' ) . '">';
			$output .= '<a href="' . esc_url( $item->url ) . '" class="ewt-menu-link">';
			if ( $icon ) $output .= '<span class="dashicons ' . esc_attr( $icon ) . '"></span> ';
			$output .= esc_html( $item->title );
			if ( $has_children ) $output .= '<span class="ewt-caret">▾</span>';
			$output .= '</a>';
		} elseif ( $depth === 1 ) {
			// ÖNEMLİ: 2. seviye öğe her zaman GERÇEK bir <a> linkidir (sütun başlığı GÖREVİ de görür).
			// Menünüzde yalnızca 2 seviye varsa (3. seviye eklemediyseniz) bu öğe doğrudan tıklanabilir bir link olarak kalır.
			// 3. seviye alt öğeleri varsa, bu link altında ayrıca bir bağlantı listesi açılır.
			$wrap_class = $has_children ? 'ewt-mega-col has-links' : 'ewt-mega-col';
			$output .= '<div class="' . esc_attr( $wrap_class ) . '">';
			$output .= '<a href="' . esc_url( $item->url ) . '" class="ewt-mega-col-title">';
			if ( $icon ) $output .= '<span class="dashicons ' . esc_attr( $icon ) . '"></span> ';
			$output .= esc_html( $item->title );
			$output .= '</a>';
			if ( $highlight ) $output .= ' <span class="ewt-badge">Yeni</span>';
		} else {
			$output .= '<li class="ewt-mega-link-item">';
			$output .= '<a href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a>';
			if ( $highlight ) $output .= ' <span class="ewt-badge">Yeni</span>';
		}
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		if ( $depth === 0 ) {
			$output .= '</li>';
		} elseif ( $depth === 1 ) {
			$output .= '</div>';
		} else {
			$output .= '</li>';
		}
	}
}

/**
 * Mega menüyü çıktılayan yardımcı fonksiyon (header.php içinde çağrılır)
 */
function ewt_render_mega_menu() {
	if ( ! has_nav_menu( 'primary' ) ) {
		echo '<p class="ewt-menu-notice">Ana Mega Menü tanımlı değil. Görünüm &gt; Menüler ekranından "Ana Mega Menü" konumuna bir menü atayın.</p>';
		return;
	}

	wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'items_wrap'     => '<ul id="ewt-primary-menu" class="ewt-mega-menu">%3$s</ul>',
		'depth'          => 3,
		'walker'         => new EWT_Mega_Menu_Walker(),
	) );
}
