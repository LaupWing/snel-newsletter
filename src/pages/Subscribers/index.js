import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Users, Search, Plus, Upload, Tag, ChevronLeft, ChevronRight, Trash2 } from 'lucide-react';
import Select from '../../components/Select';
import SubscriberRow from './SubscriberRow';
import AddSubscriberModal from './AddSubscriberModal';
import ImportCSVModal from './ImportCSVModal';

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

            {/* Table */}
            <div className="bg-white border border-gray-200 rounded-lg">
                <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div className="flex items-center gap-3">
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
                        <Select
                            value={ filterTag }
                            onChange={ setFilterTag }
                            options={ [
                                { value: '', label: __( 'All tags', 'snel-newsletter' ) },
                                ...allTags.map( ( tag ) => ( { value: tag, label: tag } ) ),
                            ] }
                        />
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

                <table className="w-full">
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

            { showAddModal && <AddSubscriberModal onClose={ () => setShowAddModal( false ) } allTags={ allTags } /> }
            { showImportModal && <ImportCSVModal onClose={ () => setShowImportModal( false ) } allTags={ allTags } /> }
        </div>
    );
}
