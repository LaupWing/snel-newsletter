import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Users, Search, Plus, Upload, Tag, ChevronLeft, ChevronRight, Trash2, Workflow, SlidersHorizontal } from 'lucide-react';
import Select from '../../components/Select';
import SubscriberRow from './SubscriberRow';
import SubscriberDetail from './SubscriberDetail';
import AddSubscriberModal from './AddSubscriberModal';
import ImportCSVModal from './ImportCSVModal';
import BulkTagModal from './BulkTagModal';
import EnrollAutomationModal from './EnrollAutomationModal';
import FilterBar from './FilterBar';
import type { Subscriber, FilterRule } from '../../types';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE = window.snelNewsletter?.nonce as string;

/**
 * Throws on any non-2xx so callers can't silently swallow a failure.
 * WP REST errors arrive as { code, message, data:{status} }.
 */
async function api( path: string, opts: RequestInit = {} ): Promise< any > {
    const res = await fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } );

    let body: any = null;
    try {
        body = await res.json();
    } catch {
        // Non-JSON response — a PHP fatal, an HTML error page, a redirect.
    }

    if ( ! res.ok ) {
        const err: any = new Error( body?.message || `${ res.status } ${ res.statusText }` );
        err.status = res.status;
        err.code   = body?.code;
        throw err;
    }

    return body;
}

