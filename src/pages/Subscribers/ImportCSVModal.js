import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Upload, X, Tag, ChevronDown, ChevronUp, FileUp, ArrowRight, Sparkles, Loader2, Check, AlertCircle, CheckCircle, XCircle, MinusCircle } from 'lucide-react';
import Select from '../../components/Select';
import TagBadge from '../../components/TagBadge';

// Mock CSV data — simulates a parsed CSV file.
const MOCK_CSV_ROWS = [
    { 'Email Address': 'peter.wilson@gmail.com', 'Full Name': 'Peter Wilson', 'Signup Source': 'landing-page', 'Date': '2026-01-15' },
    { 'Email Address': 'anna.kowalski@hotmail.com', 'Full Name': '', 'Signup Source': 'instagram', 'Date': '2026-01-20' },
    { 'Email Address': 'j.vandenberg@outlook.com', 'Full Name': 'Jan van den Berg', 'Signup Source': 'landing-page', 'Date': '2026-02-03' },
    { 'Email Address': 'fitness.maria@gmail.com', 'Full Name': '', 'Signup Source': 'twitter', 'Date': '2026-02-10' },
    { 'Email Address': 'tom.hendriks@yahoo.com', 'Full Name': 'Tom Hendriks', 'Signup Source': 'referral', 'Date': '2026-02-14' },
    { 'Email Address': 'sarah.johnson123@gmail.com', 'Full Name': '', 'Signup Source': 'landing-page', 'Date': '2026-02-20' },
    { 'Email Address': 'john@example.com', 'Full Name': 'John Doe', 'Signup Source': 'referral', 'Date': '2026-03-01' },
    { 'Email Address': 'not-an-email', 'Full Name': 'Bad Entry', 'Signup Source': 'manual', 'Date': '2026-03-02' },
    { 'Email Address': 'david.martinez@gmail.com', 'Full Name': '', 'Signup Source': 'instagram', 'Date': '2026-03-05' },
    { 'Email Address': 'lisa.de.vries@outlook.com', 'Full Name': 'Lisa de Vries', 'Signup Source': 'landing-page', 'Date': '2026-03-08' },
];

// Existing subscriber emails — for duplicate detection in mock.
const EXISTING_EMAILS = [ 'john@example.com', 'jane@example.com', 'mike@example.com', 'sarah@example.com', 'alex@example.com', 'emma@example.com', 'chris@example.com', 'lisa@example.com' ];

const MOCK_AI_NAMES = {
    'peter.wilson@gmail.com': 'Peter Wilson',
    'anna.kowalski@hotmail.com': 'Anna Kowalski',
    'j.vandenberg@outlook.com': 'Jan van den Berg',
    'fitness.maria@gmail.com': 'Maria',
    'tom.hendriks@yahoo.com': 'Tom Hendriks',
    'sarah.johnson123@gmail.com': 'Sarah Johnson',
    'david.martinez@gmail.com': 'David Martinez',
    'lisa.de.vries@outlook.com': 'Lisa de Vries',
};

function isValidEmail( email ) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
}

