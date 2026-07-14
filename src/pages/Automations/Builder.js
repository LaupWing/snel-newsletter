import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
    ArrowLeft, Zap, Mail, Clock, Split, Tag as TagIcon,
    Plus, Trash2, Loader2, Play, Pause, Check,
} from 'lucide-react';
import Select from '../../components/Select';
import GradientButton from '../../components/GradientButton';
import NodeInspector from './NodeInspector';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

const STEP_META = {
    email:     { label: __( 'Send email', 'snel-newsletter' ), icon: Mail,    color: 'text-blue-600',    bg: 'bg-blue-50',    border: 'bg-blue-500',    handle: 'bg-blue-500' },
    wait:      { label: __( 'Wait', 'snel-newsletter' ),       icon: Clock,   color: 'text-cyan-600',    bg: 'bg-cyan-50',    border: 'bg-cyan-500',    handle: 'bg-cyan-500' },
    condition: { label: __( 'If / else', 'snel-newsletter' ),  icon: Split,   color: 'text-amber-600',   bg: 'bg-amber-50',   border: 'bg-amber-500',   handle: 'bg-amber-500' },
    label:     { label: __( 'Set label', 'snel-newsletter' ),  icon: TagIcon, color: 'text-emerald-600', bg: 'bg-emerald-50', border: 'bg-emerald-500', handle: 'bg-emerald-500' },
};

const NEW_STEP = {
    email:     () => ( { type: 'email', campaign_id: 0 } ),
    wait:      () => ( { type: 'wait', days: 1, hours: 0 } ),
    condition: () => ( { type: 'condition', mode: 'opened', threshold: 50, yes: [], no: [] } ),
    label:     () => ( { type: 'label', tag: '' } ),
};

/** Vertical connector line — blue edges like the workflow canvas, green/red inside branches. */
const Line = ( { tall, color = 'bg-blue-400' } ) => <div className={ `w-0.5 ${ color } ${ tall ? 'h-5' : 'h-3.5' }` } />;

/** Connection-handle dot on a node edge. */
const NodeHandle = ( { position, color } ) => (
    <span className={ `absolute ${ position === 'top' ? '-top-[5px]' : '-bottom-[5px]' } left-1/2 -translate-x-1/2 w-2.5 h-2.5 rounded-full border-2 border-white shadow-sm z-10 ${ color }` } />
);

/** "+" insert button with a step-type popover. */
function AddButton( { onAdd, allowCondition } ) {
    const [ open, setOpen ] = useState( false );
    const ref = useRef();

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( ref.current && ! ref.current.contains( e.target ) ) setOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    const types = [ 'email', 'wait', ...( allowCondition ? [ 'condition' ] : [] ), 'label' ];

    return (
        <div className="relative" ref={ ref }>
            <button
                type="button"
                onClick={ () => setOpen( ( o ) => ! o ) }
                className="w-6 h-6 flex items-center justify-center rounded-full bg-white border border-gray-300 text-gray-400 hover:text-blue-600 hover:border-blue-400 shadow-sm transition-all hover:scale-110"
                title={ __( 'Add step', 'snel-newsletter' ) }
            >
                <Plus size={ 13 } />
            </button>
            { open && (
                <div className="absolute top-7 left-1/2 -translate-x-1/2 z-20 w-44 bg-white border border-gray-200 rounded-lg shadow-xl p-1">
                    { types.map( ( t ) => {
                        const meta = STEP_META[ t ];
                        const Icon = meta.icon;
                        return (
                            <button
                                key={ t }
                                type="button"
                                onClick={ () => { setOpen( false ); onAdd( NEW_STEP[ t ]() ); } }
                                className="flex items-center gap-2.5 w-full px-2.5 py-2 text-xs text-gray-700 hover:bg-gray-50 rounded-md transition-colors"
                            >
                                <span className={ `w-6 h-6 flex items-center justify-center rounded-md ${ meta.bg }` }>
                                    <Icon size={ 12 } className={ meta.color } />
                                </span>
                                { meta.label }
                            </button>
                        );
                    } ) }
                </div>
            ) }
        </div>
    );
}

