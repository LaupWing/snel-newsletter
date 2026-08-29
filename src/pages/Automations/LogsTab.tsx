import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    Search, Loader2, Copy, Check, RefreshCw,
    Mail, Clock, Split, Tag as TagIcon, LogIn, LogOut, Flag,
} from 'lucide-react';

import type { ComponentType } from 'react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce as string;

const TYPE_ICON: Record< string, ComponentType< any > > = {
    email:     Mail,
    wait:      Clock,
    condition: Split,
    label:     TagIcon,
    enroll:    LogIn,
    exit:      LogOut,
    complete:  Flag,
};

const LEVEL: Record< string, { dot: string; text: string; row: string } > = {
    info:    { dot: 'bg-gray-300',    text: 'text-gray-700',   row: '' },
    warning: { dot: 'bg-amber-400',   text: 'text-amber-800',  row: 'bg-amber-50/50' },
    error:   { dot: 'bg-red-500',     text: 'text-red-700',    row: 'bg-red-50/50' },
};

function when( value: any ) {
    if ( ! value ) {
        return '—';
    }
    return new Date( value.replace( ' ', 'T' ) ).toLocaleString();
}

/** Plain-text form — this is what the Copy button puts on the clipboard. */
function asText( rows: any[], automation: any ) {
    const head = `# ${ automation.name } — automation log (${ rows.length } entries)\n`;
    const body = rows
        .map( ( r ) =>
            [
                r.created_at,
                ( r.level || 'info' ).toUpperCase().padEnd( 7 ),
                ( r.email || `subscriber #${ r.subscriber_id }` ).padEnd( 24 ),
                ( r.step_path ? `step ${ r.step_path }` : r.step_type ).padEnd( 16 ),
                r.message || r.step_type,
            ].join( '  ' )
        )
        .join( '\n' );

    return `${ head }\n${ body }\n`;
}

