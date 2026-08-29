import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { useDispatch, useSelect, select } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

// Is the campaign currently flagged as a workflow (automation) email?
function isWorkflowEmail() {
    const meta = select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) || {};
    return meta._snel_nl_is_workflow === '1' || meta._snel_nl_is_workflow === true;
}
import NewsletterSidebar from './editor/NewsletterSidebar';
import PrePublishPreview from './editor/PrePublishPreview';
import './editor/editor.css';

// Register newsletter-only blocks.
import './blocks/newsletter-button/index';
import './blocks/newsletter-download/index';

registerPlugin( 'snel-newsletter-sidebar', {
    render: NewsletterSidebar,
    icon: null,
} );

registerPlugin( 'snel-newsletter-prepublish', {
    render: PrePublishPreview,
    icon: null,
} );

/**
 * Workflow emails are never published — they stay drafts and send from the
 * automation flow. So we hide the "Send Newsletter" (publish) button and let
 * the Save draft control (relabelled "Save workflow email") do the saving.
 * A body class drives the CSS so this tracks the toggle in real time.
 */
registerPlugin( 'snel-newsletter-workflow-mode', {
    render: function WorkflowMode() {
        const isWorkflow = useSelect( ( s: any ) => {
            const meta = s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
            return meta._snel_nl_is_workflow === '1' || meta._snel_nl_is_workflow === true;
        }, [] );

        useEffect( () => {
            document.body.classList.toggle( 'snel-nl-workflow-email', isWorkflow );
            return () => document.body.classList.remove( 'snel-nl-workflow-email' );
        }, [ isWorkflow ] );

        return null;
    },
    icon: null,
} );

/**
 * Remove panels that don't apply to newsletters.
 */
registerPlugin( 'snel-newsletter-cleanup', {
    render: function CleanupPanels() {
        const { removeEditorPanel } = useDispatch( 'core/editor' );
        useEffect( () => {
            // Try all known panel IDs for visibility.
            removeEditorPanel( 'post-status' );
            removeEditorPanel( 'post-visibility' );
            removeEditorPanel( 'taxonomy-panel-category' );
            removeEditorPanel( 'taxonomy-panel-post_tag' );
            removeEditorPanel( 'featured-image' );
            removeEditorPanel( 'post-excerpt' );
            removeEditorPanel( 'discussion-panel' );
            removeEditorPanel( 'post-link' );
            removeEditorPanel( 'page-attributes' );
            removeEditorPanel( 'template' );
        }, [] );
        return null;
    },
    icon: null,
} );

/**
 * Replace "Publish" with "Send Newsletter" in the Gutenberg editor.
 */
addFilter( 'i18n.gettext', 'snel-newsletter/publish-label', ( translation: string, text: string ) => {
    // Workflow emails are saved as a step, never blasted out — so the send
    // language becomes save language. Read live so the label tracks the toggle.
    if ( isWorkflowEmail() ) {
        const workflow: Record< string, string > = {
            // The publish button is hidden for workflow emails; saving happens
            // through the (relabelled) Save draft control, which keeps it a draft.
            'Save draft': 'Save workflow email',
            'Save Draft': 'Save workflow email',
            'Publish': 'Save workflow email',
            'Update': 'Save workflow email',
            'Are you ready to publish?': 'Save this workflow email?',
            'Double-check your settings before publishing.': 'This email is saved as an automation step — it won’t send on its own.',
            'Publish:': 'Save:',
        };
        if ( workflow[ text ] !== undefined ) {
            return workflow[ text ];
        }
    }

    const replacements: Record< string, string > = {
        'Publish': 'Send Newsletter',
        'Update': 'Update Campaign',
        'Schedule': 'Schedule Send',
        'Are you ready to publish?': 'Ready to send this newsletter?',
        'Double-check your settings before publishing.': 'Double-check recipients and preview before sending.',
        'Publish:': 'Send:',
    };
    return replacements[ text ] ?? translation;
} );
