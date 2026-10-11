jQuery(function ($) {
	var $fk    = $('#ewt_focus_keyword');
	var $title = $('#ewt_seo_title');
	var $desc  = $('#ewt_seo_desc');
	var titleMax = (typeof ewtSeo !== 'undefined') ? ewtSeo.titleMax : 55;
	var descMax  = (typeof ewtSeo !== 'undefined') ? ewtSeo.descMax : 130;

	function updateCounts() {
		$('#ewt_title_count').text($title.val().length);
		$('#ewt_desc_count').text($desc.val().length);
		$('#ewt_title_count').css('color', $title.val().length > titleMax ? '#b91c1c' : '');
		$('#ewt_desc_count').css('color', $desc.val().length > descMax ? '#b91c1c' : '');
	}

	function ensureStartsWithFK($field) {
		var fk = $fk.val().trim();
		if (!fk) return;
		var val = $field.val();
		if (val.toLowerCase().indexOf(fk.toLowerCase()) !== 0) {
			$field.val(fk + ($field.attr('id') === 'ewt_seo_title' ? ' - ' : '. ') + val);
		}
	}

	$title.on('input', updateCounts);
	$desc.on('input', updateCounts);

	$fk.on('blur', function () {
		ensureStartsWithFK($title);
		ensureStartsWithFK($desc);
		updateCounts();
	});

	updateCounts();
});