export default function LogsTab( { automation }: { automation: any } ) {
    const [ rows, setRows ]       = useState< any[] >( [] );
    const [ loading, setLoading ] = useState( true );
    const [ query, setQuery ]     = useState( '' );
    const [ level, setLevel ]     = useState( 'all' );
    const [ copied, setCopied ]   = useState( false );

    const load = () => {
        setLoading( true );
        fetch( `${ API_URL }/automations/${ automation.id }/logs`, {
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        } )
            .then( ( r ) => r.json() )
            .then( ( r ) => setRows( r.logs || [] ) )
            .finally( () => setLoading( false ) );
    };

    useEffect( load, [ automation.id ] );

    const q = query.trim().toLowerCase();

    const visible = rows.filter( ( r ) => {
        if ( level !== 'all' && ( r.level || 'info' ) !== level ) {
            return false;
        }
        if ( ! q ) {
            return true;
        }
        return (
            ( r.email || '' ).toLowerCase().includes( q ) ||
            ( r.name || '' ).toLowerCase().includes( q ) ||
            ( r.message || '' ).toLowerCase().includes( q ) ||
            ( r.step_type || '' ).toLowerCase().includes( q )
        );
    } );

    const counts = rows.reduce( ( acc, r ) => {
        const l = r.level || 'info';
        acc[ l ] = ( acc[ l ] || 0 ) + 1;
        return acc;
    }, {} as Record< string, number > );

    const copy = () => {
        const text = asText( visible, automation );
        const done = () => {
            setCopied( true );
            setTimeout( () => setCopied( false ), 1800 );
        };

        if ( navigator.clipboard?.writeText ) {
            navigator.clipboard.writeText( text ).then( done );
            return;
        }

        // http:// admin origins are not a secure context, so the async API is unavailable.
        const ta = document.createElement( 'textarea' );
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild( ta );
        ta.select();
        document.execCommand( 'copy' );
        document.body.removeChild( ta );
        done();
    };

    const levels = [
        { id: 'all',     label: __( 'All', 'snel-newsletter' ),      n: rows.length },
        { id: 'info',    label: __( 'Info', 'snel-newsletter' ),     n: counts.info || 0 },
        { id: 'warning', label: __( 'Warnings', 'snel-newsletter' ), n: counts.warning || 0 },
        { id: 'error',   label: __( 'Errors', 'snel-newsletter' ),   n: counts.error || 0 },
    ];

    if ( loading ) {
        return (
            <div className="flex items-center justify-center py-20 text-gray-400">
                <Loader2 size={ 20 } className="animate-spin" />
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center gap-3 mb-4 flex-wrap">
                <div className="relative flex-1 min-w-[220px]">
                    <Search size={ 14 } className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        value={ query }
                        onChange={ ( e ) => setQuery( e.target.value ) }
                        placeholder={ __( 'Search email, message or step…', 'snel-newsletter' ) }
                        className="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div className="flex items-center gap-1">
                    { levels.map( ( l ) => (
                        <button
                            key={ l.id }
                            type="button"
                            onClick={ () => setLevel( l.id ) }
                            className={ `px-2.5 py-1.5 text-xs font-medium rounded-md transition-colors ${ level === l.id
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:bg-gray-100'
                            }` }
                        >
                            { l.label } <span className="tabular-nums opacity-70">{ l.n }</span>
                        </button>
                    ) ) }
                </div>

                <button
                    type="button"
                    onClick={ load }
                    className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors"
                    title={ __( 'Refresh', 'snel-newsletter' ) }
                >
                    <RefreshCw size={ 13 } />
                </button>

                <button
                    type="button"
                    onClick={ copy }
                    disabled={ ! visible.length }
                    className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-40 rounded-lg transition-colors"
                >
                    { copied
                        ? <><Check size={ 13 } className="text-emerald-600" /> { __( 'Copied', 'snel-newsletter' ) }</>
                        : <><Copy size={ 13 } /> { __( 'Copy', 'snel-newsletter' ) }</> }
                </button>
            </div>

            <div className="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <div className="max-h-[calc(100vh-320px)] overflow-y-auto overscroll-contain">
                    { ! visible.length && (
                        <p className="px-4 py-16 text-sm text-center text-gray-400">
                            { rows.length
                                ? __( 'No log entries match.', 'snel-newsletter' )
                                : __( 'No activity yet. Logs appear as soon as the automation runs.', 'snel-newsletter' ) }
                        </p>
                    ) }

                    { visible.map( ( r ) => {
                        const lvl  = LEVEL[ r.level || 'info' ] || LEVEL.info;
                        const Icon = TYPE_ICON[ r.step_type ] || Flag;

                        return (
                            <div
                                key={ r.id }
                                className={ `flex items-start gap-3 px-4 py-2.5 border-b border-gray-50 last:border-0 hover:bg-gray-50 ${ lvl.row }` }
                            >
                                <span className={ `w-1.5 h-1.5 mt-2 rounded-full shrink-0 ${ lvl.dot }` } />

                                <span className="text-xs text-gray-400 tabular-nums whitespace-nowrap shrink-0 mt-0.5 w-[150px]">
                                    { when( r.created_at ) }
                                </span>

                                <Icon size={ 13 } className="text-gray-400 shrink-0 mt-0.5" />

                                <span className="text-xs text-gray-500 truncate shrink-0 w-[150px] mt-0.5">
                                    { r.email || `#${ r.subscriber_id }` }
                                </span>

                                <span className={ `text-sm flex-1 min-w-0 ${ lvl.text }` }>
                                    { r.message || r.step_type }
                                </span>

                                { r.step_path && (
                                    <code className="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded shrink-0 mt-0.5">
                                        { r.step_path }
                                    </code>
                                ) }
                            </div>
                        );
                    } ) }
                </div>
            </div>

            <p className="text-xs text-gray-400 mt-3">
                { __( 'Newest first, last 500 entries. Copy puts the filtered view on your clipboard as plain text.', 'snel-newsletter' ) }
            </p>
        </div>
    );
}
