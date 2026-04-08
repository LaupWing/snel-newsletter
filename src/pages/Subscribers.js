import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Users, Search, Plus, Upload, X, Tag, ChevronDown, ChevronLeft, ChevronRight, Trash2, MoreHorizontal, FileUp, ArrowRight, Sparkles, Loader2, Check, AlertCircle } from 'lucide-react';
import Select from '../components/Select';

// Mock data — will be replaced with REST API calls.
const MOCK_TAGS = [ 'fitness', 'nutrition', 'paid', 'free-trial', 'vip' ];

const MOCK_SUBSCRIBERS = [
    { id: 1, email: 'john@example.com', name: 'John Doe', status: 'active', tags: [ 'fitness', 'paid' ], created_at: '2026-03-15' },
    { id: 2, email: 'jane@example.com', name: 'Jane Smith', status: 'active', tags: [ 'fitness', 'nutrition' ], created_at: '2026-03-14' },
    { id: 3, email: 'mike@example.com', name: 'Mike Johnson', status: 'unsubscribed', tags: [ 'fitness' ], created_at: '2026-03-12' },
    { id: 4, email: 'sarah@example.com', name: 'Sarah Wilson', status: 'active', tags: [ 'nutrition', 'vip' ], created_at: '2026-03-10' },
    { id: 5, email: 'alex@example.com', name: 'Alex Brown', status: 'bounced', tags: [ 'free-trial' ], created_at: '2026-03-08' },
    { id: 6, email: 'emma@example.com', name: 'Emma Davis', status: 'active', tags: [ 'fitness', 'nutrition', 'paid' ], created_at: '2026-03-05' },
    { id: 7, email: 'chris@example.com', name: 'Chris Lee', status: 'active', tags: [ 'fitness' ], created_at: '2026-03-03' },
    { id: 8, email: 'lisa@example.com', name: '', status: 'active', tags: [], created_at: '2026-03-01' },
];

const STATUS_STYLES = {
    active: 'bg-emerald-50 text-emerald-700',
    unsubscribed: 'bg-gray-100 text-gray-600',
    bounced: 'bg-red-50 text-red-700',
};

function TagBadge( { tag, removable, onRemove } ) {
    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-purple-50 text-purple-700 rounded-full">
            { tag }
            { removable && (
                <button type="button" onClick={ onRemove } className="hover:text-purple-900 transition-colors">
                    <X size={ 10 } />
                </button>
            ) }
        </span>
    );
}

