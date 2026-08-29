import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Upload, X, ChevronDown, ChevronUp, FileUp, ArrowRight, Sparkles, Loader2, Check, AlertCircle, CheckCircle, XCircle, MinusCircle } from 'lucide-react';
import Select from '../../components/Select';
import TagBadge from '../../components/TagBadge';
import TagPicker from '../../components/TagPicker';
import type { ChangeEvent, DragEvent, MutableRefObject } from 'react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE = window.snelNewsletter?.nonce as string;

function api( path: string, opts: RequestInit = {} ): Promise< any > {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

function isValidEmail( email: string ) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email );
}

/**
 * Parse a CSV string into an array of objects.
 */
function parseCSV( text: string ): { columns: string[]; rows: Record< string, string >[] } {
    const lines = text.split( /\r?\n/ ).filter( ( l ) => l.trim() );
    if ( lines.length < 2 ) return { columns: [], rows: [] };

    // Parse header — handle quoted values.
    const parseLine = ( line: string ) => {
        const result: string[] = [];
        let current = '';
        let inQuotes = false;
        for ( let i = 0; i < line.length; i++ ) {
            const ch = line[ i ];
            if ( ch === '"' ) {
                inQuotes = ! inQuotes;
            } else if ( ch === ',' && ! inQuotes ) {
                result.push( current.trim() );
                current = '';
            } else {
                current += ch;
            }
        }
        result.push( current.trim() );
        return result;
    };

    const columns = parseLine( lines[ 0 ] );
    const rows: Record< string, string >[] = [];

    for ( let i = 1; i < lines.length; i++ ) {
        const values = parseLine( lines[ i ] );
        const row: Record< string, string > = {};
        columns.forEach( ( col, j ) => {
            row[ col ] = values[ j ] || '';
        } );
        rows.push( row );
    }

    return { columns, rows };
}

type Props = {
    onClose: () => void;
    allTags: string[];
};

type PreviewRow = {
    email: string;
    name: string;
    tags: string[];
    source: string;
    valid: boolean;
    duplicate: boolean;
    aiGenerated?: boolean;
};

