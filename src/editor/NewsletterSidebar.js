import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { Users, Send, Tag, Mail, Eye, Loader2, CheckCircle, Workflow, Zap, ListFilter } from 'lucide-react';
import EmailPreviewModal from './EmailPreviewModal';
import FilterBar from '../pages/Subscribers/FilterBar';
import ReviewListModal from '../pages/Subscribers/ReviewListModal';

const TAGS = window.snelNewsletterEditor?.tags || [];
const TAG_COUNTS = window.snelNewsletterEditor?.tagCounts || {};
const SUBSCRIBER_COUNT = window.snelNewsletterEditor?.subscriberCount || 0;
const SENDERS = window.snelNewsletterEditor?.senders || {};
const API_URL = window.snelNewsletterEditor?.restUrl || '';
const NONCE = window.snelNewsletterEditor?.nonce || '';

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

function CampaignTypePanel() {
    const { editPost } = useDispatch( 'core/editor' );
    const meta = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
        []
    ) || {};

    const isWorkflow = meta._snel_nl_is_workflow === '1' || meta._snel_nl_is_workflow === true;
    const setWorkflow = ( on ) => editPost( { meta: { _snel_nl_is_workflow: on ? '1' : '' } } );

    return (
        <PluginDocumentSettingPanel
            name="snel-newsletter-type"
            title={ __( 'Campaign type', 'snel-newsletter' ) }
            icon={ <Workflow size={ 16 } /> }
        >
            <div className="snel-newsletter-panel">
                <div className="snel-nl-type-toggle">
                    <div className="snel-nl-type-text">
                        <span className="snel-nl-type-title">
                            <Zap size={ 13 } className={ isWorkflow ? 'snel-nl-type-icon is-on' : 'snel-nl-type-icon' } />
                            { __( 'Workflow email', 'snel-newsletter' ) }
                        </span>
                        <span className="snel-nl-hint">
                            { __( 'Save this as a step for an automation instead of a one-time broadcast.', 'snel-newsletter' ) }
                        </span>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={ isWorkflow }
                        onClick={ () => setWorkflow( ! isWorkflow ) }
                        className={ `snel-nl-switch ${ isWorkflow ? 'is-on' : '' }` }
                    >
                        <span className="snel-nl-switch-knob" />
                    </button>
                </div>

                <div className={ `snel-nl-type-note ${ isWorkflow ? 'is-workflow' : '' }` }>
                    { isWorkflow
                        ? __( 'Hidden from Broadcasts. Lives inside an automation and sends when the flow reaches this step — its opens & clicks are still tracked.', 'snel-newsletter' )
                        : __( 'Shows in your Broadcasts list and sends once to the audience you pick below.', 'snel-newsletter' ) }
                </div>
            </div>
        </PluginDocumentSettingPanel>
    );
}