/** One step card with inline config. Double-click opens the node inspector. */
function StepCard( { step, onChange, onRemove, campaigns, tags, emailStats, narrow, path, onInspect } ) {
    const meta = STEP_META[ step.type ];
    const Icon = meta.icon;
    const stats = step.type === 'email' && step.campaign_id ? emailStats?.[ step.campaign_id ] : null;

    return (
        <div className={ `group relative w-full ${ narrow ? 'max-w-[300px]' : 'max-w-[400px]' }` }>
            <NodeHandle position="top" color={ meta.handle } />
            <div
                className={ `rounded-xl p-[2px] shadow-sm hover:shadow-md transition-shadow cursor-pointer ${ meta.border }` }
                onDoubleClick={ () => onInspect?.( path ) }
                title={ __( 'Double-click to see who passed through', 'snel-newsletter' ) }
            >
                <div className="bg-white rounded-[10px] px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Icon size={ 14 } className={ meta.color } />
                        <span className={ `text-[10px] font-semibold uppercase tracking-wider ${ meta.color }` }>{ meta.label }</span>
                        <span className="flex-1" />
                        { stats && (
                            <span className="text-[11px] text-gray-400 tabular-nums whitespace-nowrap">
                                { stats.sent } { __( 'sent', 'snel-newsletter' ) } · { stats.opened } { __( 'opened', 'snel-newsletter' ) }
                            </span>
                        ) }
                        <button
                            type="button"
                            onClick={ onRemove }
                            className="p-1 -my-1 -mr-1 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-md opacity-0 group-hover:opacity-100 transition-all"
                            title={ __( 'Remove step', 'snel-newsletter' ) }
                        >
                            <Trash2 size={ 13 } />
                        </button>
                    </div>

                    { step.type === 'email' && (
                        <div className="mt-2">
                            <Select
                                value={ String( step.campaign_id || '' ) }
                                onChange={ ( v ) => onChange( { ...step, campaign_id: parseInt( v, 10 ) || 0 } ) }
                                options={ [
                                    { value: '', label: __( 'Choose a campaign…', 'snel-newsletter' ) },
                                    ...campaigns.map( ( c ) => ( { value: String( c.id ), label: `${ c.subject }${ c.status !== 'draft' ? ` (${ c.status })` : '' }` } ) ),
                                ] }
                                fullWidth
                            />
                        </div>
                    ) }

                    { step.type === 'wait' && (
                        <div className="flex items-center gap-2 mt-2 text-sm text-gray-700">
                            <input
                                type="number" min="0" max="365"
                                value={ step.days }
                                onChange={ ( e ) => onChange( { ...step, days: Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) } ) }
                                className="w-14 px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500"
                            />
                            <span className="text-xs text-gray-500">{ __( 'days', 'snel-newsletter' ) }</span>
                            <input
                                type="number" min="0" max="23"
                                value={ step.hours || 0 }
                                onChange={ ( e ) => onChange( { ...step, hours: Math.max( 0, parseInt( e.target.value, 10 ) || 0 ) } ) }
                                className="w-14 px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500"
                            />
                            <span className="text-xs text-gray-500">{ __( 'hours', 'snel-newsletter' ) }</span>
                        </div>
                    ) }

                    { step.type === 'condition' && (
                        <div className="flex items-center gap-2 mt-2 flex-wrap">
                            <Select
                                value={ step.mode || 'opened' }
                                onChange={ ( v ) => onChange( { ...step, mode: v } ) }
                                options={ [
                                    { value: 'opened', label: __( 'Opened the last email?', 'snel-newsletter' ) },
                                    { value: 'open_rate', label: __( 'Open rate above…', 'snel-newsletter' ) },
                                ] }
                            />
                            { ( step.mode || 'opened' ) === 'open_rate' && (
                                <div className="flex items-center gap-1.5">
                                    <input
                                        type="number" min="0" max="100"
                                        value={ step.threshold ?? 50 }
                                        onChange={ ( e ) => onChange( { ...step, threshold: Math.min( 100, Math.max( 0, parseFloat( e.target.value ) || 0 ) ) } ) }
                                        className="w-16 px-2 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500"
                                    />
                                    <span className="text-xs text-gray-500">%</span>
                                </div>
                            ) }
                        </div>
                    ) }

                    { step.type === 'label' && (
                        <div className="mt-2">
                            <input
                                type="text"
                                list="snel-automation-tags"
                                value={ step.tag }
                                onChange={ ( e ) => onChange( { ...step, tag: e.target.value.toLowerCase().replace( /[^a-z0-9-]/g, '-' ).replace( /-+/g, '-' ) } ) }
                                placeholder={ __( 'Tag name…', 'snel-newsletter' ) }
                                className="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500"
                            />
                            <datalist id="snel-automation-tags">
                                { tags.map( ( t ) => <option key={ t } value={ t } /> ) }
                            </datalist>
                        </div>
                    ) }
                </div>
            </div>
            <NodeHandle position="bottom" color={ meta.handle } />
        </div>
    );
}

