import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Search, Loader2, Mail, Clock, Split, Tag as TagIcon, Check, LogOut } from 'lucide-react';

import type { ComponentType } from 'react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce as string;

const STEP_ICON: Record< string, ComponentType< any > > = { email: Mail, wait: Clock, condition: Split, label: TagIcon };

function when( value: any ) {
    if ( ! value ) {
        return '—';
    }
    return new Date( value.replace( ' ', 'T' ) ).toLocaleString();
}

/**
 * Resolve a run's position path against the automation's steps.
 *
 * The path is the step the run executes NEXT: [i] at the root, [i,'yes'|'no',j] inside a
 * branch. A path that runs off the end of the list means there's nothing left to do.
 */
function stepAt( steps: any, path: any ) {
    if ( ! Array.isArray( path ) ) {
        return null;
    }
    if ( path.length === 1 ) {
        return steps?.[ path[ 0 ] ] || null;
    }
    if ( path.length === 3 ) {
        const branch = steps?.[ path[ 0 ] ]?.[ path[ 1 ] ];
        return Array.isArray( branch ) ? branch[ path[ 2 ] ] || null : null;
    }
    return null;
}

/** Human name for the step a run is sitting on. */
function describe( step: any, campaigns: any[] ) {
    if ( ! step ) {
        return { label: __( 'End of automation', 'snel-newsletter' ), icon: Check };
    }

    const Icon = STEP_ICON[ step.type ] || Check;

    if ( step.type === 'email' ) {
        const c = campaigns.find( ( x ) => x.id === step.campaign_id );
        return { label: c ? c.subject : __( 'Send email', 'snel-newsletter' ), icon: Icon };
    }
    if ( step.type === 'wait' ) {
        const d = step.days || 0;
        const h = step.hours || 0;
        const parts = [];
        if ( d ) {
            parts.push( `${ d }d` );
        }
        if ( h ) {
            parts.push( `${ h }h` );
        }
        return { label: `${ __( 'Wait', 'snel-newsletter' ) } ${ parts.join( ' ' ) || '0h' }`, icon: Icon };
    }
    if ( step.type === 'condition' ) {
        return { label: __( 'If / else', 'snel-newsletter' ), icon: Icon };
    }
    if ( step.type === 'label' ) {
        return { label: `${ __( 'Set label', 'snel-newsletter' ) }: ${ step.tag }`, icon: Icon };
    }

    return { label: step.type, icon: Icon };
}

const STATUS: Record< string, { label: string; cls: string } > = {
    active:    { label: __( 'In progress', 'snel-newsletter' ), cls: 'text-blue-700 bg-blue-50 border-blue-200' },
    waiting:   { label: __( 'Waiting', 'snel-newsletter' ),     cls: 'text-amber-700 bg-amber-50 border-amber-200' },
    completed: { label: __( 'Completed', 'snel-newsletter' ),   cls: 'text-emerald-700 bg-emerald-50 border-emerald-200' },
    exited:    { label: __( 'Left', 'snel-newsletter' ),        cls: 'text-gray-500 bg-gray-50 border-gray-200' },
};

/** Which branch a path sits in, if any — so a run inside yes/no reads correctly. */
function branchOf( path: any ) {
    return Array.isArray( path ) && path.length === 3 ? path[ 1 ] : null;
}

