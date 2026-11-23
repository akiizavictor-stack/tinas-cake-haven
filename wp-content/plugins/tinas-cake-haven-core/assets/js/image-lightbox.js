( function () {
	'use strict';

	const imageSelector = '.wp-block-image img, .wp-block-gallery img';
	const excludedSelector = [
		'[class*="tch-hero-slider"]',
		'.tch-hero',
		'.wp-block-post-featured-image',
		'.wp-block-query',
		'.tch-post-card',
		'.tch-article',
		'.tch-card',
		'.tch-product',
		'.product',
		'.woocommerce',
		'.wp-block-site-logo',
		'.custom-logo-link'
	].join( ',' );

	const dialog = document.createElement( 'dialog' );
	dialog.className = 'tch-lightbox';
	dialog.setAttribute( 'aria-label', 'Image viewer' );
	dialog.innerHTML = '<div class="tch-lightbox__stage"><button class="tch-lightbox__close" type="button" aria-label="Close image">&times;</button><button class="tch-lightbox__previous" type="button" aria-label="Previous image">&#8249;</button><figure class="tch-lightbox__figure"><img class="tch-lightbox__image" alt=""><figcaption class="tch-lightbox__caption" hidden></figcaption></figure><button class="tch-lightbox__next" type="button" aria-label="Next image">&#8250;</button></div>';
	document.body.appendChild( dialog );

	const largeImage = dialog.querySelector( '.tch-lightbox__image' );
	const caption = dialog.querySelector( '.tch-lightbox__caption' );
	const previousButton = dialog.querySelector( '.tch-lightbox__previous' );
	const nextButton = dialog.querySelector( '.tch-lightbox__next' );
	let currentImage = null;
	let galleryImages = [];
	let galleryIndex = -1;
	let priorOverflow = '';

	function isBlogContext() {
		return document.body.matches( '.single-post, .blog, .archive, .post-type-archive' );
	}

	function sameImageUrl( first, second ) {
		const firstUrl = new URL( first, window.location.href );
		const secondUrl = new URL( second, window.location.href );
		const stripSize = function ( path ) {
			return path.replace( /-\d+x\d+(?=\.[^/.]+$)/, '' );
		};
		return firstUrl.origin === secondUrl.origin && stripSize( firstUrl.pathname ) === stripSize( secondUrl.pathname );
	}

	function isEligible( image ) {
		if ( ! image.matches( imageSelector ) || image.closest( excludedSelector ) || isBlogContext() ) {
			return false;
		}
		const link = image.closest( 'a' );
		return ! link || sameImageUrl( link.href, image.currentSrc || image.src );
	}

	function largestSource( image ) {
		const candidates = ( image.getAttribute( 'srcset' ) || '' ).split( ',' ).map( function ( candidate ) {
			const parts = candidate.trim().split( /\s+/ );
			return { url: parts[0], width: parseInt( parts[1], 10 ) || 0 };
		} ).filter( function ( candidate ) {
			return candidate.url;
		} );
		candidates.sort( function ( first, second ) {
			return second.width - first.width;
		} );
		return candidates.length ? candidates[0].url : ( image.currentSrc || image.src );
	}

	function captionFor( image ) {
		const figure = image.closest( 'figure' );
		const source = figure ? figure.querySelector( 'figcaption' ) : null;
		return source ? source.textContent.trim() : '';
	}

	function showImage( image ) {
		largeImage.src = largestSource( image );
		largeImage.alt = image.alt || '';
		caption.textContent = captionFor( image );
		caption.hidden = ! caption.textContent;
	}

	function openLightbox( image ) {
		if ( ! isEligible( image ) ) {
			return;
		}
		currentImage = image;
		const gallery = image.closest( '.wp-block-gallery' );
		galleryImages = gallery ? Array.from( gallery.querySelectorAll( imageSelector ) ).filter( isEligible ) : [];
		galleryIndex = galleryImages.indexOf( image );
		const hasNavigation = galleryImages.length > 1;
		previousButton.hidden = ! hasNavigation;
		nextButton.hidden = ! hasNavigation;
		showImage( image );
		priorOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		dialog.classList.remove( 'is-closing' );
		dialog.showModal();
		dialog.querySelector( '.tch-lightbox__close' ).focus();
	}

	function closeLightbox() {
		if ( ! dialog.open ) {
			return;
		}
		dialog.classList.add( 'is-closing' );
		dialog.close();
		finishClose();
	}

	function finishClose() {
		document.body.style.overflow = priorOverflow;
		dialog.classList.remove( 'is-closing' );
		if ( currentImage && currentImage.isConnected ) {
			currentImage.focus();
		}
		currentImage = null;
		galleryImages = [];
		galleryIndex = -1;
		largeImage.removeAttribute( 'src' );
		largeImage.alt = '';
		caption.textContent = '';
		caption.hidden = true;
	}

	function moveGallery( direction ) {
		if ( galleryImages.length < 2 ) {
			return;
		}
		galleryIndex = ( galleryIndex + direction + galleryImages.length ) % galleryImages.length;
		currentImage = galleryImages[galleryIndex];
		showImage( currentImage );
	}

	document.querySelectorAll( imageSelector ).forEach( function ( image ) {
		if ( ! isEligible( image ) ) {
			return;
		}
		image.setAttribute( 'role', 'button' );
		image.setAttribute( 'tabindex', '0' );
		image.setAttribute( 'aria-haspopup', 'dialog' );
		image.setAttribute( 'aria-label', image.alt ? 'View image: ' + image.alt : 'View image' );
		image.classList.add( 'tch-lightbox-trigger' );
	} );

	document.addEventListener( 'click', function ( event ) {
		const image = event.target.closest( imageSelector );
		if ( image && isEligible( image ) ) {
			event.preventDefault();
			openLightbox( image );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( dialog.open ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeLightbox();
			} else if ( 'ArrowLeft' === event.key && galleryImages.length > 1 ) {
				event.preventDefault();
				moveGallery( -1 );
			} else if ( 'ArrowRight' === event.key && galleryImages.length > 1 ) {
				event.preventDefault();
				moveGallery( 1 );
			}
			return;
		}
		if ( ( 'Enter' === event.key || ' ' === event.key ) && event.target.matches( imageSelector ) && isEligible( event.target ) ) {
			event.preventDefault();
			openLightbox( event.target );
		}
	} );

	dialog.addEventListener( 'click', function ( event ) {
		if ( ! event.target.closest( '.tch-lightbox__image, .tch-lightbox__close, .tch-lightbox__previous, .tch-lightbox__next' ) ) {
			closeLightbox();
		}
	} );
	dialog.addEventListener( 'cancel', function ( event ) {
		event.preventDefault();
		closeLightbox();
	} );
	dialog.addEventListener( 'close', finishClose );

	dialog.querySelector( '.tch-lightbox__close' ).addEventListener( 'click', closeLightbox );
	previousButton.addEventListener( 'click', function () {
		moveGallery( -1 );
	} );
	nextButton.addEventListener( 'click', function () {
		moveGallery( 1 );
	} );
} )();