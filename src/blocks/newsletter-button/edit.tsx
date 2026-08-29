import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl, RangeControl, ColorPicker } from '@wordpress/components';

type Props = {
    attributes: Record< string, any >;
    setAttributes: ( attrs: Record< string, any > ) => void;
};

export default function Edit( { attributes, setAttributes }: Props ) {
    const { text, url, backgroundColor, textColor, align, borderRadius } = attributes;

    const blockProps = useBlockProps( {
        style: { textAlign: align },
    } );

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Button Settings', 'snel-newsletter' ) }>
                    <TextControl
                        label={ __( 'URL', 'snel-newsletter' ) }
                        value={ url }
                        onChange={ ( v: string ) => setAttributes( { url: v } ) }
                        placeholder="https://..."
                    />
                    <SelectControl
                        label={ __( 'Alignment', 'snel-newsletter' ) }
                        value={ align }
                        options={ [
                            { label: 'Left', value: 'left' },
                            { label: 'Center', value: 'center' },
                            { label: 'Right', value: 'right' },
                        ] }
                        onChange={ ( v: string ) => setAttributes( { align: v } ) }
                    />
                    <RangeControl
                        label={ __( 'Border Radius', 'snel-newsletter' ) }
                        value={ borderRadius }
                        onChange={ ( v: number ) => setAttributes( { borderRadius: v } ) }
                        min={ 0 }
                        max={ 24 }
                    />
                </PanelBody>
                <PanelBody title={ __( 'Colors', 'snel-newsletter' ) } initialOpen={ false }>
                    <p style={ { fontSize: '11px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Background', 'snel-newsletter' ) }</p>
                    <ColorPicker
                        color={ backgroundColor }
                        onChangeComplete={ ( c: any ) => setAttributes( { backgroundColor: c.hex } ) }
                        disableAlpha
                    />
                    <p style={ { fontSize: '11px', color: '#6b7280', margin: '12px 0 8px' } }>{ __( 'Text', 'snel-newsletter' ) }</p>
                    <ColorPicker
                        color={ textColor }
                        onChangeComplete={ ( c: any ) => setAttributes( { textColor: c.hex } ) }
                        disableAlpha
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <div style={ { display: 'inline-block', textAlign: align, width: '100%' } }>
                    <a
                        href={ url || '#' }
                        onClick={ ( e ) => e.preventDefault() }
                        style={ {
                            display: 'inline-block',
                            backgroundColor,
                            color: textColor,
                            padding: '12px 28px',
                            borderRadius: `${ borderRadius }px`,
                            textDecoration: 'none',
                            fontWeight: 'bold',
                            fontSize: '15px',
                            fontFamily: 'Arial, sans-serif',
                        } }
                    >
                        <input
                            type="text"
                            value={ text }
                            onChange={ ( e ) => setAttributes( { text: e.target.value } ) }
                            placeholder={ __( 'Button text...', 'snel-newsletter' ) }
                            style={ {
                                background: 'transparent',
                                border: 'none',
                                color: textColor,
                                fontWeight: 'bold',
                                fontSize: '15px',
                                textAlign: 'center',
                                outline: 'none',
                                width: 'auto',
                                minWidth: '80px',
                                fontFamily: 'Arial, sans-serif',
                            } }
                        />
                    </a>
                </div>
            </div>
        </>
    );
}