function AddSubscriberModal( { onClose, allTags } ) {
    const [ email, setEmail ] = useState( '' );
    const [ name, setName ] = useState( '' );
    const [ selectedTags, setSelectedTags ] = useState( [] );
    const [ tagDropdownOpen, setTagDropdownOpen ] = useState( false );
    const tagRef = useRef();

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( tagRef.current && ! tagRef.current.contains( e.target ) ) setTagDropdownOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    const toggleTag = ( tag ) => {
        setSelectedTags( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
    };

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
                        <div className="relative" ref={ tagRef }>
                            <button
                                type="button"
                                onClick={ () => setTagDropdownOpen( ! tagDropdownOpen ) }
                                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-500 hover:border-gray-400 transition-colors"
                            >
                                { selectedTags.length > 0 ? `${ selectedTags.length } tags selected` : __( 'Select tags...', 'snel-newsletter' ) }
                                <ChevronDown size={ 14 } className={ `transition-transform ${ tagDropdownOpen ? 'rotate-180' : '' }` } />
                            </button>
                            { tagDropdownOpen && (
                                <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-10 py-1">
                                    { allTags.map( ( tag ) => (
                                        <button
                                            key={ tag }
                                            type="button"
                                            onClick={ () => toggleTag( tag ) }
                                            className={ `w-full text-left px-3 py-1.5 text-sm transition-colors ${ selectedTags.includes( tag )
                                                ? 'bg-purple-50 text-purple-700'
                                                : 'text-gray-700 hover:bg-gray-50'
                                            }` }
                                        >
                                            { tag }
                                        </button>
                                    ) ) }
                                </div>
                            ) }
                        </div>
                        { selectedTags.length > 0 && (
                            <div className="flex flex-wrap gap-1 mt-2">
                                { selectedTags.map( ( tag ) => (
                                    <TagBadge key={ tag } tag={ tag } removable onRemove={ () => toggleTag( tag ) } />
                                ) ) }
                            </div>
                        ) }
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

// Mock CSV data — simulates a parsed CSV file.
const MOCK_CSV_ROWS = [
    { 'Email Address': 'peter.wilson@gmail.com', 'Full Name': 'Peter Wilson', 'Signup Source': 'landing-page' },
    { 'Email Address': 'anna.kowalski@hotmail.com', 'Full Name': '', 'Signup Source': 'instagram' },
    { 'Email Address': 'j.vandenberg@outlook.com', 'Full Name': 'Jan van den Berg', 'Signup Source': 'landing-page' },
    { 'Email Address': 'fitness.maria@gmail.com', 'Full Name': '', 'Signup Source': 'twitter' },
    { 'Email Address': 'tom.hendriks@yahoo.com', 'Full Name': 'Tom Hendriks', 'Signup Source': 'referral' },
    { 'Email Address': 'sarah.johnson123@gmail.com', 'Full Name': '', 'Signup Source': 'landing-page' },
];

const MOCK_AI_NAMES = {
    'peter.wilson@gmail.com': 'Peter Wilson',
    'anna.kowalski@hotmail.com': 'Anna Kowalski',
    'j.vandenberg@outlook.com': 'Jan van den Berg',
    'fitness.maria@gmail.com': 'Maria',
    'tom.hendriks@yahoo.com': 'Tom Hendriks',
    'sarah.johnson123@gmail.com': 'Sarah Johnson',
};

function ImportCSVModal( { onClose, allTags } ) {
    const [ step, setStep ] = useState( 'upload' ); // upload → map → preview → done
    const [ fileName, setFileName ] = useState( '' );
    const [ csvColumns, setCsvColumns ] = useState( [] );
    const [ csvRows, setCsvRows ] = useState( [] );
    const [ mapping, setMapping ] = useState( { email: '', name: '', tags: '' } );
    const [ importTags, setImportTags ] = useState( [] );
    const [ tagDropdownOpen, setTagDropdownOpen ] = useState( false );
    const [ aiExtract, setAiExtract ] = useState( false );
    const [ aiLoading, setAiLoading ] = useState( false );
    const [ previewRows, setPreviewRows ] = useState( [] );
    const [ dragOver, setDragOver ] = useState( false );
    const [ importResult, setImportResult ] = useState( null );
    const importTagRef = useRef();

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( importTagRef.current && ! importTagRef.current.contains( e.target ) ) setTagDropdownOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    const handleFile = () => {
        // Mock: simulate loading a CSV file
        setFileName( 'subscribers_export.csv' );
        const rows = MOCK_CSV_ROWS;
        const columns = Object.keys( rows[ 0 ] );
        setCsvColumns( columns );
        setCsvRows( rows );
        setMapping( { email: columns[ 0 ], name: columns[ 1 ], tags: '' } );
        setStep( 'map' );
    };

    const handleMapping = () => {
        // Build preview from mapping
        const preview = csvRows.map( ( row ) => ( {
            email: mapping.email ? row[ mapping.email ] : '',
            name: mapping.name ? row[ mapping.name ] : '',
            tags: importTags,
            source: mapping.tags ? row[ mapping.tags ] : '',
        } ) );
        setPreviewRows( preview );
        setStep( 'preview' );
    };

    const handleAiExtract = () => {
        setAiLoading( true );
        // Mock: simulate AI name extraction with a delay
        setTimeout( () => {
            setPreviewRows( ( prev ) => prev.map( ( row ) => ( {
                ...row,
                name: row.name || MOCK_AI_NAMES[ row.email ] || '',
                aiGenerated: ! row.name,
            } ) ) );
            setAiExtract( true );
            setAiLoading( false );
        }, 1500 );
    };

    const handleImport = () => {
        // Mock: simulate import
        const total = previewRows.length;
        const noName = previewRows.filter( ( r ) => ! r.name ).length;
        setImportResult( { total, noName } );
        setStep( 'done' );
    };

    const toggleImportTag = ( tag ) => {
        setImportTags( ( prev ) =>
            prev.includes( tag ) ? prev.filter( ( t ) => t !== tag ) : [ ...prev, tag ]
        );
    };

    const missingNames = previewRows.filter( ( r ) => ! r.name ).length;

    return (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50" onClick={ onClose }>
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl" onClick={ ( e ) => e.stopPropagation() }>
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                            <Upload size={ 16 } className="text-blue-600" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900">{ __( 'Import Subscribers', 'snel-newsletter' ) }</h3>
                            <p className="text-xs text-gray-400">
                                { step === 'upload' && __( 'Upload your CSV file', 'snel-newsletter' ) }
                                { step === 'map' && __( 'Map columns to fields', 'snel-newsletter' ) }
                                { step === 'preview' && __( 'Review before importing', 'snel-newsletter' ) }
                                { step === 'done' && __( 'Import complete', 'snel-newsletter' ) }
                            </p>
                        </div>
                    </div>
                    <button type="button" onClick={ onClose } className="text-gray-400 hover:text-gray-600 transition-colors">
                        <X size={ 16 } />
                    </button>
                </div>

                {/* Steps indicator */}
                <div className="flex items-center gap-2 px-6 py-3 border-b border-gray-50 bg-gray-50/50">
                    { [ 'upload', 'map', 'preview', 'done' ].map( ( s, i ) => {
                        const labels = [ 'Upload', 'Map Fields', 'Preview', 'Done' ];
                        const isActive = s === step;
                        const isPast = [ 'upload', 'map', 'preview', 'done' ].indexOf( step ) > i;
                        return (
                            <div key={ s } className="flex items-center gap-2">
                                { i > 0 && <div className={ `w-6 h-px ${ isPast ? 'bg-blue-400' : 'bg-gray-200' }` } /> }
                                <div className={ `flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium ${ isActive ? 'bg-blue-100 text-blue-700' : isPast ? 'text-blue-600' : 'text-gray-400' }` }>
                                    { isPast ? <Check size={ 10 } /> : <span>{ i + 1 }</span> }
                                    { labels[ i ] }
                                </div>
                            </div>
                        );
                    } ) }
                </div>

                <div className="p-6">
                    {/* Step 1: Upload */}
                    { step === 'upload' && (
                        <div
                            className={ `border-2 border-dashed rounded-lg p-12 text-center transition-colors ${ dragOver ? 'border-blue-400 bg-blue-50/50' : 'border-gray-200 hover:border-gray-300' }` }
                            onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
                            onDragLeave={ () => setDragOver( false ) }
                            onDrop={ ( e ) => { e.preventDefault(); setDragOver( false ); handleFile(); } }
                        >
                            <FileUp size={ 32 } className="mx-auto text-gray-300 mb-3" />
                            <p className="text-sm text-gray-600 mb-1">{ __( 'Drag & drop your CSV file here', 'snel-newsletter' ) }</p>
                            <p className="text-xs text-gray-400 mb-4">{ __( 'or', 'snel-newsletter' ) }</p>
                            <button
                                type="button"
                                onClick={ handleFile }
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                            >
                                <Upload size={ 14 } />
                                { __( 'Browse Files', 'snel-newsletter' ) }
                            </button>
                            <p className="text-xs text-gray-400 mt-4">{ __( 'Supports .csv files. First row should be column headers.', 'snel-newsletter' ) }</p>
                        </div>
                    ) }

                    {/* Step 2: Map columns */}
                    { step === 'map' && (
                        <div className="space-y-5">
                            <div className="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg">
                                <FileUp size={ 14 } className="text-gray-400" />
                                <span className="text-sm text-gray-700 font-medium">{ fileName }</span>
                                <span className="text-xs text-gray-400">— { csvRows.length } { __( 'rows found', 'snel-newsletter' ) }</span>
                            </div>

                            <div>
                                <p className="text-xs font-medium text-gray-700 mb-3">{ __( 'Map your CSV columns to subscriber fields', 'snel-newsletter' ) }</p>
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-gray-500 w-24 shrink-0">{ __( 'Email', 'snel-newsletter' ) } *</span>
                                        <ArrowRight size={ 12 } className="text-gray-300" />
                                        <Select
                                            value={ mapping.email }
                                            onChange={ ( v ) => setMapping( { ...mapping, email: v } ) }
                                            options={ [
                                                { value: '', label: __( 'Select column...', 'snel-newsletter' ) },
                                                ...csvColumns.map( ( c ) => ( { value: c, label: c } ) ),
                                            ] }
                                        />
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-gray-500 w-24 shrink-0">{ __( 'Name', 'snel-newsletter' ) }</span>
                                        <ArrowRight size={ 12 } className="text-gray-300" />
                                        <Select
                                            value={ mapping.name }
                                            onChange={ ( v ) => setMapping( { ...mapping, name: v } ) }
                                            options={ [
                                                { value: '', label: __( 'No name column', 'snel-newsletter' ) },
                                                ...csvColumns.map( ( c ) => ( { value: c, label: c } ) ),
                                            ] }
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Assign tags on import */}
                            <div>
                                <p className="text-xs font-medium text-gray-700 mb-2">{ __( 'Assign tags to all imported subscribers', 'snel-newsletter' ) }</p>
                                <div className="relative" ref={ importTagRef }>
                                    <button
                                        type="button"
                                        onClick={ () => setTagDropdownOpen( ! tagDropdownOpen ) }
                                        className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        <Tag size={ 12 } className="text-gray-400" />
                                        <span className="text-gray-700">{ importTags.length > 0 ? `${ importTags.length } tags` : __( 'Select tags...', 'snel-newsletter' ) }</span>
                                        <ChevronDown size={ 12 } className={ `text-gray-400 transition-transform ${ tagDropdownOpen ? 'rotate-180' : '' }` } />
                                    </button>
                                    { tagDropdownOpen && (
                                        <div className="absolute left-0 top-full mt-1 bg-white rounded-lg shadow-lg ring-1 ring-black/10 py-1 z-50 min-w-[160px]">
                                            { allTags.map( ( tag ) => (
                                                <button
                                                    key={ tag }
                                                    type="button"
                                                    onClick={ () => toggleImportTag( tag ) }
                                                    className={ `w-full text-left px-3 py-2 text-xs transition-colors ${ importTags.includes( tag )
                                                        ? 'bg-purple-50 text-purple-700 font-medium'
                                                        : 'text-gray-600 hover:bg-gray-50'
                                                    }` }
                                                >
                                                    { tag }
                                                </button>
                                            ) ) }
                                        </div>
                                    ) }
                                </div>
                                { importTags.length > 0 && (
                                    <div className="flex flex-wrap gap-1 mt-2">
                                        { importTags.map( ( tag ) => (
                                            <TagBadge key={ tag } tag={ tag } removable onRemove={ () => toggleImportTag( tag ) } />
                                        ) ) }
                                    </div>
                                ) }
                            </div>

                            {/* CSV preview table */}
                            <div>
                                <p className="text-xs font-medium text-gray-700 mb-2">{ __( 'CSV Preview', 'snel-newsletter' ) }</p>
                                <div className="border border-gray-200 rounded-lg overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="bg-gray-50">
                                                    { csvColumns.map( ( col ) => (
                                                        <th key={ col } className="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                                            { col }
                                                            { col === mapping.email && <span className="ml-1 text-blue-500">→ email</span> }
                                                            { col === mapping.name && <span className="ml-1 text-blue-500">→ name</span> }
                                                        </th>
                                                    ) ) }
                                                </tr>
                                            </thead>
                                            <tbody>
                                                { csvRows.slice( 0, 3 ).map( ( row, i ) => (
                                                    <tr key={ i } className="border-t border-gray-100">
                                                        { csvColumns.map( ( col ) => (
                                                            <td key={ col } className="px-3 py-2 text-xs text-gray-600">
                                                                { row[ col ] || <span className="text-gray-300">—</span> }
                                                            </td>
                                                        ) ) }
                                                    </tr>
                                                ) ) }
                                            </tbody>
                                        </table>
                                    </div>
                                    { csvRows.length > 3 && (
                                        <div className="px-3 py-1.5 bg-gray-50 border-t border-gray-100 text-xs text-gray-400">
                                            + { csvRows.length - 3 } { __( 'more rows', 'snel-newsletter' ) }
                                        </div>
                                    ) }
                                </div>
                            </div>
                        </div>
                    ) }

                    {/* Step 3: Preview */}
                    { step === 'preview' && (
                        <div className="space-y-4">
                            {/* AI name extraction banner */}
                            { missingNames > 0 && ! aiExtract && (
                                <div className="flex items-center justify-between px-4 py-3 bg-purple-50 border border-purple-100 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <Sparkles size={ 16 } className="text-purple-600" />
                                        <div>
                                            <p className="text-sm font-medium text-purple-900">
                                                { missingNames } { __( 'subscribers have no name', 'snel-newsletter' ) }
                                            </p>
                                            <p className="text-xs text-purple-600">
                                                { __( 'AI can extract probable names from email addresses', 'snel-newsletter' ) }
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={ handleAiExtract }
                                        disabled={ aiLoading }
                                        className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors disabled:opacity-50"
                                    >
                                        { aiLoading ? (
                                            <><Loader2 size={ 12 } className="animate-spin" /> { __( 'Extracting...', 'snel-newsletter' ) }</>
                                        ) : (
                                            <><Sparkles size={ 12 } /> { __( 'Extract Names with AI', 'snel-newsletter' ) }</>
                                        ) }
                                    </button>
                                </div>
                            ) }

                            { aiExtract && (
                                <div className="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-lg">
                                    <Check size={ 14 } className="text-emerald-600" />
                                    <p className="text-sm text-emerald-700">
                                        { __( 'AI extracted names for', 'snel-newsletter' ) } { previewRows.filter( ( r ) => r.aiGenerated ).length } { __( 'subscribers', 'snel-newsletter' ) }
                                    </p>
                                </div>
                            ) }

                            {/* Preview table */}
                            <div className="border border-gray-200 rounded-lg overflow-hidden">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Email', 'snel-newsletter' ) }</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Name', 'snel-newsletter' ) }</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Tags', 'snel-newsletter' ) }</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Status', 'snel-newsletter' ) }</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        { previewRows.map( ( row, i ) => (
                                            <tr key={ i } className="border-t border-gray-100">
                                                <td className="px-3 py-2 text-xs text-gray-900 font-medium">{ row.email }</td>
                                                <td className="px-3 py-2 text-xs">
                                                    { row.name ? (
                                                        <span className={ row.aiGenerated ? 'text-purple-700' : 'text-gray-600' }>
                                                            { row.name }
                                                            { row.aiGenerated && <Sparkles size={ 10 } className="inline ml-1 text-purple-400" /> }
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-300">—</span>
                                                    ) }
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex flex-wrap gap-1">
                                                        { row.tags.length > 0
                                                            ? row.tags.map( ( tag ) => <TagBadge key={ tag } tag={ tag } /> )
                                                            : <span className="text-xs text-gray-300">—</span>
                                                        }
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <span className="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700">
                                                        { __( 'new', 'snel-newsletter' ) }
                                                    </span>
                                                </td>
                                            </tr>
                                        ) ) }
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex items-center gap-2 text-xs text-gray-400">
                                <AlertCircle size={ 12 } />
                                { __( 'Duplicate emails will be skipped automatically.', 'snel-newsletter' ) }
                            </div>
                        </div>
                    ) }

                    {/* Step 4: Done */}
                    { step === 'done' && (
                        <div className="text-center py-6">
                            <div className="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Check size={ 24 } className="text-emerald-600" />
                            </div>
                            <h3 className="text-sm font-semibold text-gray-900 mb-1">{ __( 'Import Complete!', 'snel-newsletter' ) }</h3>
                            <p className="text-sm text-gray-500">
                                { importResult?.total } { __( 'subscribers imported successfully.', 'snel-newsletter' ) }
                            </p>
                        </div>
                    ) }
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-100">
                    { step === 'done' ? (
                        <button
                            type="button"
                            onClick={ onClose }
                            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                        >
                            { __( 'Close', 'snel-newsletter' ) }
                        </button>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={ step === 'map' ? () => setStep( 'upload' ) : step === 'preview' ? () => setStep( 'map' ) : onClose }
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                            >
                                { step === 'upload' ? __( 'Cancel', 'snel-newsletter' ) : __( 'Back', 'snel-newsletter' ) }
                            </button>
                            { step === 'map' && (
                                <button
                                    type="button"
                                    onClick={ handleMapping }
                                    disabled={ ! mapping.email }
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    { __( 'Preview Import', 'snel-newsletter' ) }
                                    <ArrowRight size={ 14 } />
                                </button>
                            ) }
                            { step === 'preview' && (
                                <button
                                    type="button"
                                    onClick={ handleImport }
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                >
                                    { __( 'Import', 'snel-newsletter' ) } { previewRows.length } { __( 'Subscribers', 'snel-newsletter' ) }
                                </button>
                            ) }
                        </>
                    ) }
                </div>
            </div>
        </div>
    );
}

