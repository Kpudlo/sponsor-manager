import { createBlock, registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'sponsor-manager/sidebar' ],
				transform: ( attributes ) =>
					createBlock( metadata.name, {
						...attributes,
						orientation: 'vertical',
						maxItems:
							attributes.maxItems > 0 ? attributes.maxItems : 12,
					} ),
			},
		],
	},
} );
