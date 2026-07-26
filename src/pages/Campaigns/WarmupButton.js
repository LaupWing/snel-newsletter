import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Flame, X, TrendingUp, Shield, Clock, Send, Zap, RotateCcw } from 'lucide-react';

const RAMP = [
    { day: 'Day 1',   limit: 200  },
    { day: 'Day 2',   limit: 500  },
    { day: 'Day 3',   limit: 1000 },
    { day: 'Day 4-5', limit: 2000 },
    { day: 'Day 6-7', limit: 5000 },
    { day: 'Day 8+',  limit: null  },
];

const LANES = [
    { key: 'broadcast',  label: __( 'Broadcasts', 'snel-newsletter' ),  hint: __( 'One-time sends to your lists', 'snel-newsletter' ),  icon: Send },
    { key: 'automation', label: __( 'Automations', 'snel-newsletter' ), hint: __( 'Emails from automation flows', 'snel-newsletter' ), icon: Zap },
];

function LaneControl( { lane, state, onToggle, onRestart } ) {
    const Icon    = lane.icon;
    const enabled = !! state?.enabled;
    const day     = state?.day;
    const cap     = state?.cap_today;
    const sent    = state?.sent_today || 0;

    return (
        <div className="border border-gray-200 rounded-lg p-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                    <div className={ `w-8 h-8 rounded-lg flex items-center justify-center ${ enabled ? 'bg-orange-50' : 'bg-gray-100' }` }>
                        <Icon size={ 14 } className={ enabled ? 'text-orange-500' : 'text-gray-400' } />
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-900">{ lane.label }</p>
                        <p className="text-[11px] text-gray-400">{ lane.hint }</p>
                    </div>
                </div>
                { enabled ? (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold text-orange-700 bg-orange-50 rounded-full">
                        <Flame size={ 10 } /> { day ? `${ __( 'Day', 'snel-newsletter' ) } ${ day }` : __( 'On', 'snel-newsletter' ) }
                    </span>
                ) : (
                    <span className="text-[11px] font-medium text-gray-400">{ __( 'Off', 'snel-newsletter' ) }</span>
                ) }
            </div>

            { enabled && (
                <div className="mt-3">
                    <div className="flex items-center justify-between text-[11px] text-gray-500 mb-1">
                        <span>{ __( 'Sent today', 'snel-newsletter' ) }</span>
                        <span className="font-medium text-gray-700">
                            { cap === null || cap === undefined ? __( 'Unlimited', 'snel-newsletter' ) : `${ sent.toLocaleString() } / ${ cap.toLocaleString() }` }
                        </span>
                    </div>
                    { cap ? (
                        <div className="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div className="h-full bg-orange-500 rounded-full transition-all" style={ { width: `${ Math.min( 100, Math.round( ( sent / cap ) * 100 ) ) }%` } } />
                        </div>
                    ) : null }
                </div>
            ) }

            <div className="flex items-center gap-2 mt-3">
                { enabled ? (
                    <>
                        <button type="button" onClick={ () => onRestart( lane.key ) } className="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 rounded-lg transition-colors">
                            <RotateCcw size={ 12 } /> { __( 'Restart', 'snel-newsletter' ) }
                        </button>
                        <button type="button" onClick={ () => onToggle( lane.key, false ) } className="px-3 py-2 text-xs font-medium text-red-600 border border-red-200 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                            { __( 'Disable', 'snel-newsletter' ) }
                        </button>
                    </>
                ) : (
                    <button type="button" onClick={ () => onToggle( lane.key, true ) } className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white rounded-lg transition-all warmup-active">
                        <Flame size={ 12 } /> { __( 'Enable warmup', 'snel-newsletter' ) }
                    </button>
                ) }
            </div>
        </div>
    );
}

const TABS = [
    { key: 'progress', label: __( 'Progress', 'snel-newsletter' ) },
    { key: 'ramp',     label: __( 'Ramp', 'snel-newsletter' ) },
    { key: 'info',     label: __( 'Info', 'snel-newsletter' ) },
];