function SubscriberRow( { subscriber, selected, onSelect } ) {
    const [ menuOpen, setMenuOpen ] = useState( false );

    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
            <td className="px-4 py-3">
                <input
                    type="checkbox"
                    checked={ selected }
                    onChange={ onSelect }
                    className="rounded border-gray-300"
                />
            </td>
            <td className="px-4 py-3">
                <p className="text-sm font-medium text-gray-900">{ subscriber.email }</p>
            </td>
            <td className="px-4 py-3">
                <p className="text-sm text-gray-600">{ subscriber.name || <span className="text-gray-300">—</span> }</p>
            </td>
            <td className="px-4 py-3">
                <span className={ `inline-block px-2 py-0.5 text-xs font-medium rounded-full ${ STATUS_STYLES[ subscriber.status ] }` }>
                    { subscriber.status }
                </span>
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap gap-1">
                    { subscriber.tags.length > 0
                        ? subscriber.tags.map( ( tag ) => <TagBadge key={ tag } tag={ tag } /> )
                        : <span className="text-xs text-gray-300">—</span>
                    }
                </div>
            </td>
            <td className="px-4 py-3">
                <p className="text-xs text-gray-400">{ subscriber.created_at }</p>
            </td>
            <td className="px-4 py-3">
                <div className="relative">
                    <button
                        type="button"
                        onClick={ () => setMenuOpen( ! menuOpen ) }
                        className="p-1 text-gray-400 hover:text-gray-600 rounded transition-colors"
                    >
                        <MoreHorizontal size={ 14 } />
                    </button>
                    { menuOpen && (
                        <>
                            <div className="fixed inset-0 z-10" onClick={ () => setMenuOpen( false ) } />
                            <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1 w-36">
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    { __( 'Edit', 'snel-newsletter' ) }
                                </button>
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    { __( 'Manage Tags', 'snel-newsletter' ) }
                                </button>
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    { __( 'Delete', 'snel-newsletter' ) }
                                </button>
                            </div>
                        </>
                    ) }
                </div>
            </td>
        </tr>
    );
}

