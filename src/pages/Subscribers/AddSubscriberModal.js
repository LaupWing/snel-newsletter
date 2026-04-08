import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X } from 'lucide-react';
import TagPicker from '../../components/TagPicker';

export default function AddSubscriberModal( { onClose, allTags, onAdd } ) {
    const [ email, setEmail ] = useState( '' );
    const [ name, setName ] = useState( '' );
    const [ selectedTags, setSelectedTags ] = useState( [] );

    return (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50" onClick={ onClose }>
            <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-6" onClick={ ( e ) => e.stopPropagation() }>
                <div className="flex items-center justify-between mb-5">
                    <h3 className="text-sm font-semibold text-gray-900">{ __( 'Add Subscriber', 'snel-newsletter' ) }</h3>
                    <button type="button" onClick={ onClose } className="text-gray-400 hover:text-gray-600 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>
                <div className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">{ __( 'Email', 'snel-newsletter' ) } *</label>
                        <input
                            type="email"
                            value={ email }
                            onChange={ ( e ) => setEmail( e.target.value ) }
                            placeholder="subscriber@example.com"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">{ __( 'Name', 'snel-newsletter' ) }</label>
                        <input
                            type="text"
                            value={ name }
                            onChange={ ( e ) => setName( e.target.value ) }
                            placeholder="John Doe"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">{ __( 'Tags', 'snel-newsletter' ) }</label>
                        <TagPicker allTags={ allTags } selectedTags={ selectedTags } onChange={ setSelectedTags } />
                    </div>
                </div>
                <div className="flex items-center justify-end gap-2 mt-6">
                    <button
                        type="button"
                        onClick={ onClose }
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        { __( 'Cancel', 'snel-newsletter' ) }
                    </button>
                    <button
                        type="button"
                        onClick={ () => onAdd && onAdd( { email, name, tags: selectedTags } ) }
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                        disabled={ ! email }
                    >
                        { __( 'Add Subscriber', 'snel-newsletter' ) }
                    </button>
                </div>
            </div>
        </div>
    );
}
