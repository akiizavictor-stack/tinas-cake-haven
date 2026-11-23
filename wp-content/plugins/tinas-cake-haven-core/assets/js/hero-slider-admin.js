/**
 * Hero Slider admin – Media Library picker, add/remove and reorder.
 * Depends on jQuery and wp.media (enqueued on the Hero Slider screen).
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $tbody = $( '#tch-hero-slides' );
		if ( ! $tbody.length ) {
			return;
		}

		// Opens the WordPress media library for a single image selection.
		function openMedia( onSelect ) {
			var frame = wp.media( {
				title: tchHeroSliderAdmin.title,
				library: { type: 'image' },
				multiple: false,
				button: { text: tchHeroSliderAdmin.use }
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				onSelect( att );
			} );
			frame.open();
		}

		function thumbFor( att ) {
			return ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
		}

		function newRow( att ) {
			var $row = $(
				'<tr class="tch-slide-row">' +
					'<td class="tch-slide-order">' +
						'<button type="button" class="button tch-move-up" aria-label="' + tchHeroSliderAdmin.up + '">&uarr;</button> ' +
						'<button type="button" class="button tch-move-down" aria-label="' + tchHeroSliderAdmin.down + '">&darr;</button>' +
					'</td>' +
					'<td class="tch-slide-id-cell">' +
						'<input type="hidden" name="slide_ids[]" class="tch-slide-id" value="' + parseInt( att.id, 10 ) + '">' +
						'<code class="tch-slide-id-label">#' + parseInt( att.id, 10 ) + '</code>' +
					'</td>' +
					'<td class="tch-slide-preview"><img class="tch-slide-thumb" src="" alt="" width="80" height="80"></td>' +
					'<td><input type="text" name="slide_titles[]" class="regular-text" value="" maxlength="200" placeholder="' + tchHeroSliderAdmin.placeholder + '"></td>' +
					'<td class="tch-slide-actions">' +
						'<button type="button" class="button tch-change-image">' + tchHeroSliderAdmin.change + '</button> ' +
						'<button type="button" class="button tch-remove-slide">' + tchHeroSliderAdmin.remove + '</button>' +
					'</td>' +
				'</tr>'
			);
			$row.find( '.tch-slide-thumb' ).attr( 'src', thumbFor( att ) );
			return $row;
		}

		// Change image on an existing row.
		$tbody.on( 'click', '.tch-change-image', function () {
			var $row = $( this ).closest( 'tr' );
			openMedia( function ( att ) {
				$row.find( '.tch-slide-id' ).val( parseInt( att.id, 10 ) );
				$row.find( '.tch-slide-id-label' ).removeClass( 'tch-id-default' ).text( '#' + parseInt( att.id, 10 ) );
				$row.find( '.tch-no-image' ).remove();
				$row.find( '.tch-slide-thumb' ).attr( 'src', thumbFor( att ) ).show();
			} );
		} );

		// Add slide: pick an image first, then append the row.
		$( '.tch-add-slide' ).on( 'click', function () {
			openMedia( function ( att ) {
				$tbody.append( newRow( att ) );
			} );
		} );

		// Remove slide.
		$tbody.on( 'click', '.tch-remove-slide', function () {
			$( this ).closest( 'tr' ).remove();
		} );

		// Reorder.
		$tbody.on( 'click', '.tch-move-up', function () {
			var $row = $( this ).closest( 'tr' );
			var $prev = $row.prev( 'tr' );
			if ( $prev.length ) {
				$row.insertBefore( $prev );
			}
		} );
		$tbody.on( 'click', '.tch-move-down', function () {
			var $row = $( this ).closest( 'tr' );
			var $next = $row.next( 'tr' );
			if ( $next.length ) {
				$row.insertAfter( $next );
			}
		} );

		// Warn before leaving with unsaved changes.
		var $form = $( '#tch-hero-slider-form' );
		$form.on( 'change input', function () {
			$( window ).on( 'beforeunload.tchslider', function () {
				return tchHeroSliderAdmin.unsaved;
			} );
		} );
		$form.on( 'submit', function () {
			$( window ).off( 'beforeunload.tchslider' );
		} );
	} );
} )( jQuery );
