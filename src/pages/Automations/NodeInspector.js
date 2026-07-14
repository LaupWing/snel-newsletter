import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Loader2, MailOpen, MousePointerClick, Clock, Tag as TagIcon, Check, Minus } from 'lucide-react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

const TITLES = {
    trigger:   __( 'Entered the automation', 'snel-newsletter' ),
    email:     __( 'Sent this email', 'snel-newsletter' ),
    wait:      __( 'Reached this wait', 'snel-newsletter' ),
    label:     __( 'Got this label', 'snel-newsletter' ),
    condition: __( 'Evaluated at this branch', 'snel-newsletter' ),
};

function when( value ) {
    if ( ! value ) {
        return '—';
    }
    return new Date( value.replace( ' ', 'T' ) ).toLocaleString();
}

/** Small coloured chip. */
function Pill( { tone, icon: Icon, children } ) {
    const tones = {
        green: 'text-emerald-700 bg-emerald-50 border-emerald-200',
        red:   'text-red-600 bg-red-50 border-red-200',
        gray:  'text-gray-500 bg-gray-50 border-gray-200',
        blue:  'text-blue-700 bg-blue-50 border-blue-200',
    };
    return (
        <span className={ `inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium border rounded-full ${ tones[ tone ] }` }>
            { Icon && <Icon size={ 11 } /> }
            { children }
        </span>
    );
}

/**
 * Per-node detail: what we show in the right-hand column depends on the step type.
 * Email nodes get delivery + open + click; conditions get the branch taken; waits get
 * the resume time; labels and the trigger just get the timestamp.
 */
function Detail( { type, row } ) {
    if ( 'email' === type ) {
        return (
            <div className="flex items-center gap-1.5 flex-wrap justify-end">
                { row.send_status === 'sent'
                    ? <Pill tone="gray" icon={ Check }>{ __( 'Sent', 'snel-newsletter' ) }</Pill>
                    : <Pill tone="gray" icon={ Minus }>{ row.send_status }</Pill> }
                { row.opened_at
                    ? <Pill tone="green" icon={ MailOpen }>{ __( 'Opened', 'snel-newsletter' ) }</Pill>
                    : <Pill tone="red" icon={ Minus }>{ __( 'Not opened', 'snel-newsletter' ) }</Pill> }
                { row.clicked && <Pill tone="blue" icon={ MousePointerClick }>{ __( 'Clicked', 'snel-newsletter' ) }</Pill> }
            </div>
        );
    }

    if ( 'condition' === type ) {
        return row.detail === 'yes'
            ? <Pill tone="green" icon={ Check }>{ __( 'Yes branch', 'snel-newsletter' ) }</Pill>
            : <Pill tone="red" icon={ Minus }>{ __( 'No branch', 'snel-newsletter' ) }</Pill>;
    }

    if ( 'wait' === type ) {
        return <Pill tone="gray" icon={ Clock }>{ __( 'Resumes', 'snel-newsletter' ) } { when( row.detail ) }</Pill>;
    }

    if ( 'label' === type ) {
        return <Pill tone="green" icon={ TagIcon }>{ row.detail }</Pill>;
    }

    if ( 'trigger' === type ) {
        return <Pill tone="gray">{ row.run_status }</Pill>;
    }

    return null;
}

/**
 * Double-clicking a node opens this: the subscribers who passed through that step.
 *
 * `path` is the step's JSON path as the engine stores it ("[2]", "[2,\"yes\",0]"),
 * or the string "trigger" for the enrolment node.
 */
export default function NodeInspector( { automationId, path, onClose } ) {
    const [ data, setData ]       = useState( null );
    const [ loading, setLoading ] = useState( true );

    useEffect( () => {
        const query = 'trigger' === path ? 'trigger' : JSON.stringify( path );

        setLoading( true );
        fetch( `${ API_URL }automations/${ automationId }/step?path=${ encodeURIComponent( query ) }`, {
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        } )
            .then( ( r ) => r.json() )
            .then( ( r ) => setData( r ) )
            .finally( () => setLoading( false ) );
    }, [ automationId, path ] );

    const type = 'trigger' === path ? 'trigger' : ( data?.type || '' );
    const rows = data?.subscribers || [];

    return (
        <div
            className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40 p-6"
            onClick={ onClose }
        >
            <div
                className="w-full max-w-[640px] max-h-[80vh] flex flex-col bg-white rounded-2xl shadow-xl"
                onClick={ ( e ) => e.stopPropagation() }
            >
                <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                    <h2 className="text-sm font-semibold text-gray-900">
                        { TITLES[ type ] || __( 'Step detail', 'snel-newsletter' ) }
                    </h2>
                    <span className="text-xs text-gray-400 tabular-nums">
                        { rows.length } { __( 'subscribers', 'snel-newsletter' ) }
                    </span>
                    <span className="flex-1" />
                    <button
                        type="button"
                        onClick={ onClose }
                        className="p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
                    >
                        <X size={ 16 } />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto">
                    { loading && (
                        <div className="flex items-center justify-center py-16 text-gray-400">
                            <Loader2 size={ 18 } className="animate-spin" />
                        </div>
                    ) }

                    { ! loading && ! rows.length && (
                        <p className="px-5 py-16 text-sm text-center text-gray-400">
                            { __( 'Nobody has reached this step yet.', 'snel-newsletter' ) }
                        </p>
                    ) }

                    { ! loading && rows.map( ( row ) => (
                        <div
                            key={ row.id }
                            className="flex items-center gap-3 px-5 py-2.5 border-b border-gray-50 last:border-0 hover:bg-gray-50"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="text-sm text-gray-900 truncate">
                                    { row.name || row.email }
                                </p>
                                { row.name && <p className="text-xs text-gray-400 truncate">{ row.email }</p> }
                            </div>
                            <span className="text-xs text-gray-400 tabular-nums whitespace-nowrap shrink-0">
                                { when( row.at ) }
                            </span>
                            <div className="shrink-0">
                                <Detail type={ type } row={ row } />
                            </div>
                        </div>
                    ) ) }
                </div>
            </div>
        </div>
    );
}
