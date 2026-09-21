import { __ } from '@wordpress/i18n';
import { SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function PlacementControl( { value, onChange } ) {
	const { terms, isResolving } = useSelect(
		( select ) => {
			const { getEntityRecords, isResolving: isResolvingRecords } =
				select( coreStore );
			const query = { per_page: 100, orderby: 'name', order: 'asc' };

			return {
				terms: getEntityRecords( 'taxonomy', 'sponsor_placement', query ),
				isResolving: isResolvingRecords( 'taxonomy', 'sponsor_placement', query ),
			};
		},
		[]
	);

	if ( isResolving ) {
		return <Spinner />;
	}

	const options = [
		{ label: __( 'Select a placement…', 'sponsor-manager' ), value: '' },
	];

	if ( terms?.length ) {
		terms.forEach( ( term ) => {
			options.push( { label: term.name, value: term.slug } );
		} );
	} else {
		options.push( {
			label: __( 'No placements found', 'sponsor-manager' ),
			value: '',
			disabled: true,
		} );
	}

	return (
		<SelectControl
			label={ __( 'Sponsor Placement', 'sponsor-manager' ) }
			value={ value }
			options={ options }
			onChange={ onChange }
			help={
				[ 'everywhere', 'all' ].includes( value )
					? __(
							'Shows ads assigned to Everywhere.',
							'sponsor-manager'
					  )
					: __(
							'Shows ads assigned to this placement plus ads assigned to Everywhere.',
							'sponsor-manager'
					  )
			}
		/>
	);
}
