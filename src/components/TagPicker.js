import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Tag, ChevronDown, Plus } from 'lucide-react';
import TagBadge from './TagBadge';

/**
 * Reusable tag picker with click-outside, existing tags, and inline add.
 *
 * @param {string[]} allTags        - Available tags to pick from
 * @param {string[]} selectedTags   - Currently selected tags
 * @param {Function} onChange       - Called with updated tags array
 */
export default function TagPicker( { allTags, selectedTags, onChange } ) {
    const [ open, setOpen ] = useState( false );
    const [ newTag, setNewTag ] = useState( '' );
    const ref = useRef();

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( ref.current && ! ref.current.contains( e.target ) ) setOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    const toggleTag = ( tag ) => {
        onChange(
            selectedTags.includes( tag )
                ? selectedTags.filter( ( t ) => t !== tag )
                : [ ...selectedTags, tag ]
        );
    };

    const handleAddNew = () => {
        const tag = newTag.trim().toLowerCase().replace( /[^a-z0-9-]/g, '-' ).replace( /-+/g, '-' );
        if ( tag && ! selectedTags.includes( tag ) ) {
            onChange( [ ...selectedTags, tag ] );
        }
        setNewTag( '' );
    };

    const unselected = allTags.filter( ( t ) => ! selectedTags.includes( t ) );

    return (
        <div className="space-y-2">
            <div className="relative" ref={ ref }>
                <button
                    type="button"
                    onClick={ () => setOpen( ! open ) }
                    className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    <Tag size={ 12 } className="text-gray-400" />
                    <span className="text-gray-700">
                        { selectedTags.length > 0
                            ? `${ selectedTags.length } tag${ selectedTags.length > 1 ? 's' : '' } selected`
                            : __( 'Select tags...', 'snel-newsletter' )
                        }
                    </span>
                    <ChevronDown size={ 12 } className={ `text-gray-400 transition-transform ${ open ? 'rotate-180' : '' }` } />
                </button>
                { open && (
                    <div className="absolute left-0 top-full mt-1 bg-white rounded-lg shadow-lg ring-1 ring-black/10 py-1 z-50 min-w-[200px] max-h-60 overflow-y-auto">
                        {/* Add new tag input */}
                        <div className="px-2 py-1.5 border-b border-gray-100">
                            <div className="flex items-center gap-1">
                                <input
                                    type="text"
                                    value={ newTag }
                                    onChange={ ( e ) => setNewTag( e.target.value ) }
                                    onKeyDown={ ( e ) => e.key === 'Enter' && handleAddNew() }
                                    placeholder={ __( 'New tag...', 'snel-newsletter' ) }
                                    className="flex-1 px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:border-blue-500"
                                />
                                <button
                                    type="button"
                                    onClick={ handleAddNew }
                                    disabled={ ! newTag.trim() }
                                    className="p-1 text-blue-600 hover:text-blue-700 disabled:opacity-30 transition-colors"
                                >
                                    <Plus size={ 14 } />
                                </button>
                            </div>
                        </div>
                        {/* Existing tags */}
                        { unselected.length > 0 ? (
                            unselected.map( ( tag ) => (
                                <button
                                    key={ tag }
                                    type="button"
                                    onClick={ () => toggleTag( tag ) }
                                    className="w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors"
                                >
                                    { tag }
                                </button>
                            ) )
                        ) : (
                            <p className="px-3 py-2 text-xs text-gray-400">{ __( 'No more tags', 'snel-newsletter' ) }</p>
                        ) }
                        {/* Selected tags at bottom */}
                        { selectedTags.length > 0 && (
                            <>
                                <div className="border-t border-gray-100 mt-1 pt-1">
                                    <p className="px-3 py-1 text-[10px] text-gray-400 uppercase">{ __( 'Selected', 'snel-newsletter' ) }</p>
                                    { selectedTags.map( ( tag ) => (
                                        <button
                                            key={ tag }
                                            type="button"
                                            onClick={ () => toggleTag( tag ) }
                                            className="w-full text-left px-3 py-2 text-xs bg-purple-50 text-purple-700 font-medium hover:bg-purple-100 transition-colors"
                                        >
                                            { tag }
                                        </button>
                                    ) ) }
                                </div>
                            </>
                        ) }
                    </div>
                ) }
            </div>
            { selectedTags.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    { selectedTags.map( ( tag ) => (
                        <TagBadge key={ tag } tag={ tag } removable onRemove={ () => toggleTag( tag ) } />
                    ) ) }
                </div>
            ) }
        </div>
    );
}
