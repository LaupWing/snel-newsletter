import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MoreHorizontal } from 'lucide-react';
import TagBadge from '../../components/TagBadge';

const STATUS_STYLES = {
    active: 'bg-emerald-50 text-emerald-700',
    unsubscribed: 'bg-gray-100 text-gray-600',
    bounced: 'bg-red-50 text-red-700',
};

export { STATUS_STYLES };

export default function SubscriberRow( { subscriber, selected, onSelect, onDelete } ) {
    const [ menuOpen, setMenuOpen ] = useState( false );

    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
            <td className="px-4 py-3">
                <input
                    type="checkbox"
                    checked={ selected }
                    onChange={ onSelect }
                    className="rounded border-gray-300"
                />
            </td>
            <td className="px-4 py-3">
                <p className="text-sm font-medium text-gray-900">{ subscriber.email }</p>
            </td>
            <td className="px-4 py-3">
                <p className="text-sm text-gray-600">{ subscriber.name || <span className="text-gray-300">—</span> }</p>
            </td>
            <td className="px-4 py-3">
                <span className={ `inline-block px-2 py-0.5 text-xs font-medium rounded-full ${ STATUS_STYLES[ subscriber.status ] }` }>
                    { subscriber.status }
                </span>
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap gap-1">
                    { subscriber.tags.length > 0
                        ? subscriber.tags.map( ( tag ) => <TagBadge key={ tag } tag={ tag } /> )
                        : <span className="text-xs text-gray-300">—</span>
                    }
                </div>
            </td>
            <td className="px-4 py-3">
                <p className="text-xs text-gray-400">{ subscriber.created_at }</p>
            </td>
            <td className="px-4 py-3">
                <div className="relative">
                    <button
                        type="button"
                        onClick={ () => setMenuOpen( ! menuOpen ) }
                        className="p-1 text-gray-400 hover:text-gray-600 rounded transition-colors"
                    >
                        <MoreHorizontal size={ 14 } />
                    </button>
                    { menuOpen && (
                        <>
                            <div className="fixed inset-0 z-10" onClick={ () => setMenuOpen( false ) } />
                            <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1 w-36">
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    { __( 'Edit', 'snel-newsletter' ) }
                                </button>
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    { __( 'Manage Tags', 'snel-newsletter' ) }
                                </button>
                                <button type="button" onClick={ () => { setMenuOpen( false ); onDelete && onDelete(); } } className="w-full text-left px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    { __( 'Delete', 'snel-newsletter' ) }
                                </button>
                            </div>
                        </>
                    ) }
                </div>
            </td>
        </tr>
    );
}
