jQuery(function ($) {

	/* ---------- AI İÇERİK OLUŞTURMA ---------- */
	$('#ewt_ai_generate_btn').on('click', function () {
		var provider   = $('#ewt_ai_provider').val();
		var prompt     = $('#ewt_ai_prompt').val();
		var difficulty = $('#ewt_ai_difficulty').val();
		var wordCount  = $('#ewt_ai_word_count').val();
		var postId     = $('#post_ID').val();
		var $status    = $('#ewt_ai_status');

		if (!prompt) {
			$status.removeClass('ewt-success').addClass('ewt-error').text('Lütfen bir talimat girin.').show();
			return;
		}
		if (typeof ewtAi === 'undefined') return;

		$status.removeClass('ewt-error ewt-success').text('İçerik üretiliyor, lütfen bekleyin...').show();
		$(this).prop('disabled', true);

		$.post(ewtAi.ajaxUrl, {
			action: 'ewt_generate_content',
			nonce: ewtAi.nonce,
			provider: provider,
			prompt: prompt,
			difficulty: difficulty,
			word_count: wordCount,
			post_id: postId
		}).done(function (res) {
			if (res.success) {
				insertContentIntoEditor(res.data.content);
				$status.removeClass('ewt-error').addClass('ewt-success').text('İçerik editöre eklendi. Lütfen gözden geçirin.');
			} else {
				$status.removeClass('ewt-success').addClass('ewt-error').text(res.data || 'Bir hata oluştu.');
			}
		}).fail(function () {
			$status.removeClass('ewt-success').addClass('ewt-error').text('Sunucuya bağlanılamadı.');
		}).always(function () {
			$('#ewt_ai_generate_btn').prop('disabled', false);
		});
	});

	function insertContentIntoEditor(html) {
		// Gutenberg (block editor)
		if (window.wp && wp.data && wp.data.dispatch && wp.data.select('core/editor')) {
			try {
				var blocks = wp.blocks.rawHandler({ HTML: html });
				var existing = wp.data.select('core/block-editor').getBlocks();
				wp.data.dispatch('core/block-editor').insertBlocks(blocks, existing.length);
				return;
			} catch (e) { /* fallback aşağıda */ }
		}
		// Classic Editor (TinyMCE)
		if (window.tinymce && tinymce.get('content')) {
			tinymce.get('content').execCommand('mceInsertContent', false, html);
			return;
		}
		// Düz textarea fallback
		var $textarea = $('#content');
		if ($textarea.length) {
			$textarea.val($textarea.val() + "\n" + html);
		}
	}

	/* ---------- AI GÖRSEL / FEATURED IMAGE ---------- */
	$('#ewt_ai_image_btn').on('click', function () {
		var prompt = $('#ewt_ai_image_prompt').val();
		var $status = $('#ewt_ai_image_status');
		var postId = $('#post_ID').val();

		if (typeof ewtImage === 'undefined') return;

		$status.removeClass('ewt-error ewt-success').text('Görsel üretiliyor, bu 10-30 sn sürebilir...').show();
		$(this).prop('disabled', true);

		var provider = $('#ewt_ai_image_provider').val() || 'pexels';
		var childObject = $('#ewt_ai_image_child_object').is(':checked') ? 1 : 0;

		$.post(ewtImage.ajaxUrl, {
			action: 'ewt_generate_featured_image',
			nonce: ewtImage.nonce,
			post_id: postId,
			prompt: prompt,
			provider: provider,
			child_object: childObject
		}).done(function (res) {
			if (res.success) {
				$('#ewt_ai_image_preview').html('<img src="' + res.data.thumbnail_url + '" style="max-width:100%;border-radius:8px;margin-top:8px;" />');
				$status.removeClass('ewt-error').addClass('ewt-success').text('Öne çıkan görsel atandı. Sayfayı yenileyince görebilirsiniz.');
				// Featured image kutusunu güncellemek için sayfa yenileme önerilir
				if ($('#postimagediv').length) {
					$('#postimagediv .inside').load(location.href + ' #postimagediv .inside > *');
				}
			} else {
				$status.removeClass('ewt-success').addClass('ewt-error').text(res.data || 'Bir hata oluştu.');
			}
		}).fail(function () {
			$status.removeClass('ewt-success').addClass('ewt-error').text('Sunucuya bağlanılamadı.');
		}).always(function () {
			$('#ewt_ai_image_btn').prop('disabled', false);
		});
	});

});
