import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';

const ICON_MAP = {
    pdf: '📄',
    video: '🎬',
    ebook: '📚',
    template: '📋',
    checklist: '✅',
    workout: '💪',
};

export default function Edit( { attributes, setAttributes } ) {
    const { title, description, buttonText, url, iconType } = attributes;

    const blockProps = useBlockProps();
    const icon = ICON_MAP[ iconType ] || '📄';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Download Settings', 'snel-newsletter' ) }>
                    <TextControl
                        label={ __( 'Download URL', 'snel-newsletter' ) }
                        value={ url }
                        onChange={ ( v ) => setAttributes( { url: v } ) }
                        placeholder="https://yoursite.com/guide.pdf"
                    />
                    <SelectControl
                        label={ __( 'Icon Type', 'snel-newsletter' ) }
                        value={ iconType }
                        options={ [
                            { label: '📄 PDF / Document', value: 'pdf' },
                            { label: '🎬 Video', value: 'video' },
                            { label: '📚 E-book', value: 'ebook' },
                            { label: '📋 Template', value: 'template' },
                            { label: '✅ Checklist', value: 'checklist' },
                            { label: '💪 Workout', value: 'workout' },
                        ] }
                        onChange={ ( v ) => setAttributes( { iconType: v } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <div style={ {
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    padding: '20px',
                    backgroundColor: '#f9fafb',
                    textAlign: 'center',
                    fontFamily: 'Arial, sans-serif',
                } }>
                    <div style={ { fontSize: '32px', marginBottom: '8px' } }>{ icon }</div>
                    <input
                        type="text"
                        value={ title }
                        onChange={ ( e ) => setAttributes( { title: e.target.value } ) }
                        placeholder={ __( 'Download title...', 'snel-newsletter' ) }
                        style={ {
                            background: 'transparent',
                            border: 'none',
                            fontSize: '17px',
                            fontWeight: 'bold',
                            color: '#111827',
                            textAlign: 'center',
                            outline: 'none',
                            width: '100%',
                            marginBottom: '4px',
                        } }
                    />
                    <input
                        type="text"
                        value={ description }
                        onChange={ ( e ) => setAttributes( { description: e.target.value } ) }
                        placeholder={ __( 'Short description...', 'snel-newsletter' ) }
                        style={ {
                            background: 'transparent',
                            border: 'none',
                            fontSize: '14px',
                            color: '#6b7280',
                            textAlign: 'center',
                            outline: 'none',
                            width: '100%',
                            marginBottom: '16px',
                        } }
                    />
                    <div>
                        <a
                            href={ url || '#' }
                            onClick={ ( e ) => e.preventDefault() }
                            style={ {
                                display: 'inline-block',
                                backgroundColor: '#7c3aed',
                                color: '#ffffff',
                                padding: '10px 24px',
                                borderRadius: '6px',
                                textDecoration: 'none',
                                fontWeight: 'bold',
                                fontSize: '14px',
                            } }
                        >
                            <input
                                type="text"
                                value={ buttonText }
                                onChange={ ( e ) => setAttributes( { buttonText: e.target.value } ) }
                                style={ {
                                    background: 'transparent',
                                    border: 'none',
                                    color: '#ffffff',
                                    fontWeight: 'bold',
                                    fontSize: '14px',
                                    textAlign: 'center',
                                    outline: 'none',
                                    width: 'auto',
                                    minWidth: '80px',
                                } }
                            />
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
