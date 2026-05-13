import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Tag, Pencil, Trash2, Check, X, Users } from 'lucide-react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

export default function Tags() {
    const [ tags, setTags ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ editingTag, setEditingTag ] = useState( null );
    const [ editValue, setEditValue ] = useState( '' );
    const [ saving, setSaving ] = useState( false );

    const loadTags = useCallback( () => {
        setLoading( true );
        api( '/tags' ).then( ( data ) => {
            setTags( data || [] );
            setLoading( false );
        } );
    }, [] );

    useEffect( () => { loadTags(); }, [ loadTags ] );

    const startEdit = ( tag ) => {
        setEditingTag( tag.tag );
        setEditValue( tag.tag );
    };

    const cancelEdit = () => {
        setEditingTag( null );
        setEditValue( '' );
    };

    const handleRename = ( oldTag ) => {
        const newTag = editValue.trim().toLowerCase().replace( /[^a-z0-9-]/g, '-' ).replace( /-+/g, '-' );
        if ( ! newTag || newTag === oldTag ) {
            cancelEdit();
            return;
        }
        setSaving( true );
        api( `/tags/${ encodeURIComponent( oldTag ) }`, {
            method: 'PUT',
            body: JSON.stringify( { new_tag: newTag } ),
        } ).then( () => {
            setSaving( false );
            cancelEdit();
            loadTags();
        } );
    };

    const handleDelete = ( tag ) => {
        if ( ! confirm( `${ __( 'Delete tag', 'snel-newsletter' ) } "${ tag }"? ${ __( 'It will be removed from all subscribers.', 'snel-newsletter' ) }` ) ) return;
        api( `/tags/${ encodeURIComponent( tag ) }`, { method: 'DELETE' } ).then( () => {
            loadTags();
        } );
    };

    return (
        <div className="p-6">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Manage subscriber tags', 'snel-newsletter' ) }</p>
                </div>
            </div>

            <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div className="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2">
                    <Tag size={ 14 } className="text-gray-400" />
                    <span className="text-sm font-semibold text-gray-900">{ __( 'All Tags', 'snel-newsletter' ) }</span>
                    <span className="ml-1 px-2 py-0.5 text-xs text-gray-500 bg-gray-100 rounded-full">{ tags.length }</span>
                </div>

                { loading ? (
                    <div className="px-5 py-12 text-center text-sm text-gray-400">{ __( 'Loading...', 'snel-newsletter' ) }</div>
                ) : tags.length === 0 ? (
                    <div className="px-5 py-12 text-center">
                        <Tag size={ 32 } className="mx-auto text-gray-300 mb-3" />
                        <p className="text-sm text-gray-500">{ __( 'No tags yet. Add tags to subscribers to see them here.', 'snel-newsletter' ) }</p>
                    </div>
                ) : (
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50/50">
                                <th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Tag', 'snel-newsletter' ) }</th>
                                <th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div className="flex items-center gap-1">
                                        <Users size={ 11 } />
                                        { __( 'Subscribers', 'snel-newsletter' ) }
                                    </div>
                                </th>
                                <th className="px-5 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-28">{ __( 'Actions', 'snel-newsletter' ) }</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            { tags.map( ( t ) => (
                                <tr key={ t.tag } className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-5 py-3">
                                        { editingTag === t.tag ? (
                                            <input
                                                type="text"
                                                value={ editValue }
                                                onChange={ ( e ) => setEditValue( e.target.value ) }
                                                onKeyDown={ ( e ) => {
                                                    if ( e.key === 'Enter' ) handleRename( t.tag );
                                                    if ( e.key === 'Escape' ) cancelEdit();
                                                } }
                                                autoFocus
                                                className="px-2 py-1 border border-blue-400 rounded-md text-sm focus:outline-none focus:shadow-[0_0_0_1px_#3b82f6] w-48"
                                            />
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-purple-700 bg-purple-50 border border-purple-100 rounded-full">
                                                <Tag size={ 10 } />
                                                { t.tag }
                                            </span>
                                        ) }
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className="text-sm text-gray-600">{ t.count }</span>
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            { editingTag === t.tag ? (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={ () => handleRename( t.tag ) }
                                                        disabled={ saving }
                                                        className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors disabled:opacity-40"
                                                        title={ __( 'Save', 'snel-newsletter' ) }
                                                    >
                                                        <Check size={ 14 } />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={ cancelEdit }
                                                        className="p-1.5 text-gray-400 hover:bg-gray-100 rounded-lg transition-colors"
                                                        title={ __( 'Cancel', 'snel-newsletter' ) }
                                                    >
                                                        <X size={ 14 } />
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={ () => startEdit( t ) }
                                                        className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                        title={ __( 'Rename', 'snel-newsletter' ) }
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
                                                </>
                                            ) }
                                        </div>
                                    </td>
                                </tr>
                            ) ) }
                        </tbody>
                    </table>
                ) }
            </div>
        </div>
    );
}
