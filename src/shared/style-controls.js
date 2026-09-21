import { __ } from '@wordpress/i18n';
import { PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';

export default function StyleControls( { attributes, setAttributes } ) {
	const {
		useGlobalStyles,
		borderRadius,
		borderWidth,
		showShadow,
		borderColor,
		shadowColor,
		shadowOffsetX,
		shadowOffsetY,
		shadowBlur,
		shadowSpread,
	} = attributes;

	return (
		<>
			<PanelBody title={ __( 'Ad style', 'sponsor-manager' ) } initialOpen={ false }>
				<ToggleControl
					label={ __( 'Use global styles', 'sponsor-manager' ) }
					help={ __(
						'Managed under Sponsor Manager → Styles. Turn this off to style only this block.',
						'sponsor-manager'
					) }
					checked={ useGlobalStyles !== false }
					onChange={ ( value ) => setAttributes( { useGlobalStyles: value } ) }
				/>
				{ useGlobalStyles === false && (
					<>
						<RangeControl
							label={ __( 'Border radius', 'sponsor-manager' ) }
							value={ borderRadius }
							onChange={ ( value ) =>
								setAttributes( { borderRadius: value ?? 0 } )
							}
							min={ 0 }
							max={ 32 }
						/>
						<RangeControl
							label={ __( 'Border width', 'sponsor-manager' ) }
							value={ borderWidth }
							onChange={ ( value ) =>
								setAttributes( { borderWidth: value ?? 0 } )
							}
							min={ 0 }
							max={ 8 }
						/>
						<ToggleControl
							label={ __( 'Drop shadow', 'sponsor-manager' ) }
							checked={ !! showShadow }
							onChange={ ( value ) =>
								setAttributes( { showShadow: value } )
							}
						/>
						{ !! showShadow && (
							<>
								<RangeControl
									label={ __( 'Shadow horizontal offset', 'sponsor-manager' ) }
									value={ shadowOffsetX }
									onChange={ ( value ) =>
										setAttributes( { shadowOffsetX: value ?? 0 } )
									}
									min={ -40 }
									max={ 40 }
								/>
								<RangeControl
									label={ __( 'Shadow vertical offset', 'sponsor-manager' ) }
									value={ shadowOffsetY }
									onChange={ ( value ) =>
										setAttributes( { shadowOffsetY: value ?? 0 } )
									}
									min={ -40 }
									max={ 40 }
								/>
								<RangeControl
									label={ __( 'Shadow blur', 'sponsor-manager' ) }
									value={ shadowBlur }
									onChange={ ( value ) =>
										setAttributes( { shadowBlur: value ?? 0 } )
									}
									min={ 0 }
									max={ 60 }
								/>
								<RangeControl
									label={ __( 'Shadow spread', 'sponsor-manager' ) }
									value={ shadowSpread }
									onChange={ ( value ) =>
										setAttributes( { shadowSpread: value ?? 0 } )
									}
									min={ -20 }
									max={ 40 }
								/>
							</>
						) }
					</>
				) }
			</PanelBody>
			{ useGlobalStyles === false && (
				<PanelColorSettings
					title={ __( 'Colors', 'sponsor-manager' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: borderColor,
							onChange: ( value ) =>
								setAttributes( { borderColor: value || '' } ),
							label: __( 'Border', 'sponsor-manager' ),
						},
						...( showShadow
							? [
									{
										value: shadowColor,
										onChange: ( value ) =>
											setAttributes( { shadowColor: value || '' } ),
										label: __( 'Shadow', 'sponsor-manager' ),
									},
							  ]
							: [] ),
					] }
				/>
			) }
		</>
	);
}
