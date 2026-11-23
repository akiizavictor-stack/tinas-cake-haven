/**
 * Website Images admin controls.
 * Uses the native WordPress Media Library for selection and uploads.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $lists = $( '#tch-home-gallery, #tch-home-bake, #tch-image-list' );

		function thumbFor( att ) {
			return ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
		}

		function openPicker( multiple, onSelect ) {
			var frame = wp.media( {
				title: tchWebsiteImages.title,
				library: { type: 'image' },
				multiple: multiple ? 'add' : false,
				button: { text: multiple ? tchWebsiteImages.add : tchWebsiteImages.change }
			} );
			frame.on( 'select', function () {
				var selection = frame.state().get( 'selection' );
				if ( multiple ) {
					selection.map( function ( att ) {
						onSelect( att.toJSON() );
					} );
				} else {
					var att = selection.first();
					if ( att ) {
						onSelect( att.toJSON() );
					}
				}
			} );
			frame.open();
		}

		function updateRow( $row, att ) {
			var id = parseInt( att.id, 10 );
			var $input = $row.find( '.tch-image-id' ).first();
			var $cell = $input.closest( 'td' );
			var $label = $row.find( '.tch-image-id-label, .tch-row-id-label' ).first();
			var $thumb = $row.find( '.tch-row-thumb' ).first();

			$input.val( id ).trigger( 'change' );
			if ( $label.length ) {
				$label.removeClass( 'tch-id-default' ).text( '#' + id );
			}
			$row.find( '.tch-no-image' ).remove();
			if ( $thumb.length ) {
				$thumb.attr( 'src', thumbFor( att ) ).show();
			} else {
				$cell.append(
					$( '<img>', {
						'class': 'tch-row-thumb',
						src: thumbFor( att ),
						alt: '',
						width: 120,
						height: 90
					} )
				);
			}
		}

		function newRow( att, $list ) {
			var id = parseInt( att.id, 10 );
			var name = $list.data( 'input-name' ) || 'gallery_ids[]';
			return $( '<tr>', { 'class': 'tch-gallery-row tch-image-row' } )
				.append(
					$( '<td>', { 'class': 'tch-row-order' } )
						.append( $( '<button>', { type: 'button', 'class': 'button tch-move-up', 'aria-label': tchWebsiteImages.up, text: '↑' } ) )
						.append( ' ' )
						.append( $( '<button>', { type: 'button', 'class': 'button tch-move-down', 'aria-label': tchWebsiteImages.down, text: '↓' } ) )
				)
				.append(
					$( '<td>', { 'class': 'tch-row-id-cell' } )
						.append( $( '<input>', { type: 'hidden', name: name, 'class': 'tch-row-id tch-image-id', value: id } ) )
						.append( $( '<code>', { 'class': 'tch-row-id-label', text: '#' + id } ) )
				)
				.append(
					$( '<td>', { 'class': 'tch-row-preview' } )
						.append( $( '<img>', { 'class': 'tch-row-thumb', src: thumbFor( att ), alt: '', width: 80, height: 80 } ) )
				)
				.append(
					$( '<td>', { 'class': 'tch-row-actions' } )
						.append( $( '<button>', { type: 'button', 'class': 'button tch-change-image', text: tchWebsiteImages.change } ) )
						.append( ' ' )
						.append( $( '<button>', { type: 'button', 'class': 'button tch-remove-row', text: tchWebsiteImages.remove } ) )
				);
		}

		$( document ).on( 'click', '.tch-change-image, .tch-select-image', function () {
			var $row = $( this ).closest( 'tr' );
			openPicker( false, function ( att ) {
				updateRow( $row, att );
			} );
		} );

		$lists.each( function () {
			var $list = $( this );

			$list.on( 'click', '.tch-remove-row', function () {
				var $form = $( this ).closest( 'form' );
				$( this ).closest( 'tr' ).remove();
				$form.trigger( 'change' );
			} );

			$list.on( 'click', '.tch-move-up', function () {
				var $row = $( this ).closest( 'tr' );
				var $previous = $row.prev( 'tr' );
				if ( $previous.length ) {
					$row.insertBefore( $previous ).trigger( 'change' );
				}
			} );

			$list.on( 'click', '.tch-move-down', function () {
				var $row = $( this ).closest( 'tr' );
				var $next = $row.next( 'tr' );
				if ( $next.length ) {
					$row.insertAfter( $next ).trigger( 'change' );
				}
			} );

		} );
		$( '.tch-add-images' ).on( 'click', function () {
			var $list = $( this ).closest( 'form' ).find( '#tch-home-gallery, #tch-home-bake, #tch-image-list' ).first();
			if ( ! $list.length ) {
				return;
			}
			openPicker( true, function ( att ) {
				var maxImages = parseInt( $list.data( 'max-images' ), 10 ) || 0;
				if ( maxImages && $list.children().length >= maxImages ) {
					return;
				}
				$list.append( newRow( att, $list ) ).trigger( 'change' );
			} );
		} );

		$( '#tch-home-bake-form' ).on( 'submit', function () {
			var count = $( this ).find( '#tch-home-bake > tr' ).length;
			$( this ).find( '#tch-home-bake-count' ).val( count );
		} );

		var $forms = $( '#tch-home-gallery-form, .tch-image-manager-form' );
		$forms.on( 'change input', function () {
			$( window ).on( 'beforeunload.tchwi', function () {
				return tchWebsiteImages.unsaved;
			} );
		} );
		$forms.on( 'submit', function () {
			$( window ).off( 'beforeunload.tchwi' );
		} );
	} );
} )( jQuery );