export default function Subscribers() {
    const [ subscribers, setSubscribers ] = useState< Subscriber[] >( [] );
    const [ allTags, setAllTags ] = useState< string[] >( [] );
    const [ search, setSearch ] = useState( '' );
    const [ filterTag, setFilterTag ] = useState( '' );
    const [ filterStatus, setFilterStatus ] = useState( '' );
    const [ advFilters, setAdvFilters ] = useState< FilterRule[] >( [] );
    const [ showFilters, setShowFilters ] = useState( false );
    const [ selected, setSelected ] = useState< string[] >( [] );
    const [ selectAllMatching, setSelectAllMatching ] = useState( false );
    const [ total, setTotal ] = useState( 0 );
    const [ showAddModal, setShowAddModal ] = useState( false );
    const [ showImportModal, setShowImportModal ] = useState( false );
    const [ showBulkTagModal, setShowBulkTagModal ] = useState( false );
    const [ showEnrollModal, setShowEnrollModal ] = useState( false );
    const [ activeSubscriber, setActiveSubscriber ] = useState< Subscriber | null >( null );
    const [ page, setPage ] = useState( 1 );
    const [ totalPages, setTotalPages ] = useState( 1 );
    const [ counts, setCounts ] = useState( { total: 0, active: 0, unsubscribed: 0, bounced: 0 } );
    const [ loading, setLoading ] = useState( true );

    // Merge the quick controls (search / tag / status) and the advanced filter
    // rows into one AND-ed condition stack the server understands.
    const buildFilters = useCallback( () => {
        const f = [];
        if ( search ) f.push( { field: 'search', operator: 'contains', value: search } );
        if ( filterTag ) f.push( { field: 'tag', operator: 'has', value: filterTag } );
        if ( filterStatus ) f.push( { field: 'status', operator: 'is', value: filterStatus } );
        return f.concat( advFilters );
    }, [ search, filterTag, filterStatus, advFilters ] );

    const loadSubscribers = useCallback( () => {
        setLoading( true );
        const filters = buildFilters();
        const params  = new URLSearchParams( { page, per_page: 20 } as any );
        if ( filters.length ) params.set( 'filters', JSON.stringify( filters ) );

        api( `/subscribers?${ params }` ).then( ( data ) => {
            setSubscribers( data.subscribers || [] );
            setTotalPages( data.pages || 1 );
            setTotal( data.total || 0 );
            setCounts( data.counts || counts );
            setLoading( false );
        } ).catch( ( e ) => {
            console.error( '[snel-newsletter] load subscribers failed:', e );
            setLoading( false );
        } );
    }, [ page, buildFilters ] );

    const loadTags = useCallback( () => {
        api( '/tags' ).then( ( data ) => {
            setAllTags( ( data || [] ).map( ( t: any ) => t.tag ) );
        } ).catch( ( e ) => console.error( '[snel-newsletter] load tags failed:', e ) );
    }, [] );

    useEffect( () => { loadSubscribers(); }, [ loadSubscribers ] );
    useEffect( () => { loadTags(); }, [ loadTags ] );

    // Debounce search.
    const [ searchInput, setSearchInput ] = useState( '' );
    useEffect( () => {
        const timer = setTimeout( () => { setSearch( searchInput ); setPage( 1 ); }, 300 );
        return () => clearTimeout( timer );
    }, [ searchInput ] );

    const allSelected = subscribers.length > 0 && subscribers.every( ( s ) => selected.includes( s.id ) );

    const clearSelection = () => {
        setSelected( [] );
        setSelectAllMatching( false );
    };

    const toggleAll = () => {
        setSelectAllMatching( false );
        setSelected( allSelected ? [] : subscribers.map( ( s ) => s.id ) );
    };

    const toggleOne = ( id: string ) => {
        setSelectAllMatching( false );
        setSelected( ( prev ) => prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ] );
    };

    // "Select all N matching" — pull every matching ID so bulk actions hit the
    // whole filtered set, not just the visible page.
    const selectAllMatchingRows = () => {
        api( '/subscribers/query-ids', {
            method: 'POST',
            body: JSON.stringify( { filters: buildFilters() } ),
        } ).then( ( data ) => {
            // query-ids returns ints; the rows carry string ids — normalize so
            // the checkboxes actually render as selected.
            setSelected( ( data.ids || [] ).map( ( id: any ) => String( id ) ) );
            setSelectAllMatching( true );
        } ).catch( ( e ) => alert( e.message ) );
    };

    // Changing any filter invalidates the current selection and page.
    const handleAdvFiltersChange = ( next: FilterRule[] ) => {
        setAdvFilters( next );
        setPage( 1 );
        clearSelection();
    };

    // Rejects on failure — AddSubscriberModal renders the message.
    const handleAdd = async ( data: { email: string; name: string; tags: string[] } ) => {
        const res = await api( '/subscribers', {
            method: 'POST',
            body: JSON.stringify( data ),
        } );

        if ( ! res?.success ) {
            throw new Error( __( 'Could not add subscriber.', 'snel-newsletter' ) );
        }

        setShowAddModal( false );
        loadSubscribers();
        loadTags();
    };

    const handleDelete = ( id: string ) => {
        api( `/subscribers/${ id }`, { method: 'DELETE' } ).then( () => {
            loadSubscribers();
            loadTags();
        } ).catch( ( e ) => alert( e.message ) );
    };

    const handleBulkDelete = () => {
        if ( ! selected.length ) return;
        const msg = selectAllMatching
            ? __( 'Delete all', 'snel-newsletter' ) + ` ${ selected.length } ` + __( 'matching subscribers? This cannot be undone.', 'snel-newsletter' )
            : __( 'Delete', 'snel-newsletter' ) + ` ${ selected.length } ` + __( 'subscribers?', 'snel-newsletter' );
        if ( ! window.confirm( msg ) ) return;

        api( '/subscribers/bulk-delete', {
            method: 'POST',
            body: JSON.stringify( { ids: selected } ),
        } ).then( () => {
            clearSelection();
            loadSubscribers();
            loadTags();
        } ).catch( ( e ) => alert( e.message ) );
    };

    const handleEnroll = ( automationId: number ) => {
        return api( `/automations/${ automationId }/enroll`, {
            method: 'POST',
            body: JSON.stringify( { subscriber_ids: selected } ),
        } );
    };

    const handleBulkTag = ( { add, remove }: { add: string[]; remove: string[] } ) => {
        return api( '/subscribers/bulk-tag', {
            method: 'POST',
            body: JSON.stringify( { ids: selected, add, remove } ),
        } ).then( () => {
            loadSubscribers();
            loadTags();
        } );
    };

    if ( activeSubscriber ) {
        return (
            <div className="p-6">
                <div className="mb-8">
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Subscriber details', 'snel-newsletter' ) }</p>
                </div>
                <SubscriberDetail
                    subscriber={ activeSubscriber }
                    allTags={ allTags }
                    onBack={ () => setActiveSubscriber( null ) }
                    api={ api }
                    onRefresh={ () => { loadSubscribers(); loadTags(); } }
                />
            </div>
        );
    }

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

            {/* Table */}
            <div className="bg-white border border-gray-200 rounded-lg">
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <Search size={ 14 } className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                value={ searchInput }
                                onChange={ ( e ) => setSearchInput( e.target.value ) }
                                placeholder={ __( 'Search subscribers...', 'snel-newsletter' ) }
                                className="pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6] w-64"
                            />
                        </div>
                        {/* Quick tag/status filters — hidden while a selection is
                            active to free up room for the bulk-action buttons. */}
                        { selected.length === 0 && (
                            <>
                                <Select
                                    value={ filterTag }
                                    onChange={ ( v ) => { setFilterTag( v ); setPage( 1 ); clearSelection(); } }
                                    options={ [
                                        { value: '', label: __( 'All tags', 'snel-newsletter' ) },
                                        ...allTags.map( ( tag ) => ( { value: tag, label: tag } ) ),
                                    ] }
                                />
                                <Select
                                    value={ filterStatus }
                                    onChange={ ( v ) => { setFilterStatus( v ); setPage( 1 ); clearSelection(); } }
                                    options={ [
                                        { value: '', label: __( 'All statuses', 'snel-newsletter' ) },
                                        { value: 'active', label: __( 'Active', 'snel-newsletter' ) },
                                        { value: 'unsubscribed', label: __( 'Unsubscribed', 'snel-newsletter' ) },
                                        { value: 'bounced', label: __( 'Bounced', 'snel-newsletter' ) },
                                    ] }
                                />
                                <button
                                    type="button"
                                    onClick={ () => setShowFilters( ( v ) => ! v ) }
                                    className={ `inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors ${ showFilters || advFilters.length > 0 ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-gray-700 bg-white border-gray-200 hover:bg-gray-50' }` }
                                >
                                    <SlidersHorizontal size={ 12 } />
                                    { __( 'Filters', 'snel-newsletter' ) }
                                    { advFilters.length > 0 && (
                                        <span className="inline-flex items-center justify-center w-4 h-4 text-[10px] font-semibold text-white bg-blue-600 rounded-full">
                                            { advFilters.length }
                                        </span>
                                    ) }
                                </button>
                            </>
                        ) }
                    </div>
                    { selected.length > 0 && (
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-gray-500">{ selected.length } { __( 'selected', 'snel-newsletter' ) }</span>
                            <button
                                type="button"
                                onClick={ clearSelection }
                                className="text-xs text-gray-400 hover:text-gray-600 underline"
                            >
                                { __( 'Clear', 'snel-newsletter' ) }
                            </button>
                            <button
                                type="button"
                                onClick={ handleBulkDelete }
                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                            >
                                <Trash2 size={ 12 } />
                                { selectAllMatching ? __( 'Delete all', 'snel-newsletter' ) : __( 'Delete', 'snel-newsletter' ) }
                            </button>
                            <button type="button" onClick={ () => setShowBulkTagModal( true ) } className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                <Tag size={ 12 } />
                                { __( 'Edit Tags', 'snel-newsletter' ) }
                            </button>
                            <button type="button" onClick={ () => setShowEnrollModal( true ) } className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                <Workflow size={ 12 } />
                                { __( 'Enroll in Automation', 'snel-newsletter' ) }
                            </button>
                        </div>
                    ) }
                </div>

                {/* Advanced stacked filters. */}
                { showFilters && (
                    <FilterBar filters={ advFilters } onChange={ handleAdvFiltersChange } allTags={ allTags } />
                ) }

                {/* Persistent results bar — always offers a one-click select of the
                    entire filtered set, no need to tick the page checkbox first. */}
                { total > 0 && ! selectAllMatching && (
                    <div className="flex items-center justify-between px-4 py-2 border-b border-gray-100 bg-gray-50/40 text-xs text-gray-600">
                        <span><strong className="text-gray-900">{ total.toLocaleString() }</strong> { __( 'subscribers match', 'snel-newsletter' ) }</span>
                        <button type="button" onClick={ selectAllMatchingRows } className="inline-flex items-center gap-1 font-medium text-blue-700 hover:text-blue-900 underline">
                            { __( 'Select all', 'snel-newsletter' ) } { total.toLocaleString() }
                        </button>
                    </div>
                ) }
                { selectAllMatching && (
                    <div className="flex items-center justify-center gap-2 px-4 py-2 bg-blue-100 border-b border-blue-200 text-xs text-blue-900 font-medium">
                        <span>{ __( 'All', 'snel-newsletter' ) } { selected.length } { __( 'matching subscribers selected.', 'snel-newsletter' ) }</span>
                        <button type="button" onClick={ clearSelection } className="underline hover:text-blue-950">
                            { __( 'Clear selection', 'snel-newsletter' ) }
                        </button>
                    </div>
                ) }

                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px]">
                    <thead>
                        <tr className="border-b border-gray-200 bg-gray-50/50">
                            <th className="px-4 py-2.5 text-left w-10">
                                <input type="checkbox" checked={ allSelected } onChange={ toggleAll } className="rounded border-gray-300" />
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
                        { ! loading && subscribers.length > 0 ? (
                            subscribers.map( ( subscriber ) => (
                                <SubscriberRow
                                    key={ subscriber.id }
                                    subscriber={ subscriber }
                                    selected={ selected.includes( subscriber.id ) }
                                    onSelect={ () => toggleOne( subscriber.id ) }
                                    onDelete={ () => handleDelete( subscriber.id ) }
                                    onClick={ () => setActiveSubscriber( subscriber ) }
                                />
                            ) )
                        ) : (
                            <tr>
                                <td colSpan={ 7 } className="px-4 py-12 text-center">
                                    { loading ? (
                                        <p className="text-sm text-gray-400">{ __( 'Loading...', 'snel-newsletter' ) }</p>
                                    ) : (
                                        <>
                                            <Users size={ 32 } className="mx-auto text-gray-300 mb-3" />
                                            <p className="text-sm text-gray-500">{ __( 'No subscribers found', 'snel-newsletter' ) }</p>
                                        </>
                                    ) }
                                </td>
                            </tr>
                        ) }
                    </tbody>
                    </table>
                </div>

                { totalPages > 1 && (
                    <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                        <p className="text-xs text-gray-500">
                            { __( 'Page', 'snel-newsletter' ) } { page } { __( 'of', 'snel-newsletter' ) } { totalPages }
                        </p>
                        <div className="flex items-center gap-1">
                            <button type="button" onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) } disabled={ page <= 1 } className="p-1.5 text-gray-400 hover:text-gray-600 rounded disabled:opacity-30 transition-colors">
                                <ChevronLeft size={ 14 } />
                            </button>
                            <span className="px-2 text-xs text-gray-500">{ page } / { totalPages }</span>
                            <button type="button" onClick={ () => setPage( ( p ) => Math.min( totalPages, p + 1 ) ) } disabled={ page >= totalPages } className="p-1.5 text-gray-400 hover:text-gray-600 rounded disabled:opacity-30 transition-colors">
                                <ChevronRight size={ 14 } />
                            </button>
                        </div>
                    </div>
                ) }
            </div>

            { showAddModal && <AddSubscriberModal onClose={ () => setShowAddModal( false ) } allTags={ allTags } onAdd={ handleAdd } /> }
            { showImportModal && <ImportCSVModal onClose={ () => { setShowImportModal( false ); loadSubscribers(); loadTags(); } } allTags={ allTags } /> }
            { showBulkTagModal && (
                <BulkTagModal
                    selectedCount={ selected.length }
                    allTags={ allTags }
                    onClose={ () => setShowBulkTagModal( false ) }
                    onApply={ handleBulkTag }
                />
            ) }
            { showEnrollModal && (
                <EnrollAutomationModal
                    selectedCount={ selected.length }
                    api={ api }
                    onClose={ () => setShowEnrollModal( false ) }
                    onDone={ handleEnroll }
                />
            ) }
        </div>
    );
}
