import { createBlock } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

export default function Edit( { attributes, clientId } ) {
	const { replaceBlock } = useDispatch( 'core/block-editor' );
	const didReplace = useRef( false );

	useEffect( () => {
		if ( didReplace.current ) {
			return;
		}
		didReplace.current = true;
		replaceBlock(
			clientId,
			createBlock( 'sponsor-manager/grid', {
				...attributes,
				orientation: 'vertical',
				maxItems: attributes.maxItems > 0 ? attributes.maxItems : 12,
			} )
		);
	}, [ attributes, clientId, replaceBlock ] );

	return null;
}