function RecipientPanel() {
    const { editPost } = useDispatch( 'core/editor' );
    const meta = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
        []
    ) || {};

    // Persisted selection lives in post meta so it actually saves with the
    // campaign (and is read back by the queue when sending).
    const selectedTags = Array.isArray( meta._snel_nl_tags ) ? meta._snel_nl_tags : [];
    const setSelectedTags = ( next ) => {
        const value = typeof next === 'function' ? next( selectedTags ) : next;
        editPost( { meta: { _snel_nl_tags: value } } );
    };

    const filters = Array.isArray( meta._snel_nl_audience_filters ) ? meta._snel_nl_audience_filters : [];
    const setFilters = ( next ) => editPost( { meta: { _snel_nl_audience_filters: next } } );

    // Audience mode is persisted so we can tell "picked All" apart from "picked
    // nothing yet" — nothing selected blocks publishing (see PublishGate).
    const audience = meta._snel_nl_audience
        || ( filters.length > 0 ? 'custom' : ( selectedTags.length > 0 ? 'tags' : '' ) );

    // "Review list" opens a modal to browse the matched subscribers + history.
    const [ showReview, setShowReview ] = useState( false );

    // Live audience size for the tag selection. Overlapping tags make this a
    // distinct count, so it comes from the server rather than summing per-tag.
    const [ tagAudience, setTagAudience ] = useState( null );
    useEffect( () => {
        if ( selectedTags.length === 0 ) {
            setTagAudience( null );
            return;
        }
        let stale = false;
        api( `/audience/count?tags=${ encodeURIComponent( selectedTags.join( ',' ) ) }` )
            .then( ( res ) => { if ( ! stale ) setTagAudience( res?.count ?? null ); } )
            .catch( () => {} );
        return () => { stale = true; };
    }, [ selectedTags.join( ',' ) ] );

    // Switching audience clears the other mode's selection so only one applies.
    const chooseAudience = ( mode ) => {
        editPost( { meta: { _snel_nl_audience: mode } } );
        if ( mode !== 'tags' ) setSelectedTags( [] );
        if ( mode !== 'custom' ) setFilters( [] );
    };

    const toggleTag = ( tag ) => {
        setSelectedTags( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
    };

    // Workflow emails send from the automation flow — no broadcast audience. We
    // still render the panel (never conditionally unmount it) so it keeps its
    // position at the top of the sidebar instead of jumping to the bottom.
    const isWorkflow = meta._snel_nl_is_workflow === '1' || meta._snel_nl_is_workflow === true;
    if ( isWorkflow ) {
        return (
            <PluginDocumentSettingPanel
                name="snel-newsletter-recipients"
                title={ __( 'Recipients', 'snel-newsletter' ) }
                icon={ <Users size={ 16 } /> }
                initialOpen={ false }
            >
                <div className="snel-newsletter-panel">
                    <div className="snel-nl-sender">
                        <span className="snel-nl-sender-label">{ __( 'Sends from', 'snel-newsletter' ) }</span>
                        <span className="snel-nl-sender-email">{ SENDERS.automation || __( '(set in Settings)', 'snel-newsletter' ) }</span>
                    </div>
                    <p className="snel-nl-hint">
                        { __( 'Recipients are handled by the automation flow for workflow emails.', 'snel-newsletter' ) }
                    </p>
                </div>
            </PluginDocumentSettingPanel>
        );
    }

    return (
        <PluginDocumentSettingPanel
            name="snel-newsletter-recipients"
            title={ __( 'Recipients', 'snel-newsletter' ) }
            icon={ <Users size={ 16 } /> }
            initialOpen={ true }
        >
            <div className="snel-newsletter-panel">
                <div className="snel-nl-sender">
                    <span className="snel-nl-sender-label">{ __( 'Sends from', 'snel-newsletter' ) }</span>
                    <span className="snel-nl-sender-email">{ SENDERS.broadcast || __( '(set in Settings)', 'snel-newsletter' ) }</span>
                </div>
                <div className="snel-nl-field">
                    <label className="snel-nl-label">{ __( 'Send to', 'snel-newsletter' ) }</label>
                    <div className="snel-nl-radio-group">
                        <label className="snel-nl-radio">
                            <input
                                type="radio"
                                name="snel-nl-audience"
                                value="all"
                                checked={ audience === 'all' }
                                onChange={ () => chooseAudience( 'all' ) }
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
                                onChange={ () => chooseAudience( 'tags' ) }
                            />
                            <span>{ __( 'By tag', 'snel-newsletter' ) }</span>
                        </label>
                        <label className="snel-nl-radio">
                            <input
                                type="radio"
                                name="snel-nl-audience"
                                value="custom"
                                checked={ audience === 'custom' }
                                onChange={ () => chooseAudience( 'custom' ) }
                            />
                            <span>{ __( 'Custom list', 'snel-newsletter' ) }</span>
                        </label>
                    </div>
                    { ! audience && (
                        <p className="snel-nl-hint" style={ { color: '#b45309' } }>
                            { __( 'Pick who to send to — publishing is disabled until you choose.', 'snel-newsletter' ) }
                        </p>
                    ) }
                </div>

                { audience === 'custom' && (
                    <div className="snel-nl-field">
                        <label className="snel-nl-label">
                            <ListFilter size={ 12 } />
                            { __( 'Build the list', 'snel-newsletter' ) }
                        </label>
                        <FilterBar filters={ filters } onChange={ setFilters } allTags={ TAGS } />
                        <button
                            type="button"
                            onClick={ () => setShowReview( true ) }
                            disabled={ filters.length === 0 }
                            className="snel-nl-preview-btn"
                            style={ { marginTop: '8px' } }
                        >
                            <Users size={ 14 } />
                            { __( 'Review list', 'snel-newsletter' ) }
                        </button>
                        <p className="snel-nl-hint">
                            { __( 'Only active subscribers matching every filter will receive this campaign.', 'snel-newsletter' ) }
                        </p>
                    </div>
                ) }

                { showReview && (
                    <ReviewListModal filters={ filters } api={ api } onClose={ () => setShowReview( false ) } />
                ) }

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
                                    <span className="snel-nl-count">{ ( TAG_COUNTS[ tag ] || 0 ).toLocaleString() }</span>
                                </button>
                            ) ) }
                        </div>
                        { selectedTags.length > 0 && (
                            <p className="snel-nl-hint">
                                { tagAudience !== null
                                    ? sprintf(
                                        /* translators: %s: number of subscribers */
                                        __( '%s active subscribers will receive this campaign.', 'snel-newsletter' ),
                                        tagAudience.toLocaleString()
                                    )
                                    : __( 'Subscribers matching any selected tag will receive this campaign.', 'snel-newsletter' ) }
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
    const [ testError, setTestError ] = useState( '' );
    const [ previewText, setPreviewText ] = useState( '' );
    const [ showPreview, setShowPreview ] = useState( false );

    const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
    const postTitle = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ), [] );
    const postContent = useSelect( ( select ) => select( 'core/editor' ).getEditedPostContent(), [] );

    const handleTestSend = () => {
        if ( ! testEmail || ! postId ) return;
        setTestSending( true );
        setTestError( '' );

        // Save the post first, then send test.
        const saveBtn = document.querySelector( '.editor-post-save-draft' );
        if ( saveBtn ) saveBtn.click();

        setTimeout( () => {
            api( `/campaigns/${ postId }/send-test`, {
                method: 'POST',
                body: JSON.stringify( { email: testEmail } ),
            } ).then( ( data ) => {
                setTestSending( false );
                if ( data?.success ) {
                    setTestSent( true );
                    setTimeout( () => setTestSent( false ), 3000 );
                } else {
                    setTestError( data?.message || 'Failed to send test email.' );
                    setTimeout( () => setTestError( '' ), 5000 );
                }
            } ).catch( () => {
                setTestSending( false );
                setTestError( 'Connection error.' );
            } );
        }, 1000 );
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
                    { testError && (
                        <p className="snel-nl-hint" style={ { color: '#ef4444' } }>{ testError }</p>
                    ) }
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

/**
 * Blocks publishing (and saving) until a broadcast has a valid audience, so you
 * can't accidentally send a campaign without choosing who it goes to.
 */
function PublishGate() {
    const { lockPostSaving, unlockPostSaving } = useDispatch( 'core/editor' );
    const meta = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ),
        []
    ) || {};

    const isWorkflow = meta._snel_nl_is_workflow === '1' || meta._snel_nl_is_workflow === true;
    const tags       = Array.isArray( meta._snel_nl_tags ) ? meta._snel_nl_tags : [];
    const filters    = Array.isArray( meta._snel_nl_audience_filters ) ? meta._snel_nl_audience_filters : [];
    const audience   = meta._snel_nl_audience
        || ( filters.length > 0 ? 'custom' : ( tags.length > 0 ? 'tags' : '' ) );

    const valid = isWorkflow
        || audience === 'all'
        || ( audience === 'tags' && tags.length > 0 )
        || ( audience === 'custom' && filters.length > 0 );

    useEffect( () => {
        const cls = 'snel-nl-publish-locked';
        if ( valid ) {
            unlockPostSaving( 'snel-audience-required' );
            document.body.classList.remove( cls );
        } else {
            lockPostSaving( 'snel-audience-required' );
            document.body.classList.add( cls );
        }
        return () => document.body.classList.remove( cls );
    }, [ valid ] );

    return null;
}

export default function NewsletterSidebar() {
    return (
        <>
            <PublishGate />
            { /* Recipients first (always mounted) so it stays at the top and is
                 the first thing you see; it self-handles the workflow case. */ }
            <RecipientPanel />
            <CampaignTypePanel />
            <SendPanel />
        </>
    );
}
