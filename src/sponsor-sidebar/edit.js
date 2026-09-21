import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import PlacementControl from '../shared/placement-control';
import StyleControls from '../shared/style-controls';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { placement } = attributes;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Placement', 'sponsor-manager' ) }>
					<PlacementControl
						value={ placement }
						onChange={ ( value ) => setAttributes( { placement: value } ) }
					/>
				</PanelBody>
				<StyleControls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>

			{ ! placement ? (
				<Placeholder
					icon="align-wide"
					label={ metadata.title }
					instructions={ __(
						'Choose a sponsor placement in the block settings.',
						'sponsor-manager'
					) }
				>
					<PlacementControl
						value={ placement }
						onChange={ ( value ) => setAttributes( { placement: value } ) }
					/>
				</Placeholder>
			) : (
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			) }
		</div>
	);
}