function WarmupModal( { status, onToggle, onRestart, onClose } ) {
    const [ tab, setTab ] = useState( 'progress' );
    return (
        <>
            <div className="fixed inset-0 bg-black/50 z-40" onClick={ onClose } />
            <div className="fixed inset-0 z-50 flex items-center justify-center p-6 pointer-events-none">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg pointer-events-auto flex flex-col max-h-[85vh]">

                <div className="flex items-start justify-between px-6 pt-6 pb-4 shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-orange-50">
                            <Flame size={ 20 } className="text-orange-500" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">{ __( 'Email Warmup', 'snel-newsletter' ) }</h2>
                            <p className="text-xs text-gray-400 mt-0.5">{ __( 'Each lane ramps on its own domain', 'snel-newsletter' ) }</p>
                        </div>
                    </div>
                    <button type="button" onClick={ onClose } className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex items-center gap-1 px-6 border-b border-gray-100 shrink-0">
                    { TABS.map( ( t ) => (
                        <button
                            key={ t.key }
                            type="button"
                            onClick={ () => setTab( t.key ) }
                            className={ `px-3 py-2 -mb-px text-sm font-medium border-b-2 transition-colors ${
                                tab === t.key ? 'border-orange-500 text-gray-900' : 'border-transparent text-gray-400 hover:text-gray-700'
                            }` }
                        >
                            { t.label }
                        </button>
                    ) ) }
                </div>

                <div className="px-6 py-5 overflow-y-auto flex-1">

                    {/* Progress — per-lane controls */}
                    { tab === 'progress' && (
                        <div className="space-y-3">
                            { LANES.map( ( lane ) => (
                                <LaneControl
                                    key={ lane.key }
                                    lane={ lane }
                                    state={ status?.[ lane.key ] }
                                    onToggle={ onToggle }
                                    onRestart={ onRestart }
                                />
                            ) ) }
                        </div>
                    ) }

                    {/* Ramp schedule */}
                    { tab === 'ramp' && (
                        <div className="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                            <p className="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200 flex items-center gap-2">
                                <TrendingUp size={ 12 } /> { __( 'Send ramp (per lane)', 'snel-newsletter' ) }
                            </p>
                            <div className="divide-y divide-gray-100">
                                { RAMP.map( ( row, i ) => (
                                    <div key={ i } className="flex items-center justify-between px-4 py-2">
                                        <div className="flex items-center gap-2">
                                            <Clock size={ 12 } className="text-gray-400" />
                                            <span className="text-sm text-gray-600">{ row.day }</span>
                                        </div>
                                        { row.limit ? (
                                            <span className="text-sm font-semibold text-gray-900">{ row.limit.toLocaleString() } / day</span>
                                        ) : (
                                            <span className="text-sm font-semibold text-emerald-600">{ __( 'Unlimited', 'snel-newsletter' ) }</span>
                                        ) }
                                    </div>
                                ) ) }
                            </div>
                        </div>
                    ) }

                    {/* Info */}
                    { tab === 'info' && (
                        <div className="flex items-start gap-3">
                            <div className="w-7 h-7 rounded-lg flex items-center justify-center bg-blue-50 shrink-0 mt-0.5">
                                <Shield size={ 13 } className="text-blue-500" />
                            </div>
                            <p className="text-sm text-gray-500 leading-relaxed">
                                { __( 'A fresh domain has zero reputation — blasting on day one triggers spam filters. Warmup ramps volume gradually. Broadcasts and automations warm up separately, so a bad automation never burns your broadcast reputation.', 'snel-newsletter' ) }
                            </p>
                        </div>
                    ) }

                </div>
            </div>
            </div>
        </>
    );
}

export default function WarmupButton( { status, onToggle, onRestart } ) {
    const [ modalOpen, setModalOpen ] = useState( false );
    const anyOn = !! ( status?.broadcast?.enabled || status?.automation?.enabled );

    return (
        <>
            { modalOpen && (
                <WarmupModal
                    status={ status }
                    onToggle={ onToggle }
                    onRestart={ onRestart }
                    onClose={ () => setModalOpen( false ) }
                />
            ) }

            <button
                type="button"
                onClick={ () => setModalOpen( true ) }
                className={ `inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium rounded-lg border transition-all ${ anyOn ? 'border-orange-200 bg-white hover:border-orange-300' : 'text-gray-600 border-gray-300 bg-white hover:border-orange-300 hover:text-orange-600' }` }
            >
                { anyOn
                    ? <span className="warmup-flame-icon" />
                    : <Flame size={ 14 } className="text-gray-400 shrink-0" />
                }
                { anyOn
                    ? <span className="warmup-flame-text">{ __( 'Warmup on', 'snel-newsletter' ) }</span>
                    : __( 'Warmup', 'snel-newsletter' )
                }
            </button>
        </>
    );
}
