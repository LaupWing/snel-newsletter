import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { Users, Send, Tag, Mail, Eye, Loader2, CheckCircle } from 'lucide-react';

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

function SendPanel() {
    const [ testEmail, setTestEmail ] = useState( '' );
    const [ testSending, setTestSending ] = useState( false );
    const [ testSent, setTestSent ] = useState( false );

    const postTitle = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ), [] );

    const handleTestSend = () => {
        if ( ! testEmail ) return;
        setTestSending( true );
        // Mock: simulate sending a test email
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
                        placeholder={ __( 'Brief summary shown in inbox...', 'snel-newsletter' ) }
                        className="snel-nl-input"
                    />
                    <p className="snel-nl-hint">
                        { __( 'Shown after the subject in most email clients.', 'snel-newsletter' ) }
                    </p>
                </div>

                <div className="snel-nl-divider" />

                <div className="snel-nl-field">
                    <label className="snel-nl-label">
                        <Eye size={ 12 } />
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
