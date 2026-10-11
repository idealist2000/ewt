<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<form role="search" method="get" class="ewt-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="ewt-search-form-input">Ara</label>
	<input
		type="search"
		id="ewt-search-form-input"
		class="ewt-search-form-input"
		placeholder="Ne öğrenmek istersin?"
		value="<?php echo get_search_query(); ?>"
		name="s"
	/>
	<button type="submit" class="ewt-search-form-submit" aria-label="Ara">🔍</button>
</form>
