jQuery(function ($) {

	// Research Effort satırını sadece "Research" modu seçiliyken göster
	function toggleEffortRow() {
		var mode = $('input[name="ewt_youcom_mode"]:checked').val();
		$('#ewt-youcom-effort-row').toggle(mode !== 'express');
	}
	toggleEffortRow();
	$('input[name="ewt_youcom_mode"]').on('change', toggleEffortRow);

	// Bağlantıyı Test Et butonları
	$('.ewt-test-provider-btn').on('click', function () {
		var $btn      = $(this);
		var provider  = $btn.data('provider');
		var keyField  = $btn.data('key-field');
		var apiKey    = $('#' + keyField).val();
		var $result   = $('.ewt-test-result[data-for="' + provider + '"]');

		if (!apiKey) {
			$result.removeClass('ewt-success').addClass('ewt-error').text(' Lütfen önce bir API anahtarı girin.');
			return;
		}
		if (typeof ewtSettingsTest === 'undefined') return;

		$btn.prop('disabled', true).text('Test ediliyor...');
		$result.removeClass('ewt-error ewt-success').text('');

		$.post(ewtSettingsTest.ajaxUrl, {
			action: 'ewt_test_provider',
			nonce: ewtSettingsTest.nonce,
			provider: provider,
			api_key: apiKey
		}).done(function (res) {
			if (res.success) {
				$result.removeClass('ewt-error').addClass('ewt-success').text(' ✅ ' + res.data);
			} else {
				$result.removeClass('ewt-success').addClass('ewt-error').text(' ❌ ' + (res.data || 'Bağlantı başarısız.'));
			}
		}).fail(function () {
			$result.removeClass('ewt-success').addClass('ewt-error').text(' ❌ Sunucuya bağlanılamadı.');
		}).always(function () {
			$btn.prop('disabled', false).text('✓ Bağlantıyı Test Et');
		});
	});

});