export default function ImportCSVModal( { onClose, allTags }: Props ) {
    const [ step, setStep ] = useState( 'upload' );
    const [ fileName, setFileName ] = useState( '' );
    const [ csvColumns, setCsvColumns ] = useState< string[] >( [] );
    const [ csvRows, setCsvRows ] = useState< Record< string, string >[] >( [] );
    const [ mapping, setMapping ] = useState( { email: '', name: '', source: '' } );
    const [ importTags, setImportTags ] = useState< string[] >( [] );
    const [ importInactive, setImportInactive ] = useState( false );
    const [ aiExtract, setAiExtract ] = useState( false );
    const [ aiLoading, setAiLoading ] = useState( false );
    const [ previewRows, setPreviewRows ] = useState< PreviewRow[] >( [] );
    const [ dragOver, setDragOver ] = useState( false );
    const [ importResult, setImportResult ] = useState< { imported: number; skipped: number } | null >( null );
    const [ showAllCsvRows, setShowAllCsvRows ] = useState( false );
    const [ showAllPreviewRows, setShowAllPreviewRows ] = useState( false );
    const [ existingEmails, setExistingEmails ] = useState< string[] >( [] );
    const [ importing, setImporting ] = useState( false );
    const fileInputRef = useRef() as MutableRefObject< HTMLInputElement >;

    // Load existing emails for duplicate detection.
    useEffect( () => {
        api( '/subscribers/emails' ).then( ( data ) => {
            setExistingEmails( ( data || [] ).map( ( e: any ) => e.toLowerCase() ) );
        } );
    }, [] );

    const processFile = ( file: File | undefined ) => {
        if ( ! file ) return;
        setFileName( file.name );

        const reader = new FileReader();
        reader.onload = ( e ) => {
            const { columns, rows } = parseCSV( e.target!.result as string );
            setCsvColumns( columns );
            setCsvRows( rows );

            // Auto-detect email and name columns.
            const emailCol = columns.find( ( c ) => /email/i.test( c ) ) || columns[ 0 ];
            const nameCol = columns.find( ( c ) => /name/i.test( c ) && ! /user/i.test( c ) ) || '';
            setMapping( { email: emailCol, name: nameCol, source: '' } );
            setStep( 'map' );
        };
        reader.readAsText( file );
    };

    const handleBrowse = () => {
        fileInputRef.current?.click();
    };

    const handleFileInput = ( e: ChangeEvent< HTMLInputElement > ) => {
        processFile( e.target.files![ 0 ] );
    };

    const handleDrop = ( e: DragEvent< HTMLDivElement > ) => {
        e.preventDefault();
        setDragOver( false );
        const file = e.dataTransfer.files[ 0 ];
        if ( file && file.name.endsWith( '.csv' ) ) {
            processFile( file );
        }
    };

    const handleMapping = () => {
        const preview = csvRows.map( ( row ) => {
            const email = mapping.email ? ( row[ mapping.email ] || '' ).trim() : '';
            const valid = isValidEmail( email );
            const duplicate = existingEmails.includes( email.toLowerCase() );
            return {
                email,
                name: mapping.name ? ( row[ mapping.name ] || '' ).trim() : '',
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
        // TODO: wire to OpenAI endpoint. For now, extract from email local part.
        setTimeout( () => {
            setPreviewRows( ( prev ) => prev.map( ( row ) => {
                if ( row.name ) return row;
                // Simple name extraction from email: john.doe@... → John Doe
                const local = row.email.split( '@' )[ 0 ] || '';
                const name = local
                    .replace( /[._-]/g, ' ' )
                    .replace( /\d+/g, '' )
                    .trim()
                    .split( ' ' )
                    .map( ( w ) => w.charAt( 0 ).toUpperCase() + w.slice( 1 ).toLowerCase() )
                    .join( ' ' )
                    .trim();
                return { ...row, name: name || '', aiGenerated: true };
            } ) );
            setAiExtract( true );
            setAiLoading( false );
        }, 500 );
    };

    const handleImport = () => {
        const toImport = previewRows.filter( ( r ) => r.valid && ! r.duplicate );
        if ( ! toImport.length ) return;

        setImporting( true );

        // Send in batches of 100.
        const batchSize = 100;
        const batches: PreviewRow[][] = [];
        for ( let i = 0; i < toImport.length; i += batchSize ) {
            batches.push( toImport.slice( i, i + batchSize ) );
        }

        let totalImported = 0;
        let totalSkipped = 0;

        const processBatch = ( index: number ) => {
            if ( index >= batches.length ) {
                setImportResult( { imported: totalImported, skipped: totalSkipped } );
                setImporting( false );
                setStep( 'done' );
                return;
            }

            api( '/subscribers/import', {
                method: 'POST',
                body: JSON.stringify( {
                    subscribers: batches[ index ].map( ( r ) => ( { email: r.email, name: r.name } ) ),
                    tags: importTags,
                    status: importInactive ? 'inactive' : 'active',
                } ),
            } ).then( ( data ) => {
                totalImported += data.imported || 0;
                totalSkipped += data.skipped || 0;
                processBatch( index + 1 );
            } );
        };

        processBatch( 0 );
    };

    const missingNames = previewRows.filter( ( r ) => ! r.name ).length;

    return (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50" onClick={ onClose }>
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col" onClick={ ( e ) => e.stopPropagation() }>
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

                <div className="p-6 overflow-y-auto flex-1">
                    {/* Step 1: Upload */}
                    { step === 'upload' && (
                        <div
                            className={ `border-2 border-dashed rounded-lg p-12 text-center transition-colors ${ dragOver ? 'border-blue-400 bg-blue-50/50' : 'border-gray-200 hover:border-gray-300' }` }
                            onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
                            onDragLeave={ () => setDragOver( false ) }
                            onDrop={ handleDrop }
                        >
                            <input
                                ref={ fileInputRef }
                                type="file"
                                accept=".csv"
                                onChange={ handleFileInput }
                                className="hidden"
                            />
                            <FileUp size={ 32 } className="mx-auto text-gray-300 mb-3" />
                            <p className="text-sm text-gray-600 mb-1">{ __( 'Drag & drop your CSV file here', 'snel-newsletter' ) }</p>
                            <p className="text-xs text-gray-400 mb-4">{ __( 'or', 'snel-newsletter' ) }</p>
                            <button
                                type="button"
                                onClick={ handleBrowse }
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
                                <TagPicker allTags={ allTags } selectedTags={ importTags } onChange={ setImportTags } />
                            </div>

                            {/* Import status */}
                            <label className="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={ importInactive }
                                    onChange={ ( e ) => setImportInactive( e.target.checked ) }
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600"
                                />
                                <span className="text-xs text-gray-700">{ __( 'Import as inactive', 'snel-newsletter' ) }</span>
                                <span className="text-xs text-gray-400">{ __( '(subscribers won\'t receive emails until activated)', 'snel-newsletter' ) }</span>
                            </label>

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
                                { importResult?.imported } { __( 'subscribers imported successfully.', 'snel-newsletter' ) }
                                { ( importResult?.skipped as number ) > 0 && (
                                    <span className="text-gray-400"> ({ importResult!.skipped } { __( 'skipped', 'snel-newsletter' ) })</span>
                                ) }
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
                                    disabled={ previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length === 0 || importing }
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    { importing ? (
                                        <><Loader2 size={ 14 } className="animate-spin" /> { __( 'Importing...', 'snel-newsletter' ) }</>
                                    ) : (
                                        <>{ __( 'Import', 'snel-newsletter' ) } { previewRows.filter( ( r ) => r.valid && ! r.duplicate ).length } { __( 'Subscribers', 'snel-newsletter' ) }</>
                                    ) }
                                </button>
                            ) }
                        </>
                    ) }
                </div>
            </div>
        </div>
    );
}
