import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { Users, Send, Tag, Mail, Eye, Loader2, CheckCircle, X, Monitor, Smartphone } from 'lucide-react';

const TAGS = window.snelNewsletterEditor?.tags || [];
const SUBSCRIBER_COUNT = window.snelNewsletterEditor?.subscriberCount || 0;

function RecipientPanel() {
    const [ audience, setAudience ] = useState( 'all' );
    const [ selectedTags, setSelectedTags ] = useState( [] );

    const toggleTag = ( tag ) => {
        setSelectedTags( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
    };

    return (
        <PluginDocumentSettingPanel
            name="snel-newsletter-recipients"
            title={ __( 'Recipients', 'snel-newsletter' ) }
            icon={ <Users size={ 16 } /> }
        >
            <div className="snel-newsletter-panel">
                <div className="snel-nl-field">
                    <label className="snel-nl-label">{ __( 'Send to', 'snel-newsletter' ) }</label>
                    <div className="snel-nl-radio-group">
                        <label className="snel-nl-radio">
                            <input
                                type="radio"
                                name="snel-nl-audience"
                                value="all"
                                checked={ audience === 'all' }
                                onChange={ () => { setAudience( 'all' ); setSelectedTags( [] ); } }
                            />
                            <span>{ __( 'All subscribers', 'snel-newsletter' ) }</span>
                            <span className="snel-nl-count">{ SUBSCRIBER_COUNT.toLocaleString() }</span>
                        </label>
                        <label className="snel-nl-radio">
                            <input
                                type="radio"
                                name="snel-nl-audience"
                                value="tags"
                                checked={ audience === 'tags' }
                                onChange={ () => setAudience( 'tags' ) }
                            />
                            <span>{ __( 'By tag', 'snel-newsletter' ) }</span>
                        </label>
                    </div>
                </div>

                { audience === 'tags' && (
                    <div className="snel-nl-field">
                        <label className="snel-nl-label">{ __( 'Select tags', 'snel-newsletter' ) }</label>
                        <div className="snel-nl-tags">
                            { TAGS.map( ( tag ) => (
                                <button
                                    key={ tag }
                                    type="button"
                                    onClick={ () => toggleTag( tag ) }
                                    className={ `snel-nl-tag ${ selectedTags.includes( tag ) ? 'is-active' : '' }` }
                                >
                                    <Tag size={ 10 } />
                                    { tag }
                                </button>
                            ) ) }
                        </div>
                        { selectedTags.length > 0 && (
                            <p className="snel-nl-hint">
                                { __( 'Subscribers matching any selected tag will receive this campaign.', 'snel-newsletter' ) }
                            </p>
                        ) }
                    </div>
                ) }
            </div>
        </PluginDocumentSettingPanel>
    );
}

function EmailPreviewModal( { onClose, title, previewText, content } ) {
    const [ device, setDevice ] = useState( 'desktop' );

    return (
        <div className="snel-nl-preview-overlay" onClick={ onClose }>
            <div className="snel-nl-preview-modal" onClick={ ( e ) => e.stopPropagation() }>
                <div className="snel-nl-preview-header">
                    <h3>{ __( 'Email Preview', 'snel-newsletter' ) }</h3>
                    <div className="snel-nl-preview-actions">
                        <button
                            type="button"
                            onClick={ () => setDevice( 'desktop' ) }
                            className={ `snel-nl-device-btn ${ device === 'desktop' ? 'is-active' : '' }` }
                        >
                            <Monitor size={ 14 } />
                        </button>
                        <button
                            type="button"
                            onClick={ () => setDevice( 'mobile' ) }
                            className={ `snel-nl-device-btn ${ device === 'mobile' ? 'is-active' : '' }` }
                        >
                            <Smartphone size={ 14 } />
                        </button>
                        <button type="button" onClick={ onClose } className="snel-nl-preview-close">
                            <X size={ 16 } />
                        </button>
                    </div>
                </div>

                <div className="snel-nl-preview-body">
                    <div className={ `snel-nl-preview-frame ${ device === 'mobile' ? 'is-mobile' : '' }` }>
                        {/* Inbox row preview */}
                        <div className="snel-nl-inbox-preview">
                            <div className="snel-nl-inbox-row">
                                <div className="snel-nl-inbox-sender">Your Newsletter</div>
                                <div className="snel-nl-inbox-date">now</div>
                            </div>
                            <div className="snel-nl-inbox-subject">{ title || __( '(no subject)', 'snel-newsletter' ) }</div>
                            <div className="snel-nl-inbox-preview-text">{ previewText || __( 'No preview text set...', 'snel-newsletter' ) }</div>
                        </div>

                        {/* Email content preview */}
                        <div className="snel-nl-email-preview">
                            <table width="100%" cellPadding="0" cellSpacing="0" style={ { margin: '0 auto', fontFamily: 'Arial, sans-serif', maxWidth: '600px' } }>
                                <tbody>
                                    <tr>
                                        <td style={ { background: '#1a1a1a', padding: '24px', textAlign: 'center' } }>
                                            <span style={ { color: '#fff', fontSize: '18px', fontWeight: 'bold' } }>Your Newsletter</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={ { padding: '32px 24px', background: '#ffffff' } }>
                                            { content ? (
                                                <div
                                                    className="snel-nl-email-content"
                                                    dangerouslySetInnerHTML={ { __html: content } }
                                                />
                                            ) : (
                                                <p style={ { color: '#9ca3af', fontStyle: 'italic', fontSize: '14px' } }>
                                                    { __( 'Start writing your newsletter content in the editor...', 'snel-newsletter' ) }
                                                </p>
                                            ) }
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={ { padding: '16px 24px', textAlign: 'center', borderTop: '1px solid #e5e7eb' } }>
                                            <p style={ { color: '#9ca3af', fontSize: '11px', margin: '0 0 4px' } }>
                                                { __( 'You received this because you subscribed to our newsletter.', 'snel-newsletter' ) }
                                            </p>
                                            <a href="#" style={ { color: '#6b7280', fontSize: '11px' } }>
                                                { __( 'Unsubscribe', 'snel-newsletter' ) }
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function SendPanel() {
    const [ testEmail, setTestEmail ] = useState( '' );
    const [ testSending, setTestSending ] = useState( false );
    const [ testSent, setTestSent ] = useState( false );
    const [ previewText, setPreviewText ] = useState( '' );
    const [ showPreview, setShowPreview ] = useState( false );

    const postTitle = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ), [] );
    const postContent = useSelect( ( select ) => select( 'core/editor' ).getEditedPostContent(), [] );

    const handleTestSend = () => {
        if ( ! testEmail ) return;
        setTestSending( true );
        setTimeout( () => {
            setTestSending( false );
            setTestSent( true );
            setTimeout( () => setTestSent( false ), 3000 );
        }, 1500 );
    };

    return (
        <PluginDocumentSettingPanel
            name="snel-newsletter-send"
            title={ __( 'Send Settings', 'snel-newsletter' ) }
            icon={ <Send size={ 16 } /> }
        >
            <div className="snel-newsletter-panel">
                <div className="snel-nl-field">
                    <label className="snel-nl-label">{ __( 'Subject line', 'snel-newsletter' ) }</label>
                    <div className="snel-nl-subject-preview">
                        <Mail size={ 12 } />
                        <span>{ postTitle || __( '(no title)', 'snel-newsletter' ) }</span>
                    </div>
                    <p className="snel-nl-hint">
                        { __( 'The post title is used as the email subject line.', 'snel-newsletter' ) }
                    </p>
                </div>

                <div className="snel-nl-field">
                    <label className="snel-nl-label">{ __( 'Preview text', 'snel-newsletter' ) }</label>
                    <input
                        type="text"
                        value={ previewText }
                        onChange={ ( e ) => setPreviewText( e.target.value ) }
                        placeholder={ __( 'Brief summary shown in inbox...', 'snel-newsletter' ) }
                        className="snel-nl-input"
                    />
                    <p className="snel-nl-hint">
                        { __( 'Shown after the subject in most email clients.', 'snel-newsletter' ) }
                    </p>
                </div>

                <div className="snel-nl-divider" />

                {/* Preview email button */}
                <div className="snel-nl-field">
                    <button
                        type="button"
                        onClick={ () => setShowPreview( true ) }
                        className="snel-nl-preview-btn"
                    >
                        <Eye size={ 14 } />
                        { __( 'Preview Email', 'snel-newsletter' ) }
                    </button>
                </div>

                <div className="snel-nl-divider" />

                <div className="snel-nl-field">
                    <label className="snel-nl-label">
                        <Send size={ 12 } />
                        { __( 'Send test email', 'snel-newsletter' ) }
                    </label>
                    <div className="snel-nl-test-row">
                        <input
                            type="email"
                            value={ testEmail }
                            onChange={ ( e ) => setTestEmail( e.target.value ) }
                            placeholder="you@example.com"
                            className="snel-nl-input"
                        />
                        <button
                            type="button"
                            onClick={ handleTestSend }
                            disabled={ ! testEmail || testSending }
                            className="snel-nl-test-btn"
                        >
                            { testSending ? (
                                <Loader2 size={ 12 } className="animate-spin" />
                            ) : testSent ? (
                                <CheckCircle size={ 12 } />
                            ) : (
                                <Send size={ 12 } />
                            ) }
                            { testSending ? __( 'Sending...', 'snel-newsletter' ) : testSent ? __( 'Sent!', 'snel-newsletter' ) : __( 'Send Test', 'snel-newsletter' ) }
                        </button>
                    </div>
                </div>
            </div>

            { showPreview && (
                <EmailPreviewModal
                    onClose={ () => setShowPreview( false ) }
                    title={ postTitle }
                    previewText={ previewText }
                    content={ postContent }
                />
            ) }
        </PluginDocumentSettingPanel>
    );
}

export default function NewsletterSidebar() {
    return (
        <>
            <RecipientPanel />
            <SendPanel />
        </>
    );
}