export default function Subscribers() {
    const [ subscribers ] = useState( MOCK_SUBSCRIBERS );
    const [ allTags ] = useState( MOCK_TAGS );
    const [ search, setSearch ] = useState( '' );
    const [ filterTag, setFilterTag ] = useState( '' );
    const [ filterStatus, setFilterStatus ] = useState( '' );
    const [ selected, setSelected ] = useState( [] );
    const [ showAddModal, setShowAddModal ] = useState( false );
    const [ showImportModal, setShowImportModal ] = useState( false );
    const [ page ] = useState( 1 );
    const totalPages = 1;

    const filtered = subscribers.filter( ( s ) => {
        if ( search && ! s.email.toLowerCase().includes( search.toLowerCase() ) && ! s.name.toLowerCase().includes( search.toLowerCase() ) ) return false;
        if ( filterTag && ! s.tags.includes( filterTag ) ) return false;
        if ( filterStatus && s.status !== filterStatus ) return false;
        return true;
    } );

    const allSelected = filtered.length > 0 && selected.length === filtered.length;

    const toggleAll = () => {
        setSelected( allSelected ? [] : filtered.map( ( s ) => s.id ) );
    };

    const toggleOne = ( id ) => {
        setSelected( ( prev ) => prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ] );
    };

    const counts = {
        total: subscribers.length,
        active: subscribers.filter( ( s ) => s.status === 'active' ).length,
        unsubscribed: subscribers.filter( ( s ) => s.status === 'unsubscribed' ).length,
        bounced: subscribers.filter( ( s ) => s.status === 'bounced' ).length,
    };

    return (
        <div className="p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Manage your subscribers', 'snel-newsletter' ) }</p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={ () => setShowImportModal( true ) }
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg transition-colors"
                    >
                        <Upload size={ 14 } />
                        { __( 'Import CSV', 'snel-newsletter' ) }
                    </button>
                    <button
                        type="button"
                        onClick={ () => setShowAddModal( true ) }
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                    >
                        <Plus size={ 14 } />
                        { __( 'Add Subscriber', 'snel-newsletter' ) }
                    </button>
                </div>
            </div>

            {/* Stats bar */}
            <div className="flex items-center gap-6 mb-6">
                <div className="flex items-center gap-2">
                    <Users size={ 14 } className="text-gray-400" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.total }</strong> { __( 'total', 'snel-newsletter' ) }
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-emerald-500" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.active }</strong> { __( 'active', 'snel-newsletter' ) }
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-gray-400" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.unsubscribed }</strong> { __( 'unsubscribed', 'snel-newsletter' ) }
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-red-500" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.bounced }</strong> { __( 'bounced', 'snel-newsletter' ) }
                    </span>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white border border-gray-200 rounded-lg">
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div className="flex items-center gap-3">
                        {/* Search */}
                        <div className="relative">
                            <Search size={ 14 } className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                value={ search }
                                onChange={ ( e ) => setSearch( e.target.value ) }
                                placeholder={ __( 'Search subscribers...', 'snel-newsletter' ) }
                                className="pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6] w-64"
                            />
                        </div>
                        {/* Tag filter */}
                        <Select
                            value={ filterTag }
                            onChange={ setFilterTag }
                            options={ [
                                { value: '', label: __( 'All tags', 'snel-newsletter' ) },
                                ...allTags.map( ( tag ) => ( { value: tag, label: tag } ) ),
                            ] }
                        />
                        {/* Status filter */}
                        <Select
                            value={ filterStatus }
                            onChange={ setFilterStatus }
                            options={ [
                                { value: '', label: __( 'All statuses', 'snel-newsletter' ) },
                                { value: 'active', label: __( 'Active', 'snel-newsletter' ) },
                                { value: 'unsubscribed', label: __( 'Unsubscribed', 'snel-newsletter' ) },
                                { value: 'bounced', label: __( 'Bounced', 'snel-newsletter' ) },
                            ] }
                        />
                    </div>
                    {/* Bulk actions */}
                    { selected.length > 0 && (
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-gray-500">{ selected.length } { __( 'selected', 'snel-newsletter' ) }</span>
                            <button type="button" className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                <Trash2 size={ 12 } />
                                { __( 'Delete', 'snel-newsletter' ) }
                            </button>
                            <button type="button" className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                <Tag size={ 12 } />
                                { __( 'Add Tag', 'snel-newsletter' ) }
                            </button>
                        </div>
                    ) }
                </div>

                {/* Table */}
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-gray-200 bg-gray-50/50">
                            <th className="px-4 py-2.5 text-left w-10">
                                <input
                                    type="checkbox"
                                    checked={ allSelected }
                                    onChange={ toggleAll }
                                    className="rounded border-gray-300"
                                />
                            </th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Email', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Name', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Status', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Tags', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Added', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        { filtered.length > 0 ? (
                            filtered.map( ( subscriber ) => (
                                <SubscriberRow
                                    key={ subscriber.id }
                                    subscriber={ subscriber }
                                    selected={ selected.includes( subscriber.id ) }
                                    onSelect={ () => toggleOne( subscriber.id ) }
                                />
                            ) )
                        ) : (
                            <tr>
                                <td colSpan="7" className="px-4 py-12 text-center">
                                    <Users size={ 32 } className="mx-auto text-gray-300 mb-3" />
                                    <p className="text-sm text-gray-500">{ __( 'No subscribers found', 'snel-newsletter' ) }</p>
                                </td>
                            </tr>
                        ) }
                    </tbody>
                </table>

                {/* Pagination */}
                { totalPages > 1 && (
                    <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                        <p className="text-xs text-gray-500">
                            { __( 'Showing', 'snel-newsletter' ) } { filtered.length } { __( 'of', 'snel-newsletter' ) } { subscribers.length }
                        </p>
                        <div className="flex items-center gap-1">
                            <button type="button" disabled={ page <= 1 } className="p-1.5 text-gray-400 hover:text-gray-600 rounded disabled:opacity-30 transition-colors">
                                <ChevronLeft size={ 14 } />
                            </button>
                            <span className="px-2 text-xs text-gray-500">{ page } / { totalPages }</span>
                            <button type="button" disabled={ page >= totalPages } className="p-1.5 text-gray-400 hover:text-gray-600 rounded disabled:opacity-30 transition-colors">
                                <ChevronRight size={ 14 } />
                            </button>
                        </div>
                    </div>
                ) }
            </div>

            {/* Add Subscriber Modal */}
            { showAddModal && <AddSubscriberModal onClose={ () => setShowAddModal( false ) } allTags={ allTags } /> }

            {/* Import CSV Modal */}
            { showImportModal && <ImportCSVModal onClose={ () => setShowImportModal( false ) } allTags={ allTags } /> }
        </div>
    );
}
