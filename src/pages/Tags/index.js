import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Tag, Pencil, Trash2, Zap, RefreshCw, Plus } from 'lucide-react';
import TagEditModal from './TagEditModal';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

export default function Tags() {
    const [ tags, setTags ]           = useState( [] );
    const [ loading, setLoading ]     = useState( true );
    const [ editingTag, setEditingTag ] = useState( null );
    const [ creating, setCreating ]   = useState( false );
    const [ syncing, setSyncing ]     = useState( null );

    const loadTags = useCallback( () => {
        setLoading( true );
        api( '/tags' ).then( ( data ) => {
            setTags( data || [] );
            setLoading( false );
        } );
    }, [] );

    useEffect( () => { loadTags(); }, [ loadTags ] );

    // The modal serves both flows; a null oldTag means we're creating one.
    const handleSave = async ( oldTag, payload ) => {
        const { new_tag: name, ...rule } = payload;

        const result = oldTag
            ? await api( `/tags/${ encodeURIComponent( oldTag ) }`, {
                method: 'PUT',
                body: JSON.stringify( payload ),
            } )
            : await api( '/tags', {
                method: 'POST',
                body: JSON.stringify( { tag: name, ...rule } ),
            } );

        if ( ! result?.code ) {
            loadTags();
        }

        return result;
    };

    const handleDelete = ( tag ) => {
        if ( ! confirm( `${ __( 'Delete tag', 'snel-newsletter' ) } "${ tag }"? ${ __( 'It will be removed from all subscribers.', 'snel-newsletter' ) }` ) ) return;
        api( `/tags/${ encodeURIComponent( tag ) }`, { method: 'DELETE' } ).then( () => loadTags() );
    };

    const handleSync = ( tag ) => {
        setSyncing( tag );
        api( `/tags/${ encodeURIComponent( tag ) }/sync`, { method: 'POST' } ).then( ( res ) => {
            setSyncing( null );
            loadTags();
            alert( `${ res.matched } ${ __( 'subscriber(s) matched', 'snel-newsletter' ) }` );
        } );
    };

    const staticTags  = tags.filter( ( t ) => t.type !== 'dynamic' );
    const dynamicTags = tags.filter( ( t ) => t.type === 'dynamic' );

    const METRIC_LABELS = {
        open_rate:       'Open rate',
        click_rate:      'Click rate',
        opens:           'Total opens',
        clicks:          'Total clicks',
        emails_received: 'Emails received',
    };

    const OPERATOR_LABELS = {
        gt: '>', gte: '≥', lt: '<', lte: '≤', eq: '=',
    };

    const renderTable = ( rows ) => (
        <table className="w-full">
            <thead>
                <tr className="border-b border-gray-100 bg-gray-50/50">
                    <th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Tag', 'snel-newsletter' ) }</th>
                    <th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Subscribers', 'snel-newsletter' ) }</th>
                    <th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Rule', 'snel-newsletter' ) }</th>
                    <th className="px-5 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">{ __( 'Actions', 'snel-newsletter' ) }</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
                { rows.map( ( t ) => (
                    <tr key={ t.tag } className="hover:bg-gray-50/50 transition-colors">
                        <td className="px-5 py-3">
                            <span className={ `inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full border ${ t.type === 'dynamic'
                                ? 'text-amber-700 bg-amber-50 border-amber-100'
                                : 'text-purple-700 bg-purple-50 border-purple-100'
                            }` }>
                                { t.type === 'dynamic' ? <Zap size={ 10 } /> : <Tag size={ 10 } /> }
                                { t.tag }
                            </span>
                        </td>
                        <td className="px-5 py-3">
                            <span className="text-sm text-gray-600">{ t.count }</span>
                        </td>
                        <td className="px-5 py-3">
                            { t.type === 'dynamic' && t.metric ? (
                                <span className="text-xs text-gray-500">
                                    { METRIC_LABELS[ t.metric ] } { OPERATOR_LABELS[ t.operator ] } { t.threshold }{ ( t.metric === 'open_rate' || t.metric === 'click_rate' ) ? '%' : '' }
                                </span>
                            ) : (
                                <span className="text-xs text-gray-300">—</span>
                            ) }
                        </td>
                        <td className="px-5 py-3">
                            <div className="flex items-center justify-end gap-1">
                                { t.type === 'dynamic' && (
                                    <button
                                        type="button"
                                        onClick={ () => handleSync( t.tag ) }
                                        disabled={ syncing === t.tag }
                                        className="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors disabled:opacity-40"
                                        title={ __( 'Sync now', 'snel-newsletter' ) }
                                    >
                                        <RefreshCw size={ 13 } className={ syncing === t.tag ? 'animate-spin' : '' } />
                                    </button>
                                ) }
                                <button
                                    type="button"
                                    onClick={ () => setEditingTag( t ) }
                                    className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                    title={ __( 'Edit', 'snel-newsletter' ) }
                                >
                                    <Pencil size={ 13 } />
                                </button>
                                <button
                                    type="button"
                                    onClick={ () => handleDelete( t.tag ) }
                                    className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title={ __( 'Delete', 'snel-newsletter' ) }
                                >
                                    <Trash2 size={ 13 } />
                                </button>
                            </div>
                        </td>
                    </tr>
                ) ) }
            </tbody>
        </table>
    );

    return (
        <div className="p-6">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Manage subscriber tags', 'snel-newsletter' ) }</p>
                </div>
                <button
                    type="button"
                    onClick={ () => setCreating( true ) }
                    className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                >
                    <Plus size={ 14 } />
                    { __( 'New tag', 'snel-newsletter' ) }
                </button>
            </div>

            { loading ? (
                <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center text-sm text-gray-400">
                    { __( 'Loading...', 'snel-newsletter' ) }
                </div>
            ) : tags.length === 0 ? (
                <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
                    <Tag size={ 32 } className="mx-auto text-gray-300 mb-3" />
                    <p className="text-sm text-gray-500">{ __( 'No tags yet. Create one, or add tags to subscribers.', 'snel-newsletter' ) }</p>
                    <button
                        type="button"
                        onClick={ () => setCreating( true ) }
                        className="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                    >
                        <Plus size={ 14 } />
                        { __( 'New tag', 'snel-newsletter' ) }
                    </button>
                </div>
            ) : (
                <div className="space-y-6">
                    { dynamicTags.length > 0 && (
                        <div className="bg-white border border-amber-100 rounded-lg overflow-hidden">
                            <div className="px-5 py-3.5 border-b border-amber-50 flex items-center gap-2 bg-amber-50/50">
                                <Zap size={ 14 } className="text-amber-500" />
                                <span className="text-sm font-semibold text-amber-800">{ __( 'Dynamic Tags', 'snel-newsletter' ) }</span>
                                <span className="ml-1 px-2 py-0.5 text-xs text-amber-600 bg-amber-100 rounded-full">{ dynamicTags.length }</span>
                            </div>
                            { renderTable( dynamicTags ) }
                        </div>
                    ) }

                    { staticTags.length > 0 && (
                        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <div className="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2">
                                <Tag size={ 14 } className="text-gray-400" />
                                <span className="text-sm font-semibold text-gray-900">{ __( 'Static Tags', 'snel-newsletter' ) }</span>
                                <span className="ml-1 px-2 py-0.5 text-xs text-gray-500 bg-gray-100 rounded-full">{ staticTags.length }</span>
                            </div>
                            { renderTable( staticTags ) }
                        </div>
                    ) }
                </div>
            ) }

            { ( editingTag || creating ) && (
                <TagEditModal
                    tag={ editingTag }
                    onClose={ () => { setEditingTag( null ); setCreating( false ); } }
                    onSave={ handleSave }
                />
            ) }
        </div>
    );
}
