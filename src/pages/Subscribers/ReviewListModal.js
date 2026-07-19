import { useState, useEffect, createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, ChevronDown, ChevronRight, Eye, MousePointerClick, Loader2, Users } from 'lucide-react';

/**
 * "Review list" modal — shows the subscribers a filter stack matches, and lets
 * you expand any one to see every campaign they received and whether they
 * opened / clicked it. Always constrained to active subscribers (send audience).
 *
 * Styled with inline styles on purpose: it's rendered inside the block editor,
 * which does not load the plugin's Tailwind stylesheet.
 *
 * @param {Object}   props
 * @param {Array}    props.filters - Filter conditions.
 * @param {Function} props.api     - Fetch helper (context-specific base URL + nonce).
 * @param {Function} props.onClose
 */

const S = {
    overlay: { position: 'fixed', inset: 0, zIndex: 100000, background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 },
    card: { background: '#fff', borderRadius: 12, boxShadow: '0 20px 50px rgba(0,0,0,0.25)', width: '100%', maxWidth: 560, maxHeight: '80vh', display: 'flex', flexDirection: 'column', overflow: 'hidden', fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' },
    header: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 20px', borderBottom: '1px solid #f0f0f1' },
    titleWrap: { display: 'flex', alignItems: 'center', gap: 8 },
    title: { fontSize: 14, fontWeight: 600, color: '#1e1e1e', margin: 0 },
    count: { fontSize: 12, color: '#8a8a8a' },
    closeBtn: { background: 'none', border: 'none', cursor: 'pointer', color: '#8a8a8a', padding: 4, display: 'flex', borderRadius: 6 },
    body: { flex: 1, overflowY: 'auto' },
    center: { padding: '56px 0', textAlign: 'center', color: '#8a8a8a', fontSize: 13 },
    row: { width: '100%', display: 'flex', alignItems: 'center', gap: 8, padding: '10px 20px', textAlign: 'left', background: 'none', border: 'none', borderBottom: '1px solid #f6f6f7', cursor: 'pointer' },
    email: { fontSize: 13, color: '#1e1e1e', margin: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' },
    name: { fontSize: 12, color: '#8a8a8a', margin: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' },
    histWrap: { padding: '2px 20px 12px 44px', background: '#fafafa' },
    histRow: { display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, padding: '3px 0' },
    histSubject: { flex: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', color: '#50575e' },
    histDate: { color: '#c3c4c7', width: 78, textAlign: 'right', flexShrink: 0 },
    footer: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 20px', borderTop: '1px solid #f0f0f1', fontSize: 12, color: '#8a8a8a' },
    pageBtn: { background: 'none', border: '1px solid #e0e0e0', borderRadius: 6, padding: '4px 10px', cursor: 'pointer', fontSize: 12, color: '#50575e' },
};

export default function ReviewListModal( { filters, api, onClose } ) {
    const [ subs, setSubs ] = useState( [] );
    const [ total, setTotal ] = useState( 0 );
    const [ page, setPage ] = useState( 1 );
    const [ pages, setPages ] = useState( 1 );
    const [ loading, setLoading ] = useState( true );
    const [ expanded, setExpanded ] = useState( null );
    const [ history, setHistory ] = useState( {} );
    const [ histLoading, setHistLoading ] = useState( null );

    useEffect( () => {
        setLoading( true );
        const activeFilters = [ ...filters, { field: 'status', operator: 'is', value: 'active' } ];
        const params = new URLSearchParams( { page, per_page: 20 } );
        params.set( 'filters', JSON.stringify( activeFilters ) );
        api( `/subscribers?${ params }` ).then( ( data ) => {
            setSubs( data.subscribers || [] );
            setTotal( data.total || 0 );
            setPages( data.pages || 1 );
            setLoading( false );
        } ).catch( () => setLoading( false ) );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ page ] );

    const toggle = ( id ) => {
        if ( expanded === id ) { setExpanded( null ); return; }
        setExpanded( id );
        if ( ! history[ id ] ) {
            setHistLoading( id );
            api( `/subscribers/${ id }/history` ).then( ( data ) => {
                setHistory( ( h ) => ( { ...h, [ id ]: data.history || [] } ) );
                setHistLoading( null );
            } ).catch( () => setHistLoading( null ) );
        }
    };

    const dim = { color: '#dcdcde' };

    return createPortal(
        <div style={ S.overlay } onClick={ onClose }>
            <div style={ S.card } onClick={ ( e ) => e.stopPropagation() }>
                <div style={ S.header }>
                    <div style={ S.titleWrap }>
                        <Users size={ 16 } style={ { color: '#3858e9' } } />
                        <h2 style={ S.title }>{ __( 'Review list', 'snel-newsletter' ) }</h2>
                        <span style={ S.count }>
                            { loading ? '…' : `${ total.toLocaleString() } ${ __( 'active match', 'snel-newsletter' ) }` }
                        </span>
                    </div>
                    <button type="button" onClick={ onClose } style={ S.closeBtn }><X size={ 16 } /></button>
                </div>

                <div style={ S.body }>
                    { loading ? (
                        <div style={ S.center }><Loader2 size={ 20 } className="animate-spin" /></div>
                    ) : subs.length === 0 ? (
                        <p style={ S.center }>{ __( 'No subscribers match these filters.', 'snel-newsletter' ) }</p>
                    ) : (
                        subs.map( ( s ) => (
                            <div key={ s.id }>
                                <button type="button" onClick={ () => toggle( s.id ) } style={ S.row }>
                                    { expanded === s.id ? <ChevronDown size={ 14 } style={ { color: '#8a8a8a', flexShrink: 0 } } /> : <ChevronRight size={ 14 } style={ { color: '#8a8a8a', flexShrink: 0 } } /> }
                                    <div style={ { minWidth: 0, flex: 1 } }>
                                        <p style={ S.email }>{ s.email }</p>
                                        { s.name && <p style={ S.name }>{ s.name }</p> }
                                    </div>
                                </button>

                                { expanded === s.id && (
                                    <div style={ S.histWrap }>
                                        { histLoading === s.id ? (
                                            <p style={ { ...S.histRow, color: '#8a8a8a' } }><Loader2 size={ 12 } className="animate-spin" /> { __( 'Loading history…', 'snel-newsletter' ) }</p>
                                        ) : ( history[ s.id ] || [] ).length === 0 ? (
                                            <p style={ { fontSize: 12, color: '#8a8a8a', padding: '4px 0' } }>{ __( 'No emails received yet.', 'snel-newsletter' ) }</p>
                                        ) : (
                                            history[ s.id ].map( ( h, idx ) => (
                                                <div key={ idx } style={ S.histRow }>
                                                    <span style={ S.histSubject }>{ h.subject || `#${ h.campaign_id }` }</span>
                                                    <span title={ __( 'Opened', 'snel-newsletter' ) } style={ Number( h.opened ) ? { color: '#00a32a', display: 'flex' } : { ...dim, display: 'flex' } }><Eye size={ 12 } /></span>
                                                    <span title={ __( 'Clicked', 'snel-newsletter' ) } style={ Number( h.clicked ) ? { color: '#3858e9', display: 'flex' } : { ...dim, display: 'flex' } }><MousePointerClick size={ 12 } /></span>
                                                    <span style={ S.histDate }>{ h.sent_at ? String( h.sent_at ).slice( 0, 10 ) : '—' }</span>
                                                </div>
                                            ) )
                                        ) }
                                    </div>
                                ) }
                            </div>
                        ) )
                    ) }
                </div>

                { pages > 1 && (
                    <div style={ S.footer }>
                        <span>{ __( 'Page', 'snel-newsletter' ) } { page } / { pages }</span>
                        <div style={ { display: 'flex', gap: 6 } }>
                            <button type="button" onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) } disabled={ page <= 1 } style={ { ...S.pageBtn, opacity: page <= 1 ? 0.4 : 1 } }>{ __( 'Prev', 'snel-newsletter' ) }</button>
                            <button type="button" onClick={ () => setPage( ( p ) => Math.min( pages, p + 1 ) ) } disabled={ page >= pages } style={ { ...S.pageBtn, opacity: page >= pages ? 0.4 : 1 } }>{ __( 'Next', 'snel-newsletter' ) }</button>
                        </div>
                    </div>
                ) }
            </div>
        </div>,
        document.body
    );
}
