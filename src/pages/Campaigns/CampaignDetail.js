import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Eye, MousePointerClick, Users, Clock, CheckCircle, XCircle, Loader2 } from 'lucide-react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

function api( path ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
    } ).then( ( r ) => r.json() );
}

const SUB_STATUS = {
    sent:     { bg: 'bg-emerald-50 text-emerald-700', label: 'Sent' },
    pending:  { bg: 'bg-gray-100 text-gray-500',      label: 'Pending' },
    failed:   { bg: 'bg-red-50 text-red-600',         label: 'Failed' },
    retrying: { bg: 'bg-amber-50 text-amber-600',     label: 'Retrying' },
};

function StatCard( { icon: Icon, label, value, sub, color = 'blue' } ) {
    const colors = {
        blue:   'bg-blue-50 text-blue-600',
        emerald:'bg-emerald-50 text-emerald-600',
        purple: 'bg-purple-50 text-purple-600',
        red:    'bg-red-50 text-red-600',
    };
    return (
        <div className="bg-white border border-gray-200 rounded-lg p-4 flex items-center gap-4">
            <div className={ `w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${ colors[ color ] }` }>
                <Icon size={ 18 } />
            </div>
            <div>
                <p className="text-xs text-gray-400">{ label }</p>
                <p className="text-xl font-bold text-gray-900 leading-tight">{ value }</p>
                { sub && <p className="text-xs text-gray-400 mt-0.5">{ sub }</p> }
            </div>
        </div>
    );
}

export default function CampaignDetail( { campaignId, onClose } ) {
    const [ campaign, setCampaign ] = useState( null );
    const [ loading,  setLoading  ] = useState( true );

    useEffect( () => {
        setLoading( true );
        api( `/campaigns/${ campaignId }/stats` ).then( ( data ) => {
            setCampaign( data );
            setLoading( false );
        } );
    }, [ campaignId ] );

    const c          = { tags: [], subscribers: [], ...( campaign || {} ) };
    const progress   = c.recipients > 0 ? Math.round( ( ( c.sent || 0 ) / c.recipients ) * 100 ) : 0;
    const openRate   = c.sent > 0 ? Math.round( ( ( c.opened  || 0 ) / c.sent ) * 100 ) : 0;
    const clickRate  = c.sent > 0 ? Math.round( ( ( c.clicked || 0 ) / c.sent ) * 100 ) : 0;

    return (
        <>
            { /* Backdrop */ }
            <div
                className="fixed inset-0 bg-black/50 z-40"
                onClick={ onClose }
            />

            { /* Modal */ }
            <div className="fixed inset-0 z-50 flex items-center justify-center p-6 pointer-events-none">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col pointer-events-auto">

                { /* Modal header */ }
                <div className="flex items-start justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div className="flex-1 min-w-0 pr-4">
                        <h2 className="text-base font-semibold text-gray-900 truncate">{ c.subject }</h2>
                        <div className="flex items-center gap-2 mt-1">
                            <span className={ `inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ${ c.status === 'sending' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }` }>
                                { c.status === 'sending' && <Loader2 size={ 10 } className="animate-spin" /> }
                                { c.status === 'sending' ? 'Sending' : 'Sent' }
                            </span>
                            { c.tags.map( ( t ) => (
                                <span key={ t } className="px-1.5 py-0.5 text-[10px] font-medium bg-purple-50 text-purple-600 rounded">{ t }</span>
                            ) ) }
                            { c.sent_at && (
                                <span className="flex items-center gap-1 text-xs text-gray-400">
                                    <Clock size={ 10 } /> { c.sent_at }
                                </span>
                            ) }
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={ onClose }
                        className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors shrink-0"
                    >
                        <X size={ 16 } />
                    </button>
                </div>

                { /* Scrollable content */ }
                <div className="flex-1 overflow-y-auto p-6 space-y-4">

                    { loading && (
                        <div className="flex items-center justify-center py-16">
                            <Loader2 size={ 24 } className="animate-spin text-gray-400" />
                        </div>
                    ) }

                    { ! loading && campaign && <>

                    { /* Progress bar */ }
                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-gray-600">{ __( 'Send progress', 'snel-newsletter' ) }</span>
                            <span className="text-sm font-semibold text-gray-900">{ c.sent.toLocaleString() } <span className="text-gray-400 font-normal">/ { c.recipients.toLocaleString() }</span></span>
                        </div>
                        <div className="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-blue-500 rounded-full transition-all"
                                style={ { width: `${ progress }%` } }
                            />
                        </div>
                        <p className="text-xs text-gray-400 mt-1.5">{ progress }% complete</p>
                    </div>

                    { /* Stat cards */ }
                    <div className="grid grid-cols-2 gap-3">
                        <StatCard icon={ Users }             label={ __( 'Total recipients', 'snel-newsletter' ) } value={ c.recipients.toLocaleString() }  color="blue" />
                        <StatCard icon={ Eye }               label={ __( 'Open rate', 'snel-newsletter' ) }        value={ `${ openRate }%` }               sub={ `${ c.opened.toLocaleString() } opens` }   color="emerald" />
                        <StatCard icon={ MousePointerClick } label={ __( 'Click rate', 'snel-newsletter' ) }       value={ `${ clickRate }%` }              sub={ `${ c.clicked.toLocaleString() } clicks` } color="purple" />
                        <StatCard icon={ XCircle }           label={ __( 'Failed', 'snel-newsletter' ) }           value={ c.failed }                       sub={ __( 'will retry', 'snel-newsletter' ) }    color="red" />
                    </div>

                    { /* Subscriber table */ }
                    <div className="bg-white border border-gray-200 rounded-lg">
                        <div className="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p className="text-sm font-medium text-gray-900">{ __( 'Subscribers', 'snel-newsletter' ) }</p>
                            <span className="text-xs text-gray-400">{ __( 'Showing first 50', 'snel-newsletter' ) }</span>
                        </div>
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/50">
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Email', 'snel-newsletter' ) }</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Status', 'snel-newsletter' ) }</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Opened', 'snel-newsletter' ) }</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Clicked', 'snel-newsletter' ) }</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Sent at', 'snel-newsletter' ) }</th>
                                </tr>
                            </thead>
                            <tbody>
                                { c.subscribers.map( ( sub ) => {
                                    const s = SUB_STATUS[ sub.status ] || SUB_STATUS.pending;
                                    return (
                                        <tr key={ sub.id } className="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                            <td className="px-4 py-2.5 text-sm text-gray-700 font-mono">{ sub.email }</td>
                                            <td className="px-4 py-2.5">
                                                <span className={ `inline-flex px-2 py-0.5 text-xs font-medium rounded-full ${ s.bg }` }>{ s.label }</span>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                { sub.opened
                                                    ? <CheckCircle size={ 14 } className="text-emerald-500" />
                                                    : <span className="text-xs text-gray-300">—</span>
                                                }
                                            </td>
                                            <td className="px-4 py-2.5">
                                                { sub.clicked
                                                    ? <CheckCircle size={ 14 } className="text-purple-500" />
                                                    : <span className="text-xs text-gray-300">—</span>
                                                }
                                            </td>
                                            <td className="px-4 py-2.5 text-xs text-gray-400">{ sub.sent_at || '—' }</td>
                                        </tr>
                                    );
                                } ) }
                            </tbody>
                        </table>
                    </div>
                    </> }
                </div>
            </div>
            </div>
        </>
    );
}
