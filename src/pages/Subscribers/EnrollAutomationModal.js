import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Workflow } from 'lucide-react';

export default function EnrollAutomationModal( { selectedCount, api, onClose, onDone } ) {
    const [ automations, setAutomations ] = useState( null );
    const [ picked, setPicked ]           = useState( 0 );
    const [ saving, setSaving ]           = useState( false );

    useEffect( () => {
        api( '/automations' ).then( ( data ) => setAutomations( Array.isArray( data ) ? data : [] ) );
    }, [ api ] );

    const handleEnroll = async () => {
        if ( ! picked ) return;
        setSaving( true );
        try {
            const res = await onDone( picked );
            alert( `${ res.enrolled } ${ __( 'subscriber(s) enrolled', 'snel-newsletter' ) }` );
            onClose();
        } catch ( e ) {
            alert( e.message );
            setSaving( false );
        }
    };

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">{ __( 'Enroll in Automation', 'snel-newsletter' ) }</h2>
                        <p className="text-xs text-gray-500 mt-0.5">
                            { selectedCount } { __( 'subscriber(s) selected', 'snel-newsletter' ) }
                        </p>
                    </div>
                    <button type="button" onClick={ onClose } className="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>

                <div className="p-5">
                    { automations === null ? (
                        <p className="text-sm text-gray-400 text-center py-4">{ __( 'Loading…', 'snel-newsletter' ) }</p>
                    ) : automations.length === 0 ? (
                        <div className="text-center py-4">
                            <Workflow size={ 28 } className="mx-auto text-gray-300 mb-2" />
                            <p className="text-sm text-gray-500">{ __( 'No automations yet. Create one under Newsletter → Automations.', 'snel-newsletter' ) }</p>
                        </div>
                    ) : (
                        <div className="space-y-1 max-h-64 overflow-y-auto">
                            { automations.map( ( a ) => (
                                <label
                                    key={ a.id }
                                    className={ `flex items-center justify-between px-3 py-2.5 rounded-lg border cursor-pointer transition-colors ${ picked === a.id ? 'bg-blue-50 border-blue-300' : 'bg-gray-50 border-gray-100 hover:border-gray-300' }` }
                                >
                                    <span className="flex items-center gap-2.5">
                                        <input
                                            type="radio"
                                            name="snel-enroll-automation"
                                            checked={ picked === a.id }
                                            onChange={ () => setPicked( a.id ) }
                                        />
                                        <span className="text-sm font-medium text-gray-800">{ a.name }</span>
                                    </span>
                                    <span className={ `px-2 py-0.5 text-[11px] font-medium rounded-full ${ a.status === 'active' ? 'text-emerald-700 bg-emerald-50' : 'text-gray-500 bg-gray-100' }` }>
                                        { a.status === 'active' ? __( 'Active', 'snel-newsletter' ) : __( 'Paused', 'snel-newsletter' ) }
                                    </span>
                                </label>
                            ) ) }
                        </div>
                    ) }
                    { !! picked && automations?.find( ( a ) => a.id === picked )?.status !== 'active' && (
                        <p className="text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2 mt-3">
                            { __( 'This automation is paused — subscribers will be enrolled but nothing runs until you activate it.', 'snel-newsletter' ) }
                        </p>
                    ) }
                </div>

                <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100">
                    <button type="button" onClick={ onClose } className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors">
                        { __( 'Cancel', 'snel-newsletter' ) }
                    </button>
                    <button
                        type="button"
                        onClick={ handleEnroll }
                        disabled={ ! picked || saving }
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 rounded-lg transition-colors"
                    >
                        { saving ? __( 'Enrolling…', 'snel-newsletter' ) : __( 'Enroll', 'snel-newsletter' ) }
                    </button>
                </div>
            </div>
        </div>
    );
}
