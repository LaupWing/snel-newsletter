import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Tag, Zap, RefreshCw } from 'lucide-react';

const METRICS = [
    { value: 'open_rate',       label: 'Open rate',        unit: '%' },
    { value: 'click_rate',      label: 'Click rate',       unit: '%' },
    { value: 'opens',           label: 'Total opens',      unit: '' },
    { value: 'clicks',          label: 'Total clicks',     unit: '' },
    { value: 'emails_received', label: 'Emails received',  unit: '' },
];

const OPERATORS = [
    { value: 'gt',  label: 'higher than' },
    { value: 'gte', label: 'at least' },
    { value: 'lt',  label: 'lower than' },
    { value: 'lte', label: 'at most' },
    { value: 'eq',  label: 'equal to' },
];

export default function TagEditModal( { tag, onClose, onSave } ) {
    const [ name, setName ]           = useState( tag.tag );
    const [ type, setType ]           = useState( tag.type || 'static' );
    const [ metric, setMetric ]       = useState( tag.metric || 'open_rate' );
    const [ operator, setOperator ]   = useState( tag.operator || 'gt' );
    const [ threshold, setThreshold ] = useState( tag.threshold ?? '' );
    const [ saving, setSaving ]       = useState( false );
    const [ syncResult, setSyncResult ] = useState( null );

    const selectedMetric = METRICS.find( ( m ) => m.value === metric );

    const handleSave = async () => {
        setSaving( true );
        setSyncResult( null );
        const result = await onSave( tag.tag, {
            new_tag:   name.trim().toLowerCase().replace( /[^a-z0-9-]/g, '-' ).replace( /-+/g, '-' ),
            type,
            metric:    type === 'dynamic' ? metric : null,
            operator:  type === 'dynamic' ? operator : null,
            threshold: type === 'dynamic' ? parseFloat( threshold ) : null,
        } );
        setSaving( false );
        if ( result?.synced !== undefined && result.synced !== null ) {
            setSyncResult( result.synced );
            setTimeout( () => onClose(), 1500 );
        } else {
            onClose();
        }
    };

    const isValid = name.trim() && ( type === 'static' || ( metric && operator && threshold !== '' && ! isNaN( parseFloat( threshold ) ) ) );

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
                {/* Header */}
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 className="text-sm font-semibold text-gray-900">{ __( 'Edit Tag', 'snel-newsletter' ) }</h2>
                    <button type="button" onClick={ onClose } className="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>

                <div className="p-5 space-y-5">
                    {/* Tag name */}
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1.5">{ __( 'Tag name', 'snel-newsletter' ) }</label>
                        <div className="relative">
                            <Tag size={ 13 } className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                value={ name }
                                onChange={ ( e ) => setName( e.target.value ) }
                                className="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                            />
                        </div>
                    </div>

                    {/* Static / Dynamic toggle */}
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-2">{ __( 'Tag type', 'snel-newsletter' ) }</label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onClick={ () => setType( 'static' ) }
                                className={ `flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-colors ${ type === 'static'
                                    ? 'bg-purple-50 border-purple-300 text-purple-700'
                                    : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                }` }
                            >
                                <Tag size={ 14 } />
                                <div className="text-left">
                                    <div className="text-xs font-semibold">{ __( 'Static', 'snel-newsletter' ) }</div>
                                    <div className="text-[10px] opacity-70">{ __( 'Assigned manually', 'snel-newsletter' ) }</div>
                                </div>
                            </button>
                            <button
                                type="button"
                                onClick={ () => setType( 'dynamic' ) }
                                className={ `flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-colors ${ type === 'dynamic'
                                    ? 'bg-amber-50 border-amber-300 text-amber-700'
                                    : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                }` }
                            >
                                <Zap size={ 14 } />
                                <div className="text-left">
                                    <div className="text-xs font-semibold">{ __( 'Dynamic', 'snel-newsletter' ) }</div>
                                    <div className="text-[10px] opacity-70">{ __( 'Auto-assigned by rule', 'snel-newsletter' ) }</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    {/* Dynamic rule builder */}
                    { type === 'dynamic' && (
                        <div className="bg-amber-50 border border-amber-100 rounded-lg p-4 space-y-3">
                            <p className="text-xs font-medium text-amber-800">{ __( 'Assign this tag when...', 'snel-newsletter' ) }</p>

                            {/* Metric */}
                            <div>
                                <label className="block text-xs text-amber-700 mb-1">{ __( 'Metric', 'snel-newsletter' ) }</label>
                                <select
                                    value={ metric }
                                    onChange={ ( e ) => setMetric( e.target.value ) }
                                    className="w-full px-3 py-2 border border-amber-200 bg-white rounded-lg text-sm focus:outline-none focus:border-amber-400"
                                >
                                    { METRICS.map( ( m ) => (
                                        <option key={ m.value } value={ m.value }>{ m.label }</option>
                                    ) ) }
                                </select>
                            </div>

                            {/* Operator + threshold */}
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <label className="block text-xs text-amber-700 mb-1">{ __( 'Condition', 'snel-newsletter' ) }</label>
                                    <select
                                        value={ operator }
                                        onChange={ ( e ) => setOperator( e.target.value ) }
                                        className="w-full px-3 py-2 border border-amber-200 bg-white rounded-lg text-sm focus:outline-none focus:border-amber-400"
                                    >
                                        { OPERATORS.map( ( o ) => (
                                            <option key={ o.value } value={ o.value }>{ o.label }</option>
                                        ) ) }
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-amber-700 mb-1">
                                        { __( 'Value', 'snel-newsletter' ) }
                                        { selectedMetric?.unit && <span className="ml-1 text-amber-500">({ selectedMetric.unit })</span> }
                                    </label>
                                    <input
                                        type="number"
                                        value={ threshold }
                                        onChange={ ( e ) => setThreshold( e.target.value ) }
                                        min="0"
                                        step={ selectedMetric?.unit === '%' ? '0.1' : '1' }
                                        max={ selectedMetric?.unit === '%' ? '100' : undefined }
                                        placeholder="0"
                                        className="w-full px-3 py-2 border border-amber-200 bg-white rounded-lg text-sm focus:outline-none focus:border-amber-400"
                                    />
                                </div>
                            </div>

                            {/* Preview sentence */}
                            { metric && operator && threshold !== '' && (
                                <p className="text-xs text-amber-700 italic bg-amber-100 rounded px-2.5 py-1.5">
                                    { __( 'Tag subscribers whose', 'snel-newsletter' ) } <strong>{ METRICS.find( m => m.value === metric )?.label.toLowerCase() }</strong>{' '}
                                    { __( 'is', 'snel-newsletter' ) } <strong>{ OPERATORS.find( o => o.value === operator )?.label }</strong>{' '}
                                    <strong>{ threshold }{ selectedMetric?.unit }</strong>
                                </p>
                            ) }

                            { syncResult !== null && (
                                <p className="text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 rounded px-2.5 py-1.5 flex items-center gap-1.5">
                                    <RefreshCw size={ 11 } />
                                    { syncResult } { __( 'subscriber(s) matched and tagged', 'snel-newsletter' ) }
                                </p>
                            ) }
                        </div>
                    ) }
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100">
                    <button type="button" onClick={ onClose } className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors">
                        { __( 'Cancel', 'snel-newsletter' ) }
                    </button>
                    <button
                        type="button"
                        onClick={ handleSave }
                        disabled={ ! isValid || saving }
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 rounded-lg transition-colors"
                    >
                        { saving
                            ? ( type === 'dynamic' ? __( 'Saving & syncing...', 'snel-newsletter' ) : __( 'Saving...', 'snel-newsletter' ) )
                            : ( type === 'dynamic' ? __( 'Save & Sync', 'snel-newsletter' ) : __( 'Save', 'snel-newsletter' ) )
                        }
                    </button>
                </div>
            </div>
        </div>
    );
}
