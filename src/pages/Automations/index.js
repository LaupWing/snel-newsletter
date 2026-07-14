import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Workflow, Plus, Trash2, Loader2, Play, Pause, X } from 'lucide-react';
import Builder from './Builder';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

export default function Automations() {
    const [ automations, setAutomations ] = useState( [] );
    const [ loading, setLoading ]         = useState( true );
    const [ openId, setOpenId ]           = useState( null );
    const [ creating, setCreating ]       = useState( false );
    const [ showNameModal, setShowNameModal ] = useState( false );
    const [ newName, setNewName ]         = useState( '' );
    const inputRef = useRef( null );

    const load = useCallback( () => {
        setLoading( true );
        api( '/automations' ).then( ( data ) => {
            setAutomations( Array.isArray( data ) ? data : [] );
            setLoading( false );
        } );
    }, [] );

    useEffect( () => { load(); }, [ load ] );

    const openNameModal = () => {
        setNewName( '' );
        setShowNameModal( true );
        setTimeout( () => inputRef.current?.focus(), 50 );
    };

    const handleCreate = async () => {
        const name = newName.trim() || __( 'Welcome series', 'snel-newsletter' );
        setShowNameModal( false );
        setCreating( true );
        const res = await api( '/automations', {
            method: 'POST',
            body: JSON.stringify( { name, trigger_type: 'tag', steps: [] } ),
        } );
        setCreating( false );
        if ( res.id ) setOpenId( res.id );
        load();
    };

    const handleDelete = ( a ) => {
        if ( ! confirm( `${ __( 'Delete automation', 'snel-newsletter' ) } "${ a.name }"? ${ __( 'Enrolled subscribers will stop receiving its emails.', 'snel-newsletter' ) }` ) ) return;
        api( `/automations/${ a.id }`, { method: 'DELETE' } ).then( () => load() );
    };

    const handleToggle = ( a ) => {
        const status = a.status === 'active' ? 'paused' : 'active';
        api( `/automations/${ a.id }`, { method: 'PUT', body: JSON.stringify( { status } ) } ).then( () => load() );
    };

    if ( openId ) {
        return <Builder automationId={ openId } onClose={ () => { setOpenId( null ); load(); } } />;
    }

    return (
        <div className="p-6">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Automations — enroll subscribers, send sequences, branch on opens', 'snel-newsletter' ) }</p>
                </div>
                <button
                    type="button"
                    onClick={ openNameModal }
                    disabled={ creating }
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 rounded-lg transition-colors"
                >
                    { creating ? <Loader2 size={ 14 } className="animate-spin" /> : <Plus size={ 14 } /> }
                    { __( 'New Automation', 'snel-newsletter' ) }
                </button>
            </div>

            { loading ? (
                <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
                    <Loader2 size={ 20 } className="mx-auto animate-spin text-gray-400" />
                </div>
            ) : automations.length === 0 ? (
                <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
                    <Workflow size={ 32 } className="mx-auto text-gray-300 mb-3" />
                    <p className="text-sm text-gray-500 mb-3">{ __( 'No automations yet', 'snel-newsletter' ) }</p>
                    <button
                        type="button"
                        onClick={ openNameModal }
                        className="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                    >
                        <Plus size={ 14 } />
                        { __( 'Create your first automation', 'snel-newsletter' ) }
                    </button>
                </div>
            ) : (
                <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50/50">
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Automation', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Status', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Trigger', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Enrolled', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'In progress', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Completed', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 w-24"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            { automations.map( ( a ) => (
                                <tr key={ a.id } className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-4 py-3">
                                        <button type="button" onClick={ () => setOpenId( a.id ) } className="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors">
                                            { a.name }
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={ `inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full ${ a.status === 'active' ? 'text-emerald-700 bg-emerald-50' : 'text-gray-500 bg-gray-100' }` }>
                                            <span className={ `w-1.5 h-1.5 rounded-full ${ a.status === 'active' ? 'bg-emerald-500' : 'bg-gray-400' }` } />
                                            { a.status === 'active' ? __( 'Active', 'snel-newsletter' ) : __( 'Paused', 'snel-newsletter' ) }
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-500">
                                        { a.trigger_type === 'manual'
                                            ? __( 'Manual enroll only', 'snel-newsletter' )
                                            : <>{ __( 'Tag added:', 'snel-newsletter' ) } <span className="px-2 py-0.5 text-xs font-medium text-purple-700 bg-purple-50 rounded-full">{ a.trigger_tag || '—' }</span></> }
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{ a.enrolled }</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{ a.in_progress }</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{ a.completed }</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                onClick={ () => handleToggle( a ) }
                                                className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                                title={ a.status === 'active' ? __( 'Pause', 'snel-newsletter' ) : __( 'Activate', 'snel-newsletter' ) }
                                            >
                                                { a.status === 'active' ? <Pause size={ 13 } /> : <Play size={ 13 } /> }
                                            </button>
                                            <button
                                                type="button"
                                                onClick={ () => handleDelete( a ) }
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
                </div>
            ) }

            { showNameModal && (
                <div className="fixed inset-0 z-[100000] flex items-center justify-center">
                    <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={ () => setShowNameModal( false ) } />
                    <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6 border border-gray-200">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-gray-900">{ __( 'New Automation', 'snel-newsletter' ) }</h3>
                            <button
                                type="button"
                                onClick={ () => setShowNameModal( false ) }
                                className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                <X size={ 16 } />
                            </button>
                        </div>
                        <input
                            ref={ inputRef }
                            type="text"
                            value={ newName }
                            onChange={ ( e ) => setNewName( e.target.value ) }
                            onKeyDown={ ( e ) => {
                                if ( e.key === 'Enter' ) handleCreate();
                                if ( e.key === 'Escape' ) setShowNameModal( false );
                            } }
                            placeholder={ __( 'e.g. Welcome series', 'snel-newsletter' ) }
                            className="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition-colors"
                        />
                        <div className="flex justify-end gap-2 mt-4">
                            <button
                                type="button"
                                onClick={ () => setShowNameModal( false ) }
                                className="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 transition-colors"
                            >
                                { __( 'Cancel', 'snel-newsletter' ) }
                            </button>
                            <button
                                type="button"
                                onClick={ handleCreate }
                                className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                            >
                                { __( 'Create', 'snel-newsletter' ) }
                            </button>
                        </div>
                    </div>
                </div>
            ) }
        </div>
    );
}
