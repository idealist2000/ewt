/**
 * Taslak Yönetimi — toplu yayınlama arayüz mantığı.
 * Zaman aşımı olmadan çalışması için: her istek sınırlı bir parti (batch) işler,
 * sunucu "done" dönene kadar (partide batch'ten az sonuç gelene kadar) döngü devam eder.
 * "Tümü" seçildiğinde içerik türleri sırayla işlenir.
 */
( function ( $ ) {
	'use strict';

	let stopRequested = false;
	let totalEstimate  = 0;
	let totalDone      = 0;

	function log( msg ) {
		const $log = $( '#ewt_dp_log' );
		$log.show();
		$log.append( '<div>' + msg + '</div>' );
		$log.scrollTop( $log[ 0 ].scrollHeight );
	}

	function setProgress( pct, text ) {
		$( '#ewt_dp_progress_wrap' ).show();
		$( '#ewt_dp_progress_bar' ).css( 'width', Math.max( 0, Math.min( 100, pct ) ) + '%' );
		$( '#ewt_dp_progress_text' ).text( text );
	}

	function ajax( action, data ) {
		return $.post( ewtDraftPublish.ajaxUrl, Object.assign( {
			action: action,
			nonce: ewtDraftPublish.nonce,
		}, data ) );
	}

	function refreshCountsRow( postType, publishDelta ) {
		const $row = $( 'tr[data-pt="' + postType + '"]' );
		if ( ! $row.length ) return;
		const $pub   = $row.find( '.ewt-pub-count' );
		const $draft = $row.find( '.ewt-draft-count' );
		const curPub   = parseInt( $pub.text().replace( /[^0-9]/g, '' ), 10 ) || 0;
		const curDraft = parseInt( $draft.text().replace( /[^0-9]/g, '' ), 10 ) || 0;
		$pub.text( ( curPub + publishDelta ).toLocaleString( 'tr-TR' ) );
		$draft.text( Math.max( 0, curDraft - publishDelta ).toLocaleString( 'tr-TR' ) );
	}

	async function publishOneType( postType, filters, batchSize ) {
		// Tahmini toplam (ilerleme çubuğu için)
		let typeTotal = 0;
		try {
			const countRes = await ajax( 'ewt_count_drafts', Object.assign( { post_type: postType }, filters ) );
			if ( countRes && countRes.success ) {
				typeTotal = countRes.data.total;
			}
		} catch ( e ) { /* tahmine devam et */ }

		if ( typeTotal === 0 ) {
			log( '— ' + ( ewtDraftPublish.labels[ postType ] || postType ) + ': yayınlanacak taslak yok.' );
			return;
		}

		log( '▶ ' + ( ewtDraftPublish.labels[ postType ] || postType ) + ': ~' + typeTotal + ' taslak bulundu, yayınlanıyor…' );
		totalEstimate += typeTotal;

		let done = false;
		let typeDone = 0;
		while ( ! done && ! stopRequested ) {
			const res = await ajax( 'ewt_publish_drafts_batch', Object.assign( {
				post_type: postType,
				batch: batchSize,
			}, filters ) );

			if ( ! res || ! res.success ) {
				log( '⚠ Hata: ' + ( res && res.data ? res.data : 'bilinmeyen hata' ) + ' — durduruluyor.' );
				break;
			}

			const d = res.data;
			typeDone  += d.published;
			totalDone += d.published;
			done = d.done;

			refreshCountsRow( postType, d.published );

			const pct = totalEstimate > 0 ? ( totalDone / totalEstimate ) * 100 : 0;
			setProgress( pct, totalDone + ' / ~' + totalEstimate + ' yayınlandı (' + ( ewtDraftPublish.labels[ postType ] || postType ) + ')' );

			if ( d.failed > 0 ) {
				log( '  ⚠ ' + d.failed + ' kayıt yayınlanamadı (bu partide).' );
			}
		}

		log( '✓ ' + ( ewtDraftPublish.labels[ postType ] || postType ) + ': toplam ' + typeDone + ' içerik yayınlandı.' );
	}

	$( function () {
		const $pt      = $( '#ewt_dp_post_type' );
		const $confirm = $( '#ewt_dp_confirm' );
		const $start   = $( '#ewt_dp_start' );
		const $stop    = $( '#ewt_dp_stop' );

		function updateStartState() {
			$start.prop( 'disabled', ! ( $pt.val() && $confirm.is( ':checked' ) ) );
		}
		$pt.on( 'change', updateStartState );
		$confirm.on( 'change', updateStartState );

		$start.on( 'click', async function () {
			stopRequested = false;
			totalEstimate = 0;
			totalDone     = 0;
			$( '#ewt_dp_log' ).empty().hide();
			setProgress( 0, 'Başlatılıyor…' );

			$start.hide();
			$stop.show();

			const postType  = $pt.val();
			const batchSize = parseInt( $( '#ewt_dp_batch' ).val(), 10 ) || 200;
			const filters   = {
				seviye:    $( '#ewt_dp_seviye' ).val(),
				date_from: $( '#ewt_dp_date_from' ).val(),
				date_to:   $( '#ewt_dp_date_to' ).val(),
			};

			if ( postType === 'all' ) {
				for ( const pt of ewtDraftPublish.postTypes ) {
					if ( stopRequested ) break;
					await publishOneType( pt, filters, batchSize );
				}
			} else {
				await publishOneType( postType, filters, batchSize );
			}

			setProgress( 100, 'Tamamlandı — toplam ' + totalDone + ' içerik yayınlandı.' );
			$stop.hide();
			$start.show();
		} );

		$stop.on( 'click', function () {
			stopRequested = true;
			log( '⏹ Durdurma istendi, mevcut parti bitince duracak…' );
		} );
	} );
} )( jQuery );