export default function ImportCSVModal( { onClose, allTags } ) {
    const [ step, setStep ] = useState( 'upload' );
    const [ fileName, setFileName ] = useState( '' );
    const [ csvColumns, setCsvColumns ] = useState( [] );
    const [ csvRows, setCsvRows ] = useState( [] );
    const [ mapping, setMapping ] = useState( { email: '', name: '', source: '' } );
    const [ importTags, setImportTags ] = useState( [] );
    const [ tagDropdownOpen, setTagDropdownOpen ] = useState( false );
    const [ aiExtract, setAiExtract ] = useState( false );
    const [ aiLoading, setAiLoading ] = useState( false );
    const [ previewRows, setPreviewRows ] = useState( [] );
    const [ dragOver, setDragOver ] = useState( false );
    const [ importResult, setImportResult ] = useState( null );
    const [ showAllCsvRows, setShowAllCsvRows ] = useState( false );
    const [ showAllPreviewRows, setShowAllPreviewRows ] = useState( false );
    const importTagRef = useRef();

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( importTagRef.current && ! importTagRef.current.contains( e.target ) ) setTagDropdownOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    const handleFile = () => {
        setFileName( 'subscribers_export.csv' );
        const rows = MOCK_CSV_ROWS;
        const columns = Object.keys( rows[ 0 ] );
        setCsvColumns( columns );
        setCsvRows( rows );
        setMapping( { email: columns[ 0 ], name: columns[ 1 ], source: columns[ 2 ] } );
        setStep( 'map' );
    };

    const handleMapping = () => {
        const preview = csvRows.map( ( row ) => {
            const email = mapping.email ? row[ mapping.email ] : '';
            const valid = isValidEmail( email );
            const duplicate = EXISTING_EMAILS.includes( email.toLowerCase() );
            return {
                email,
                name: mapping.name ? row[ mapping.name ] : '',
                tags: importTags,
                source: mapping.source ? row[ mapping.source ] : '',
                valid,
                duplicate,
            };
        } );
        setPreviewRows( preview );
        setShowAllPreviewRows( false );
        setStep( 'preview' );
    };

    const handleAiExtract = () => {
        setAiLoading( true );
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
        const total = previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length;
        setImportResult( { total } );
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
                                <div className="flex items-center justify-between mb-2">
                                    <p className="text-xs font-medium text-gray-700">{ __( 'CSV Preview', 'snel-newsletter' ) }</p>
                                    <span className="text-xs text-gray-400">{ csvRows.length } { __( 'rows', 'snel-newsletter' ) }</span>
                                </div>
                                <div className="border border-gray-200 rounded-lg overflow-hidden">
                                    <div className="overflow-x-auto max-h-64 overflow-y-auto">
                                        <table className="w-full">
                                            <thead className="sticky top-0">
                                                <tr className="bg-gray-50">
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-400 w-8">#</th>
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
                                                { ( showAllCsvRows ? csvRows : csvRows.slice( 0, 3 ) ).map( ( row, i ) => (
                                                    <tr key={ i } className="border-t border-gray-100">
                                                        <td className="px-3 py-2 text-xs text-gray-300">{ i + 1 }</td>
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
                                        <button
                                            type="button"
                                            onClick={ () => setShowAllCsvRows( ! showAllCsvRows ) }
                                            className="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                        >
                                            { showAllCsvRows ? (
                                                <><ChevronUp size={ 12 } /> { __( 'Show less', 'snel-newsletter' ) }</>
                                            ) : (
                                                <><ChevronDown size={ 12 } /> { __( 'Show all', 'snel-newsletter' ) } { csvRows.length } { __( 'rows', 'snel-newsletter' ) }</>
                                            ) }
                                        </button>
                                    ) }
                                </div>
                            </div>
                        </div>
                    ) }

                    {/* Step 3: Preview */}
                    { step === 'preview' && (
                        <div className="space-y-4">
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

                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-1.5 text-xs">
                                    <CheckCircle size={ 12 } className="text-emerald-500" />
                                    <span className="text-gray-600"><strong className="text-gray-900">{ previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length }</strong> { __( 'will be imported', 'snel-newsletter' ) }</span>
                                </div>
                                { previewRows.some( ( r ) => r.duplicate ) && (
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <MinusCircle size={ 12 } className="text-amber-500" />
                                        <span className="text-gray-600"><strong className="text-gray-900">{ previewRows.filter( ( r ) => r.duplicate ).length }</strong> { __( 'duplicates skipped', 'snel-newsletter' ) }</span>
                                    </div>
                                ) }
                                { previewRows.some( ( r ) => ! r.valid ) && (
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <XCircle size={ 12 } className="text-red-500" />
                                        <span className="text-gray-600"><strong className="text-gray-900">{ previewRows.filter( ( r ) => ! r.valid ).length }</strong> { __( 'invalid emails', 'snel-newsletter' ) }</span>
                                    </div>
                                ) }
                            </div>

                            <div className="border border-gray-200 rounded-lg overflow-hidden">
                                <div className="overflow-x-auto max-h-72 overflow-y-auto">
                                    <table className="w-full">
                                        <thead className="sticky top-0">
                                            <tr className="bg-gray-50">
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-400 w-8">#</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Email', 'snel-newsletter' ) }</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Name', 'snel-newsletter' ) }</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Tags', 'snel-newsletter' ) }</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Source', 'snel-newsletter' ) }</th>
                                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500">{ __( 'Valid', 'snel-newsletter' ) }</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">{ __( 'Action', 'snel-newsletter' ) }</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            { ( showAllPreviewRows ? previewRows : previewRows.slice( 0, 4 ) ).map( ( row, i ) => (
                                                <tr key={ i } className={ `border-t border-gray-100 ${ ! row.valid ? 'bg-red-50/50' : row.duplicate ? 'bg-amber-50/30' : '' }` }>
                                                    <td className="px-3 py-2 text-xs text-gray-300">{ i + 1 }</td>
                                                    <td className="px-3 py-2 text-xs font-medium">
                                                        <span className={ ! row.valid ? 'text-red-600' : 'text-gray-900' }>{ row.email }</span>
                                                    </td>
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
                                                    <td className="px-3 py-2 text-xs text-gray-500">{ row.source || '—' }</td>
                                                    <td className="px-3 py-2 text-center">
                                                        { ! row.valid ? (
                                                            <XCircle size={ 14 } className="inline text-red-400" />
                                                        ) : (
                                                            <CheckCircle size={ 14 } className="inline text-emerald-400" />
                                                        ) }
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        { ! row.valid ? (
                                                            <span className="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-red-50 text-red-600">
                                                                { __( 'skip — invalid', 'snel-newsletter' ) }
                                                            </span>
                                                        ) : row.duplicate ? (
                                                            <span className="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-amber-50 text-amber-700">
                                                                { __( 'skip — duplicate', 'snel-newsletter' ) }
                                                            </span>
                                                        ) : (
                                                            <span className="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700">
                                                                { __( 'import', 'snel-newsletter' ) }
                                                            </span>
                                                        ) }
                                                    </td>
                                                </tr>
                                            ) ) }
                                        </tbody>
                                    </table>
                                </div>
                                { previewRows.length > 4 && (
                                    <button
                                        type="button"
                                        onClick={ () => setShowAllPreviewRows( ! showAllPreviewRows ) }
                                        className="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                                    >
                                        { showAllPreviewRows ? (
                                            <><ChevronUp size={ 12 } /> { __( 'Show less', 'snel-newsletter' ) }</>
                                        ) : (
                                            <><ChevronDown size={ 12 } /> { __( 'Show all', 'snel-newsletter' ) } { previewRows.length } { __( 'rows', 'snel-newsletter' ) }</>
                                        ) }
                                    </button>
                                ) }
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
                                    disabled={ previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length === 0 }
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    { __( 'Import', 'snel-newsletter' ) } { previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length } { __( 'Subscribers', 'snel-newsletter' ) }
                                </button>
                            ) }
                        </>
                    ) }
                </div>
            </div>
        </div>
    );
}
