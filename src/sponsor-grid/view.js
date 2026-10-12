/**
 * Rotate overflow ads every two minutes, replacing the oldest slots first.
 */
( function () {
	const INITIAL_DELAY_MS = 120000;
	const INTERVAL_MS = 120000;
	const SWAP_MS = 350;

	function weightedPick( ads, excludeIds ) {
		const pool = ads.filter( ( ad ) => ! excludeIds.includes( ad.id ) );
		if ( ! pool.length ) {
			return null;
		}

		const totalWeight = pool.reduce(
			( sum, ad ) => sum + Math.max( 1, ad.priority || 1 ),
			0
		);
		let roll = Math.random() * totalWeight;

		for ( const ad of pool ) {
			roll -= Math.max( 1, ad.priority || 1 );
			if ( roll <= 0 ) {
				return ad;
			}
		}

		return pool[ pool.length - 1 ];
	}

	function escapeHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	function renderAdMarkup( ad ) {
		const title = escapeHtml( ad.title );
		const link = escapeHtml( ad.link );
		const image = ad.image
			? `<img class="sponsor-manager-grid__item-image" src="${ escapeHtml( ad.image ) }" alt="${ title }" width="16" height="9" loading="lazy" decoding="async" />`
			: `<span class="sponsor-manager-grid__item-fallback">${ title }</span>`;

		return `<a class="sponsor-manager-grid__item" href="${ link }" target="_blank" rel="noopener noreferrer sponsored">${ image }<span class="screen-reader-text">(opens in a new tab)</span></a>`;
	}

	function getSlots( container ) {
		return Array.from(
			container.querySelectorAll( '.sponsor-manager-grid__slot' )
		);
	}

	function getVisibleIds( slots ) {
		return slots.map( ( slot ) => parseInt( slot.dataset.adId, 10 ) );
	}

	function swapOldestOverflow( container, pool ) {
		const slots = getSlots( container );
		if ( ! slots.length ) {
			return;
		}

		const overflow = pool.length - slots.length;
		if ( overflow <= 0 ) {
			return;
		}

		const swapCount = Math.min( overflow, slots.length );
		const now = Date.now();
		const ranked = slots.slice().sort( ( a, b ) => {
			return Number( a.dataset.shownAt || 0 ) - Number( b.dataset.shownAt || 0 );
		} );
		const outgoing = ranked.slice( 0, swapCount );
		const excludeIds = getVisibleIds( slots );
		const updates = [];

		outgoing.forEach( ( slot ) => {
			const replacement = weightedPick( pool, excludeIds );
			if ( ! replacement ) {
				return;
			}
			excludeIds.push( replacement.id );
			updates.push( { slot, replacement } );
			slot.classList.add( 'is-swapping' );
		} );

		if ( ! updates.length ) {
			return;
		}

		window.setTimeout( () => {
			updates.forEach( ( { slot, replacement } ) => {
				slot.dataset.adId = String( replacement.id );
				slot.dataset.shownAt = String( now );
				slot.innerHTML = renderAdMarkup( replacement );
				slot.classList.remove( 'is-swapping' );
			} );
		}, SWAP_MS );
	}

	function initRotation( container ) {
		if ( container.dataset.sponsorRotate !== '1' ) {
			return;
		}

		if (
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		) {
			return;
		}

		let pool;
		try {
			pool = JSON.parse( container.dataset.sponsorPool || '[]' );
		} catch ( error ) {
			return;
		}

		const slots = getSlots( container );
		if ( ! Array.isArray( pool ) || pool.length <= slots.length ) {
			return;
		}

		const started = Date.now();
		slots.forEach( ( slot ) => {
			slot.dataset.shownAt = String( started );
		} );

		const initialDelay = parseInt(
			container.dataset.sponsorInitialMs || String( INITIAL_DELAY_MS ),
			10
		);
		const interval = parseInt(
			container.dataset.sponsorIntervalMs || String( INTERVAL_MS ),
			10
		);

		window.setTimeout( () => {
			swapOldestOverflow( container, pool );
			window.setInterval( () => {
				swapOldestOverflow( container, pool );
			}, interval );
		}, initialDelay );
	}

	document
		.querySelectorAll( '.sponsor-manager-grid[data-sponsor-rotate="1"]' )
		.forEach( initRotation );
}() );
