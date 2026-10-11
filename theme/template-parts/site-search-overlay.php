<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Tam ekran canlı arama paneli. footer.php içinde bir kez çağrılır.
 * Açma/kapama ve arama mantığı assets/js/site-search.js içindedir.
 */
?>
<div class="ewt-search-overlay" id="ewt-search-overlay" aria-hidden="true">
	<div class="ewt-search-overlay-backdrop" data-ewt-search-close></div>
	<div class="ewt-search-overlay-panel" role="dialog" aria-modal="true" aria-label="Site içi arama">
		<div class="ewt-search-overlay-top">
			<span class="ewt-search-overlay-icon" aria-hidden="true">🔍</span>
			<label class="screen-reader-text" for="ewt-search-input">Sitede ara</label>
			<input
				type="text"
				id="ewt-search-input"
				class="ewt-search-overlay-input"
				autocomplete="off"
				role="combobox"
				aria-expanded="false"
				aria-controls="ewt-search-results-list"
				aria-autocomplete="list"
			/>
			<button type="button" class="ewt-search-overlay-close" id="ewt-search-close-btn" aria-label="Kapat">✕</button>
		</div>
		<div class="ewt-search-overlay-body">
			<ul id="ewt-search-results-list" class="ewt-search-results-list" role="listbox"></ul>
			<div id="ewt-search-status" class="ewt-search-status" aria-live="polite"></div>
		</div>
		<div class="ewt-search-overlay-footer">
			<a href="#" id="ewt-search-view-all" class="ewt-search-view-all" style="display:none;">Tüm sonuçları gör →</a>
			<span class="ewt-search-hint">Kapatmak için <kbd>ESC</kbd> · Açmak için <kbd>Ctrl/Cmd</kbd>+<kbd>K</kbd></span>
		</div>
	</div>
</div>
