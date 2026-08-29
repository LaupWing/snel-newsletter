import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { Eye, Users, Tag } from 'lucide-react';
import EmailPreviewModal from './EmailPreviewModal';

export default function PrePublishPreview() {
    const [ showPreview, setShowPreview ] = useState( false );

    const postTitle = useSelect( ( select: any ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ), [] );
    const postContent = useSelect( ( select: any ) => select( 'core/editor' ).getEditedPostContent(), [] );

    return (
        <PluginPrePublishPanel
            title={ __( 'Email Preview', 'snel-newsletter' ) }
            initialOpen={ true }
        >
            <div className="snel-newsletter-panel">
                <p className="snel-nl-hint" style={ { marginBottom: '8px' } }>
                    { __( 'Preview how your newsletter will look in email clients before sending.', 'snel-newsletter' ) }
                </p>
                <button
                    type="button"
                    onClick={ () => setShowPreview( true ) }
                    className="snel-nl-preview-btn"
                >
                    <Eye size={ 14 } />
                    { __( 'Preview Email', 'snel-newsletter' ) }
                </button>
            </div>

            { showPreview && (
                <EmailPreviewModal
                    onClose={ () => setShowPreview( false ) }
                    title={ postTitle }
                    previewText=""
                    content={ postContent }
                />
            ) }
        </PluginPrePublishPanel>
    );
}
