/**
 * Priority-weighted sponsor rotation — swaps one slot at a time after an initial delay.
 */
( function () {
	const INITIAL_DELAY_MS = 60000;
	const INTERVAL_MS = 60000;

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

		return `<a class="sponsor-manager-grid__item" href="${ link }" target="_blank" rel="noopener noreferrer sponsored">${ image }</a>`;
	}

	function getVisibleIds( container ) {
		return Array.from(
			container.querySelectorAll( '.sponsor-manager-grid__slot' )
		).map( ( slot ) => parseInt( slot.dataset.adId, 10 ) );
	}

	function swapRandomSlot( container, pool ) {
		const slots = Array.from(
			container.querySelectorAll( '.sponsor-manager-grid__slot' )
		);
		if ( ! slots.length ) {
			return;
		}

		const visibleIds = getVisibleIds( container );
		const replacement = weightedPick( pool, visibleIds );
		if ( ! replacement ) {
			return;
		}

		const slot =
			slots[ Math.floor( Math.random() * slots.length ) ];
		slot.classList.add( 'is-swapping' );

		window.setTimeout( () => {
			slot.dataset.adId = String( replacement.id );
			slot.innerHTML = renderAdMarkup( replacement );
			slot.classList.remove( 'is-swapping' );
		}, 350 );
	}

	function initRotation( container ) {
		if ( container.dataset.sponsorRotate !== '1' ) {
			return;
		}

		let pool;
		try {
			pool = JSON.parse( container.dataset.sponsorPool || '[]' );
		} catch ( error ) {
			return;
		}

		if ( ! Array.isArray( pool ) || pool.length <= 4 ) {
			return;
		}

		const initialDelay = parseInt(
			container.dataset.sponsorInitialMs || String( INITIAL_DELAY_MS ),
			10
		);
		const interval = parseInt(
			container.dataset.sponsorIntervalMs || String( INTERVAL_MS ),
			10
		);

		window.setTimeout( () => {
			swapRandomSlot( container, pool );
			window.setInterval( () => {
				swapRandomSlot( container, pool );
			}, interval );
		}, initialDelay );
	}

	document
		.querySelectorAll( '.sponsor-manager-grid[data-sponsor-rotate="1"]' )
		.forEach( initRotation );
}() );