export default function SubscribersTab( { automation, campaigns }: { automation: any; campaigns: any[] } ) {
    const [ rows, setRows ]       = useState< any[] >( [] );
    const [ loading, setLoading ] = useState( true );
    const [ query, setQuery ]     = useState( '' );
    const [ filter, setFilter ]   = useState( 'all' );

    useEffect( () => {
        setLoading( true );
        fetch( `${ API_URL }/automations/${ automation.id }/subscribers`, {
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        } )
            .then( ( r ) => r.json() )
            .then( ( r ) => setRows( r.subscribers || [] ) )
            .finally( () => setLoading( false ) );
    }, [ automation.id ] );

    const q = query.trim().toLowerCase();

    const visible = rows.filter( ( r ) => {
        if ( filter !== 'all' && r.status !== filter ) {
            return false;
        }
        if ( ! q ) {
            return true;
        }
        return ( r.email || '' ).toLowerCase().includes( q ) || ( r.name || '' ).toLowerCase().includes( q );
    } );

    const counts = rows.reduce( ( acc, r ) => {
        acc[ r.status ] = ( acc[ r.status ] || 0 ) + 1;
        return acc;
    }, {} as Record< string, number > );

    const filters = [
        { id: 'all',       label: __( 'All', 'snel-newsletter' ),        n: rows.length },
        { id: 'active',    label: STATUS.active.label,                   n: counts.active || 0 },
        { id: 'waiting',   label: STATUS.waiting.label,                  n: counts.waiting || 0 },
        { id: 'completed', label: STATUS.completed.label,                n: counts.completed || 0 },
        { id: 'exited',    label: STATUS.exited.label,                   n: counts.exited || 0 },
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
                        placeholder={ __( 'Search name or email…', 'snel-newsletter' ) }
                        className="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                    />
                </div>

                <div className="flex items-center gap-1">
                    { filters.map( ( f ) => (
                        <button
                            key={ f.id }
                            type="button"
                            onClick={ () => setFilter( f.id ) }
                            className={ `px-2.5 py-1.5 text-xs font-medium rounded-md transition-colors ${ filter === f.id
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:bg-gray-100'
                            }` }
                        >
                            { f.label } <span className="tabular-nums opacity-70">{ f.n }</span>
                        </button>
                    ) ) }
                </div>
            </div>

            <div className="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <div className="max-h-[calc(100vh-320px)] overflow-y-auto overscroll-contain">
                    <table className="w-full text-sm bg-white">
                        <thead className="sticky top-0 bg-gray-50 border-b border-gray-200 z-10">
                            <tr className="text-left text-xs font-medium text-gray-500">
                                <th className="px-4 py-2.5">{ __( 'Subscriber', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5">{ __( 'Currently at', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5">{ __( 'Status', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 whitespace-nowrap">{ __( 'Next run', 'snel-newsletter' ) }</th>
                                <th className="px-4 py-2.5 whitespace-nowrap">{ __( 'Entered', 'snel-newsletter' ) }</th>
                            </tr>
                        </thead>
                        <tbody>
                            { ! visible.length && (
                                <tr>
                                    <td colSpan={ 5 } className="px-4 py-16 text-center text-gray-400">
                                        { rows.length
                                            ? __( 'No subscribers match.', 'snel-newsletter' )
                                            : __( 'Nobody is enrolled in this automation yet.', 'snel-newsletter' ) }
                                    </td>
                                </tr>
                            ) }

                            { visible.map( ( r ) => {
                                const done   = r.status === 'completed' || r.status === 'exited';
                                const step   = stepAt( automation.steps, r.position );
                                const at     = describe( step, campaigns );
                                const branch = branchOf( r.position );
                                const status = STATUS[ r.status ] || STATUS.exited;
                                const AtIcon = r.status === 'exited' ? LogOut : at.icon;

                                return (
                                    <tr key={ r.run_id } className="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                        <td className="px-4 py-2.5">
                                            <p className="text-gray-900 truncate max-w-[220px]">{ r.name || r.email }</p>
                                            { r.name && <p className="text-xs text-gray-400 truncate max-w-[220px]">{ r.email }</p> }
                                        </td>

                                        <td className="px-4 py-2.5">
                                            <span className="inline-flex items-center gap-1.5 text-gray-700">
                                                <AtIcon size={ 13 } className="text-gray-400 shrink-0" />
                                                <span className="truncate max-w-[240px]">
                                                    { r.status === 'exited'
                                                        ? __( 'Left the automation', 'snel-newsletter' )
                                                        : done
                                                            ? __( 'Finished', 'snel-newsletter' )
                                                            : at.label }
                                                </span>
                                                { ! done && branch && (
                                                    <span className={ `px-1.5 py-0.5 text-[10px] font-semibold rounded-full border ${ branch === 'yes'
                                                        ? 'text-emerald-600 bg-emerald-50 border-emerald-200'
                                                        : 'text-red-500 bg-red-50 border-red-200'
                                                    }` }>
                                                        { branch === 'yes' ? __( 'yes', 'snel-newsletter' ) : __( 'no', 'snel-newsletter' ) }
                                                    </span>
                                                ) }
                                            </span>
                                        </td>

                                        <td className="px-4 py-2.5">
                                            <span className={ `inline-flex px-2 py-0.5 text-[11px] font-medium border rounded-full whitespace-nowrap ${ status.cls }` }>
                                                { status.label }
                                            </span>
                                        </td>

                                        <td className="px-4 py-2.5 text-xs text-gray-500 tabular-nums whitespace-nowrap">
                                            { r.status === 'waiting' ? when( r.next_run_at ) : '—' }
                                        </td>

                                        <td className="px-4 py-2.5 text-xs text-gray-500 tabular-nums whitespace-nowrap">
                                            { when( r.enrolled_at ) }
                                        </td>
                                    </tr>
                                );
                            } ) }
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