/**
 * A vertical list of steps with insert points. Branch lists disallow conditions.
 *
 * `basePath` is the engine's JSON path prefix for this list: [] at the root, and
 * [conditionIndex, 'yes'|'no'] inside a branch. Each step's own path is basePath + its
 * index, which is exactly the position string the engine stores on a run — so the node
 * inspector can look a step up without any extra mapping.
 */
function StepList( { steps, onSteps, campaigns, tags, emailStats, allowCondition, narrow, lineColor, basePath = [], onInspect } ) {
    const insertAt = ( i, step ) => {
        const next = steps.slice();
        next.splice( i, 0, step );
        onSteps( next );
    };
    const updateAt = ( i, step ) => onSteps( steps.map( ( s, j ) => ( j === i ? step : s ) ) );
    const removeAt = ( i ) => onSteps( steps.filter( ( _, j ) => j !== i ) );

    return (
        <div className="flex flex-col items-center w-full">
            { steps.map( ( step, i ) => (
                <div key={ i } className="flex flex-col items-center w-full">
                    <Line color={ lineColor } />
                    <AddButton allowCondition={ allowCondition } onAdd={ ( s ) => insertAt( i, s ) } />
                    <Line color={ lineColor } />
                    { step.type === 'condition' ? (
                        <ConditionBlock
                            step={ step }
                            onChange={ ( s ) => updateAt( i, s ) }
                            onRemove={ () => removeAt( i ) }
                            campaigns={ campaigns }
                            tags={ tags }
                            emailStats={ emailStats }
                            path={ [ ...basePath, i ] }
                            onInspect={ onInspect }
                        />
                    ) : (
                        <StepCard
                            step={ step }
                            onChange={ ( s ) => updateAt( i, s ) }
                            onRemove={ () => removeAt( i ) }
                            campaigns={ campaigns }
                            tags={ tags }
                            emailStats={ emailStats }
                            narrow={ narrow }
                            path={ [ ...basePath, i ] }
                            onInspect={ onInspect }
                        />
                    ) }
                </div>
            ) ) }
            <Line color={ lineColor } />
            <AddButton allowCondition={ allowCondition } onAdd={ ( s ) => insertAt( steps.length, s ) } />
        </div>
    );
}

/** Condition card + its yes/no branch columns with fork/merge connectors. */
function ConditionBlock( { step, onChange, onRemove, campaigns, tags, emailStats, path, onInspect } ) {
    const isRate = ( step.mode || 'opened' ) === 'open_rate';
    return (
        <div className="w-full flex flex-col items-center">
            <StepCard
                step={ step }
                onChange={ onChange }
                onRemove={ onRemove }
                campaigns={ campaigns }
                tags={ tags }
                emailStats={ emailStats }
                path={ path }
                onInspect={ onInspect }
            />

            {/* fork ⊓ — yes edge green, no edge red.
                The columns below are `grid-cols-2 gap-6`, so each column's centre sits at
                25% − half-the-gap, not a flat 25%. Pinning the arcs to 25% leaves them 6px
                off the vertical line they're meant to meet. */}
            <div className="relative w-full max-w-[560px] h-7">
                <span className="absolute top-0 left-1/2 -translate-x-1/2 w-0.5 h-3 bg-blue-400" />
                <span className="absolute top-3 bottom-0 left-[calc(25%-7px)] right-1/2 border-2 border-b-0 border-r-0 border-emerald-400 rounded-tl-2xl" />
                <span className="absolute top-3 bottom-0 left-1/2 right-[calc(25%-7px)] border-2 border-b-0 border-l-0 border-red-400 rounded-tr-2xl" />
            </div>

            {/* Branches are rarely the same length, so each column's line grows to fill the
                taller one — otherwise the shorter branch stops mid-air and the merge below
                it reads as disconnected. */}
            <div className="grid grid-cols-2 gap-6 w-full max-w-[560px] items-stretch">
                <div className="flex flex-col items-center h-full">
                    <span className="px-3 py-0.5 text-[11px] font-semibold text-emerald-600 bg-white border border-emerald-200 rounded-full shadow-sm z-10">
                        ✓ { isRate ? __( 'Above', 'snel-newsletter' ) : __( 'Opened', 'snel-newsletter' ) }
                    </span>
                    <StepList
                        steps={ step.yes }
                        onSteps={ ( yes ) => onChange( { ...step, yes } ) }
                        campaigns={ campaigns }
                        tags={ tags }
                        emailStats={ emailStats }
                        allowCondition={ false }
                        narrow
                        lineColor="bg-emerald-400"
                        basePath={ [ ...path, 'yes' ] }
                        onInspect={ onInspect }
                    />
                    <span className="w-0.5 flex-1 min-h-[12px] bg-emerald-400" />
                </div>
                <div className="flex flex-col items-center h-full">
                    <span className="px-3 py-0.5 text-[11px] font-semibold text-red-500 bg-white border border-red-200 rounded-full shadow-sm z-10">
                        ✕ { isRate ? __( 'Below', 'snel-newsletter' ) : __( "Didn't open", 'snel-newsletter' ) }
                    </span>
                    <StepList
                        steps={ step.no }
                        onSteps={ ( no ) => onChange( { ...step, no } ) }
                        campaigns={ campaigns }
                        tags={ tags }
                        emailStats={ emailStats }
                        allowCondition={ false }
                        narrow
                        lineColor="bg-red-400"
                        basePath={ [ ...path, 'no' ] }
                        onInspect={ onInspect }
                    />
                    <span className="w-0.5 flex-1 min-h-[12px] bg-red-400" />
                </div>
            </div>

            {/* merge ∪ — same 25% − gap/4 alignment as the fork above. */}
            <div className="relative w-full max-w-[560px] h-7">
                <span className="absolute top-0 bottom-3 left-[calc(25%-7px)] right-1/2 border-2 border-t-0 border-r-0 border-emerald-400 rounded-bl-2xl" />
                <span className="absolute top-0 bottom-3 left-1/2 right-[calc(25%-7px)] border-2 border-t-0 border-l-0 border-red-400 rounded-br-2xl" />
                <span className="absolute bottom-0 left-1/2 -translate-x-1/2 w-0.5 h-3 bg-blue-400" />
            </div>
        </div>
    );
}

