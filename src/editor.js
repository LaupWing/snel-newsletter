import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import NewsletterSidebar from './editor/NewsletterSidebar';
import PrePublishPreview from './editor/PrePublishPreview';
import './editor/editor.css';

registerPlugin( 'snel-newsletter-sidebar', {
    render: NewsletterSidebar,
    icon: null,
} );

registerPlugin( 'snel-newsletter-prepublish', {
    render: PrePublishPreview,
    icon: null,
} );

/**
 * Remove panels that don't apply to newsletters.
 */
registerPlugin( 'snel-newsletter-cleanup', {
    render: function CleanupPanels() {
        const { removeEditorPanel } = useDispatch( 'core/editor' );
        useEffect( () => {
            removeEditorPanel( 'post-status' ); // Visibility
        }, [] );
        return null;
    },
    icon: null,
} );

/**
 * Replace "Publish" with "Send Newsletter" in the Gutenberg editor.
 */
addFilter( 'i18n.gettext', 'snel-newsletter/publish-label', ( translation, text ) => {
    const replacements = {
        'Publish': 'Send Newsletter',
        'Update': 'Update Campaign',
        'Schedule': 'Schedule Send',
        'Are you ready to publish?': 'Ready to send this newsletter?',
        'Double-check your settings before publishing.': 'Double-check recipients and preview before sending.',
        'Publish:': 'Send:',
    };
    return replacements[ text ] ?? translation;
} );
