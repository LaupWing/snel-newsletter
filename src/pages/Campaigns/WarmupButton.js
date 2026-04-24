import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Flame, X, TrendingUp, Shield, Clock, ChevronRight } from 'lucide-react';

const RAMP = [
    { day: 'Day 1',   limit: 200  },
    { day: 'Day 2',   limit: 500  },
    { day: 'Day 3',   limit: 1000 },
    { day: 'Day 4-5', limit: 2000 },
    { day: 'Day 6-7', limit: 5000 },
    { day: 'Day 8+',  limit: null  },
];

function WarmupModal( { active, onEnable, onDisable, onClose } ) {
    return (
        <>
            <div className="fixed inset-0 bg-black/50 z-40" onClick={ onClose } />
            <div className="fixed inset-0 z-50 flex items-center justify-center p-6 pointer-events-none">
            <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg pointer-events-auto flex flex-col max-h-[80vh]">

                { /* Header */ }
                <div className="flex items-start justify-between px-6 pt-6 pb-4 shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-orange-50">
                            <Flame size={ 20 } className="text-orange-500" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-gray-900">{ __( 'Email Warmup', 'snel-newsletter' ) }</h2>
                            <p className="text-xs text-gray-400 mt-0.5">{ __( 'Protect your sender reputation', 'snel-newsletter' ) }</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={ onClose }
                        className="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <X size={ 16 } />
                    </button>
                </div>

                <div className="px-6 py-5 space-y-5 overflow-y-auto flex-1">

                    { /* Why warmup */ }
                    <div className="space-y-3">
                        <div className="flex items-start gap-3">
                            <div className="w-7 h-7 rounded-lg flex items-center justify-center bg-blue-50 shrink-0 mt-0.5">
                                <Shield size={ 13 } className="text-blue-500" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-gray-800">{ __( 'Why does warmup matter?', 'snel-newsletter' ) }</p>
                                <p className="text-sm text-gray-500 mt-1 leading-relaxed">
                                    { __( 'A new sending domain has zero reputation. Blasting thousands of emails on day one triggers spam filters at Gmail, Outlook, and Apple Mail. Warmup ramps volume gradually so inbox providers learn to trust your domain.', 'snel-newsletter' ) }
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3">
                            <div className="w-7 h-7 rounded-lg flex items-center justify-center bg-emerald-50 shrink-0 mt-0.5">
                                <TrendingUp size={ 13 } className="text-emerald-500" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-gray-800">{ __( 'What warmup does', 'snel-newsletter' ) }</p>
                                <p className="text-sm text-gray-500 mt-1 leading-relaxed">
                                    { __( 'Each campaign respects a daily send cap. Emails beyond the cap are queued for the next day. A minimum 2-day cooldown between sends to the same subscriber is also enforced.', 'snel-newsletter' ) }
                                </p>
                            </div>
                        </div>
                    </div>

                    { /* Ramp schedule */ }
                    <div className="bg-gray-50 rounded-lg border border-gray-200 overflow-hidden">
                        <p className="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            { __( 'Send ramp', 'snel-newsletter' ) }
                        </p>
                        <div className="divide-y divide-gray-100">
                            { RAMP.map( ( row, i ) => (
                                <div key={ i } className="flex items-center justify-between px-4 py-2.5">
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

                </div>

                { /* Footer CTA */ }
                <div className="px-6 py-4 border-t border-gray-100 shrink-0">
                    { active ? (
                        <div className="flex items-center gap-3">
                            <div className="flex-1 bg-orange-50 border border-orange-200 rounded-lg px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Flame size={ 14 } className="text-orange-500" />
                                    <p className="text-sm font-medium text-orange-800">{ __( 'Warmup is active', 'snel-newsletter' ) }</p>
                                </div>
                                <p className="text-xs text-orange-600 mt-1">{ __( 'Daily send limits are being enforced.', 'snel-newsletter' ) }</p>
                            </div>
                            <button
                                type="button"
                                onClick={ onDisable }
                                className="px-4 py-2.5 text-sm font-medium text-red-600 border border-red-200 bg-red-50 hover:bg-red-100 rounded-lg transition-colors shrink-0"
                            >
                                { __( 'Disable', 'snel-newsletter' ) }
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={ onEnable }
                            className="w-full flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold text-white rounded-lg transition-all warmup-active"
                        >
                            <Flame size={ 15 } />
                            { __( 'Enable Warmup', 'snel-newsletter' ) }
                            <ChevronRight size={ 14 } />
                        </button>
                    ) }
                </div>

            </div>
            </div>
        </>
    );
}

export default function WarmupButton( { active, onToggle } ) {
    const [ modalOpen, setModalOpen ] = useState( false );

    const handleEnable = () => {
        onToggle( true );
        setModalOpen( false );
    };

    const handleDisable = () => {
        onToggle( false );
        setModalOpen( false );
    };

    return (
        <>
            { modalOpen && (
                <WarmupModal
                    active={ active }
                    onEnable={ handleEnable }
                    onDisable={ handleDisable }
                    onClose={ () => setModalOpen( false ) }
                />
            ) }

            <button
                type="button"
                onClick={ () => setModalOpen( true ) }
                className={ `inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium rounded-lg border transition-all ${ active ? 'border-orange-200 bg-white hover:border-orange-300' : 'text-gray-600 border-gray-300 bg-white hover:border-orange-300 hover:text-orange-600' }` }
            >
                { active
                    ? <span className="warmup-flame-icon" />
                    : <Flame size={ 14 } className="text-gray-400 shrink-0" />
                }
                { active
                    ? <span className="warmup-flame-text">{ __( 'Warmup on', 'snel-newsletter' ) }</span>
                    : __( 'Warmup', 'snel-newsletter' )
                }
            </button>
        </>
    );
}