export default function Builder( { automationId, onClose } ) {
    const [ automation, setAutomation ] = useState( null );
    const [ campaigns, setCampaigns ]   = useState( [] );
    const [ tags, setTags ]             = useState( [] );
    const [ saving, setSaving ]         = useState( false );
    const [ dirty, setDirty ]           = useState( false );
    const [ savedFlash, setSavedFlash ] = useState( false );
    const [ inspect, setInspect ]       = useState( null ); // step path, or 'trigger'

    useEffect( () => {
        api( `/automations/${ automationId }` ).then( setAutomation );
        api( '/campaigns?per_page=100' ).then( ( d ) => setCampaigns( d.campaigns || [] ) );
        api( '/tags' ).then( ( d ) => setTags( ( d || [] ).map( ( t ) => t.tag ) ) );
    }, [ automationId ] );

    const patch = useCallback( ( fields ) => {
        setAutomation( ( a ) => ( { ...a, ...fields } ) );
        setDirty( true );
    }, [] );

    const save = async ( extra = {} ) => {
        setSaving( true );
        const payload = {
            name:         automation.name,
            trigger_type: automation.trigger_type,
            trigger_tag:  automation.trigger_tag,
            steps:        automation.steps,
            ...extra,
        };
        await api( `/automations/${ automationId }`, { method: 'PUT', body: JSON.stringify( payload ) } );
        const fresh = await api( `/automations/${ automationId }` );
        setAutomation( fresh );
        setSaving( false );
        setDirty( false );
        setSavedFlash( true );
        setTimeout( () => setSavedFlash( false ), 1500 );
    };

    const toggleActive = () => save( { status: automation.status === 'active' ? 'paused' : 'active' } );

    if ( ! automation ) {
        return (
            <div className="p-6">
                <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
                    <Loader2 size={ 20 } className="mx-auto animate-spin text-gray-400" />
                </div>
            </div>
        );
    }

    const isActive   = automation.status === 'active';
    const tagOptions = [
        { value: '', label: __( 'Choose a tag…', 'snel-newsletter' ) },
        ...tags.map( ( t ) => ( { value: t, label: t } ) ),
    ];

    return (
        <div className="p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-5">
                <div className="flex items-center gap-3">
                    <button type="button" onClick={ onClose } className="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title={ __( 'Back to automations', 'snel-newsletter' ) }>
                        <ArrowLeft size={ 16 } />
                    </button>
                    <div>
                        <input
                            type="text"
                            value={ automation.name }
                            onChange={ ( e ) => patch( { name: e.target.value } ) }
                            className="text-lg font-bold text-gray-900 bg-transparent border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none transition-colors"
                        />
                        <p className="text-xs text-gray-500 mt-0.5 tabular-nums">
                            { automation.enrolled } { __( 'enrolled', 'snel-newsletter' ) } · { automation.in_progress } { __( 'in progress', 'snel-newsletter' ) } · { automation.completed } { __( 'completed', 'snel-newsletter' ) }
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span className={ `inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full ${ isActive ? 'text-emerald-700 bg-emerald-50' : 'text-gray-500 bg-gray-100' }` }>
                        <span className={ `w-1.5 h-1.5 rounded-full ${ isActive ? 'bg-emerald-500' : 'bg-gray-400' }` } />
                        { isActive ? __( 'Active', 'snel-newsletter' ) : __( 'Paused', 'snel-newsletter' ) }
                    </span>
                    <button
                        type="button"
                        onClick={ () => save() }
                        disabled={ saving || ! dirty }
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 disabled:opacity-40 rounded-lg transition-colors"
                    >
                        { savedFlash ? <><Check size={ 13 } className="text-emerald-600" /> { __( 'Saved', 'snel-newsletter' ) }</> : saving ? __( 'Saving…', 'snel-newsletter' ) : __( 'Save', 'snel-newsletter' ) }
                    </button>
                    { isActive ? (
                        <button
                            type="button"
                            onClick={ toggleActive }
                            disabled={ saving }
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-lg transition-all disabled:opacity-40"
                        >
                            <Pause size={ 13 } /> { __( 'Pause', 'snel-newsletter' ) }
                        </button>
                    ) : (
                        <GradientButton>
                            <button
                                type="button"
                                onClick={ toggleActive }
                                disabled={ saving }
                                className="relative inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium text-gray-900 bg-white hover:bg-gray-50 rounded-md transition-colors disabled:opacity-40"
                            >
                                <Play size={ 13 } /> { __( 'Activate', 'snel-newsletter' ) }
                            </button>
                        </GradientButton>
                    ) }
                </div>
            </div>

            {/* Canvas */}
            <div className="border border-gray-200 rounded-2xl bg-gray-50 [background-image:radial-gradient(#d8d9dd_1px,transparent_1px)] [background-size:18px_18px] px-6 py-10 overflow-x-auto min-h-[calc(100vh-240px)]">
                <div className="flex flex-col items-center min-w-[560px]">

                    {/* Trigger */}
                    <div className="relative w-full max-w-[400px]">
                        <div
                            className="rounded-xl p-[2px] shadow-sm bg-gradient-to-b from-blue-500 to-violet-600 cursor-pointer"
                            onDoubleClick={ () => setInspect( 'trigger' ) }
                            title={ __( 'Double-click to see who entered', 'snel-newsletter' ) }
                        >
                            <div className="bg-white rounded-[10px] px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Zap size={ 14 } className="text-violet-600" />
                                    <span className="text-[10px] font-semibold uppercase tracking-wider text-violet-600">{ __( 'Trigger', 'snel-newsletter' ) }</span>
                                    <span className="flex-1" />
                                    <span className="text-[11px] text-gray-400 tabular-nums shrink-0">{ automation.enrolled } { __( 'entered', 'snel-newsletter' ) }</span>
                                </div>
                                <div className="flex items-center gap-2 mt-2 flex-wrap">
                                    <Select
                                        value={ automation.trigger_type }
                                        onChange={ ( v ) => patch( { trigger_type: v } ) }
                                        options={ [
                                            { value: 'tag', label: __( 'When tag is added', 'snel-newsletter' ) },
                                            { value: 'manual', label: __( 'Manual enroll only', 'snel-newsletter' ) },
                                        ] }
                                    />
                                    { automation.trigger_type === 'tag' && (
                                        <Select
                                            value={ automation.trigger_tag || '' }
                                            onChange={ ( v ) => patch( { trigger_tag: v } ) }
                                            options={ tagOptions }
                                        />
                                    ) }
                                </div>
                            </div>
                        </div>
                        <NodeHandle position="bottom" color="bg-violet-600" />
                    </div>

                    {/* Steps */}
                    <StepList
                        steps={ automation.steps }
                        onSteps={ ( steps ) => patch( { steps } ) }
                        campaigns={ campaigns }
                        tags={ tags }
                        emailStats={ automation.email_stats || {} }
                        allowCondition
                        basePath={ [] }
                        onInspect={ setInspect }
                    />

                    <Line />
                    <span className="px-4 py-1 text-xs text-gray-400 bg-white border border-dashed border-gray-300 rounded-full">
                        { __( 'End of automation', 'snel-newsletter' ) }
                    </span>
                </div>
            </div>

            <p className="text-xs text-gray-400 mt-3">
                { __( 'Emails are regular campaigns — create them as drafts under Campaigns, then pick them in an email step. Double-click any node to see who passed through it.', 'snel-newsletter' ) }
            </p>

            { inspect && (
                <NodeInspector
                    automationId={ automation.id }
                    path={ inspect }
                    onClose={ () => setInspect( null ) }
                />
            ) }
        </div>
    );
}
