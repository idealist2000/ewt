jQuery(function ($) {
	var $fk    = $('#ewt_focus_keyword');
	var $title = $('#ewt_seo_title');
	var $desc  = $('#ewt_seo_desc');
	var titleMax = (typeof ewtSeo !== 'undefined') ? ewtSeo.titleMax : 55;
	var descMax  = (typeof ewtSeo !== 'undefined') ? ewtSeo.descMax : 130;

	function updateCounts() {
		$('#ewt_title_count').text(Array.from($title.val()).length);
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

	function titleCase() {
		$title.val($title.val().toLocaleLowerCase('tr-TR').replace(/[\p{L}\p{N}]+/gu, function (word) {
			if (/^(toefl|ielts|ıelts|ydt|yds|yökdil|gre|gmat|cefr|seo|pdf|bbc|it|ıt|[abc][12])$/i.test(word)) return word === 'yökdil' ? 'YÖKDİL' : word.toUpperCase();
			return word.charAt(0).toLocaleUpperCase('tr-TR') + word.slice(1);
		}));
	}
	$title.on('blur', function () { ensureStartsWithFK($title); titleCase(); updateCounts(); });
	$title.on('input', updateCounts);
	$desc.on('input', updateCounts);

	$fk.on('blur', function () {
		ensureStartsWithFK($title);
		ensureStartsWithFK($desc);
		titleCase();
		updateCounts();
	});

	updateCounts();
});
