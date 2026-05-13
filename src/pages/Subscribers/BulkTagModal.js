import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Tag, Plus, Minus } from 'lucide-react';
import TagBadge from '../../components/TagBadge';

export default function BulkTagModal( { selectedCount, allTags, onClose, onApply } ) {
    const [ toAdd, setToAdd ] = useState( [] );
    const [ toRemove, setToRemove ] = useState( [] );
    const [ newTag, setNewTag ] = useState( '' );
    const [ saving, setSaving ] = useState( false );

    const toggleAdd = ( tag ) => {
        setToAdd( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
        setToRemove( ( prev ) => prev.filter( ( t ) => t !== tag ) );
    };

    const toggleRemove = ( tag ) => {
        setToRemove( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
        setToAdd( ( prev ) => prev.filter( ( t ) => t !== tag ) );
    };

    const handleAddNew = () => {
        const tag = newTag.trim().toLowerCase().replace( /[^a-z0-9-]/g, '-' ).replace( /-+/g, '-' );
        if ( tag && ! toAdd.includes( tag ) ) {
            setToAdd( ( prev ) => [ ...prev, tag ] );
            setToRemove( ( prev ) => prev.filter( ( t ) => t !== tag ) );
        }
        setNewTag( '' );
    };

    const handleApply = async () => {
        if ( ! toAdd.length && ! toRemove.length ) return;
        setSaving( true );
        await onApply( { add: toAdd, remove: toRemove } );
        setSaving( false );
        onClose();
    };

    const hasChanges = toAdd.length > 0 || toRemove.length > 0;

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">{ __( 'Edit Tags', 'snel-newsletter' ) }</h2>
                        <p className="text-xs text-gray-500 mt-0.5">
                            { selectedCount } { __( 'subscriber(s) selected', 'snel-newsletter' ) }
                        </p>
                    </div>
                    <button type="button" onClick={ onClose } className="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>

                <div className="p-5 space-y-4">
                    {/* Add new tag input */}
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1.5">{ __( 'Add new tag', 'snel-newsletter' ) }</label>
                        <div className="flex items-center gap-2">
                            <input
                                type="text"
                                value={ newTag }
                                onChange={ ( e ) => setNewTag( e.target.value ) }
                                onKeyDown={ ( e ) => e.key === 'Enter' && handleAddNew() }
                                placeholder={ __( 'Type a tag name...', 'snel-newsletter' ) }
                                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                            />
                            <button
                                type="button"
                                onClick={ handleAddNew }
                                disabled={ ! newTag.trim() }
                                className="px-3 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 rounded-lg transition-colors"
                            >
                                { __( 'Add', 'snel-newsletter' ) }
                            </button>
                        </div>
                    </div>

                    {/* Existing tags */}
                    { allTags.length > 0 && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1.5">{ __( 'Existing tags', 'snel-newsletter' ) }</label>
                            <div className="space-y-1 max-h-48 overflow-y-auto">
                                { allTags.map( ( tag ) => {
                                    const adding   = toAdd.includes( tag );
                                    const removing = toRemove.includes( tag );
                                    return (
                                        <div key={ tag } className={ `flex items-center justify-between px-3 py-2 rounded-lg border transition-colors ${ adding ? 'bg-emerald-50 border-emerald-200' : removing ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-100' }` }>
                                            <span className={ `text-xs font-medium ${ adding ? 'text-emerald-700' : removing ? 'text-red-600 line-through' : 'text-gray-700' }` }>
                                                { tag }
                                            </span>
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={ () => toggleAdd( tag ) }
                                                    title={ __( 'Add to selected', 'snel-newsletter' ) }
                                                    className={ `p-1 rounded transition-colors ${ adding ? 'text-emerald-600 bg-emerald-100' : 'text-gray-400 hover:text-emerald-600 hover:bg-emerald-50' }` }
                                                >
                                                    <Plus size={ 12 } />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={ () => toggleRemove( tag ) }
                                                    title={ __( 'Remove from selected', 'snel-newsletter' ) }
                                                    className={ `p-1 rounded transition-colors ${ removing ? 'text-red-600 bg-red-100' : 'text-gray-400 hover:text-red-600 hover:bg-red-50' }` }
                                                >
                                                    <Minus size={ 12 } />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                } ) }
                            </div>
                        </div>
                    ) }

                    {/* Summary */}
                    { hasChanges && (
                        <div className="bg-gray-50 rounded-lg px-3 py-2.5 space-y-1.5">
                            { toAdd.length > 0 && (
                                <div className="flex items-center gap-1.5 flex-wrap">
                                    <span className="text-xs text-gray-500">{ __( 'Adding:', 'snel-newsletter' ) }</span>
                                    { toAdd.map( ( t ) => <TagBadge key={ t } tag={ t } /> ) }
                                </div>
                            ) }
                            { toRemove.length > 0 && (
                                <div className="flex items-center gap-1.5 flex-wrap">
                                    <span className="text-xs text-gray-500">{ __( 'Removing:', 'snel-newsletter' ) }</span>
                                    { toRemove.map( ( t ) => (
                                        <span key={ t } className="px-2 py-0.5 text-xs text-red-600 bg-red-50 rounded-full line-through">{ t }</span>
                                    ) ) }
                                </div>
                            ) }
                        </div>
                    ) }
                </div>

                <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100">
                    <button type="button" onClick={ onClose } className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors">
                        { __( 'Cancel', 'snel-newsletter' ) }
                    </button>
                    <button
                        type="button"
                        onClick={ handleApply }
                        disabled={ ! hasChanges || saving }
                        className="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-40 rounded-lg transition-colors"
                    >
                        { saving ? __( 'Applying...', 'snel-newsletter' ) : __( 'Apply', 'snel-newsletter' ) }
                    </button>
                </div>
            </div>
        </div>
    );
}
