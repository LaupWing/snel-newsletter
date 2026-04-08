import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Send, Plus, Search, Eye, MousePointerClick, Users, Clock, MoreHorizontal, ChevronLeft, ChevronRight, Copy, Trash2, BarChart3 } from 'lucide-react';
import Select from '../components/Select';

const MOCK_CAMPAIGNS = [
    { id: 1, subject: 'Welcome to the Fitness Newsletter!', status: 'sent', recipients: 3842, sent: 3842, opened: 2881, clicked: 921, sent_at: '2026-03-15 10:30', tags: [ 'fitness' ] },
    { id: 2, subject: '5 Exercises You Can Do at Home', status: 'sent', recipients: 3910, sent: 3910, opened: 2738, clicked: 1564, sent_at: '2026-03-08 09:00', tags: [ 'fitness', 'nutrition' ] },
    { id: 3, subject: 'New Year, New Goals — Your 2026 Plan', status: 'sent', recipients: 4012, sent: 4012, opened: 3210, clicked: 802, sent_at: '2026-01-02 08:00', tags: [ 'fitness' ] },
    { id: 4, subject: 'Premium Workout Plan — Early Access', status: 'sent', recipients: 512, sent: 512, opened: 410, clicked: 245, sent_at: '2026-02-14 12:00', tags: [ 'paid' ] },
    { id: 5, subject: 'Weekly Nutrition Tips #12', status: 'draft', recipients: 0, sent: 0, opened: 0, clicked: 0, sent_at: null, tags: [ 'nutrition' ] },
    { id: 6, subject: 'Summer Body Challenge — Registration Open', status: 'draft', recipients: 0, sent: 0, opened: 0, clicked: 0, sent_at: null, tags: [ 'fitness', 'free-trial' ] },
    { id: 7, subject: 'How Protein Timing Affects Your Gains', status: 'sending', recipients: 3900, sent: 1240, opened: 0, clicked: 0, sent_at: '2026-04-08 14:00', tags: [ 'fitness', 'nutrition' ] },
];

const STATUS_STYLES = {
    sent: { bg: 'bg-emerald-50 text-emerald-700', label: 'Sent' },
    draft: { bg: 'bg-gray-100 text-gray-600', label: 'Draft' },
    sending: { bg: 'bg-blue-50 text-blue-700', label: 'Sending' },
    scheduled: { bg: 'bg-purple-50 text-purple-700', label: 'Scheduled' },
    failed: { bg: 'bg-red-50 text-red-700', label: 'Failed' },
};

function CampaignRow( { campaign } ) {
    const [ menuOpen, setMenuOpen ] = useState( false );
    const status = STATUS_STYLES[ campaign.status ] || STATUS_STYLES.draft;
    const openRate = campaign.sent > 0 ? Math.round( ( campaign.opened / campaign.sent ) * 100 ) : 0;
    const clickRate = campaign.sent > 0 ? Math.round( ( campaign.clicked / campaign.sent ) * 100 ) : 0;

    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
            <td className="px-4 py-3">
                <div>
                    <p className="text-sm font-medium text-gray-900">{ campaign.subject }</p>
                    <div className="flex items-center gap-2 mt-1">
                        { campaign.tags.map( ( tag ) => (
                            <span key={ tag } className="px-1.5 py-0.5 text-[10px] font-medium bg-purple-50 text-purple-600 rounded">
                                { tag }
                            </span>
                        ) ) }
                    </div>
                </div>
            </td>
            <td className="px-4 py-3">
                <span className={ `inline-block px-2 py-0.5 text-xs font-medium rounded-full ${ status.bg }` }>
                    { status.label }
                </span>
                { campaign.status === 'sending' && (
                    <div className="mt-1.5 w-20">
                        <div className="w-full h-1 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-blue-500 rounded-full transition-all"
                                style={ { width: `${ Math.round( ( campaign.sent / campaign.recipients ) * 100 ) }%` } }
                            />
                        </div>
                        <p className="text-[10px] text-gray-400 mt-0.5">{ campaign.sent } / { campaign.recipients }</p>
                    </div>
                ) }
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1 text-sm text-gray-600">
                    <Users size={ 12 } className="text-gray-400" />
                    { campaign.recipients > 0 ? campaign.recipients.toLocaleString() : '—' }
                </div>
            </td>
            <td className="px-4 py-3">
                { campaign.sent > 0 ? (
                    <div className="flex items-center gap-1">
                        <Eye size={ 12 } className="text-gray-400" />
                        <span className={ `text-sm font-medium ${ openRate >= 50 ? 'text-emerald-600' : openRate >= 25 ? 'text-amber-600' : 'text-gray-600' }` }>
                            { openRate }%
                        </span>
                        <span className="text-xs text-gray-400">({ campaign.opened.toLocaleString() })</span>
                    </div>
                ) : (
                    <span className="text-xs text-gray-300">—</span>
                ) }
            </td>
            <td className="px-4 py-3">
                { campaign.sent > 0 ? (
                    <div className="flex items-center gap-1">
                        <MousePointerClick size={ 12 } className="text-gray-400" />
                        <span className={ `text-sm font-medium ${ clickRate >= 10 ? 'text-emerald-600' : clickRate >= 5 ? 'text-amber-600' : 'text-gray-600' }` }>
                            { clickRate }%
                        </span>
                        <span className="text-xs text-gray-400">({ campaign.clicked.toLocaleString() })</span>
                    </div>
                ) : (
                    <span className="text-xs text-gray-300">—</span>
                ) }
            </td>
            <td className="px-4 py-3">
                { campaign.sent_at ? (
                    <div className="flex items-center gap-1 text-xs text-gray-400">
                        <Clock size={ 10 } />
                        { campaign.sent_at }
                    </div>
                ) : (
                    <span className="text-xs text-gray-300">—</span>
                ) }
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
                            <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1 w-40">
                                { campaign.status === 'draft' && (
                                    <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                        <Send size={ 12 } />
                                        { __( 'Send', 'snel-newsletter' ) }
                                    </button>
                                ) }
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                    <BarChart3 size={ 12 } />
                                    { __( 'View Stats', 'snel-newsletter' ) }
                                </button>
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                    <Copy size={ 12 } />
                                    { __( 'Duplicate', 'snel-newsletter' ) }
                                </button>
                                <button type="button" className="w-full text-left px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2">
                                    <Trash2 size={ 12 } />
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

export default function Campaigns() {
    const [ campaigns ] = useState( MOCK_CAMPAIGNS );
    const [ search, setSearch ] = useState( '' );
    const [ filterStatus, setFilterStatus ] = useState( '' );
    const [ page ] = useState( 1 );
    const totalPages = 1;

    const filtered = campaigns.filter( ( c ) => {
        if ( search && ! c.subject.toLowerCase().includes( search.toLowerCase() ) ) return false;
        if ( filterStatus && c.status !== filterStatus ) return false;
        return true;
    } );

    const counts = {
        total: campaigns.length,
        sent: campaigns.filter( ( c ) => c.status === 'sent' ).length,
        draft: campaigns.filter( ( c ) => c.status === 'draft' ).length,
        sending: campaigns.filter( ( c ) => c.status === 'sending' ).length,
    };

    return (
        <div className="p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Create and manage campaigns', 'snel-newsletter' ) }</p>
                </div>
                <button
                    type="button"
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                >
                    <Plus size={ 14 } />
                    { __( 'New Campaign', 'snel-newsletter' ) }
                </button>
            </div>

            {/* Stats bar */}
            <div className="flex items-center gap-6 mb-6">
                <div className="flex items-center gap-2">
                    <Send size={ 14 } className="text-gray-400" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.total }</strong> { __( 'total', 'snel-newsletter' ) }
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-emerald-500" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.sent }</strong> { __( 'sent', 'snel-newsletter' ) }
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-gray-400" />
                    <span className="text-sm text-gray-600">
                        <strong className="text-gray-900">{ counts.draft }</strong> { __( 'drafts', 'snel-newsletter' ) }
                    </span>
                </div>
                { counts.sending > 0 && (
                    <div className="flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-blue-500 animate-pulse" />
                        <span className="text-sm text-gray-600">
                            <strong className="text-gray-900">{ counts.sending }</strong> { __( 'sending', 'snel-newsletter' ) }
                        </span>
                    </div>
                ) }
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
                                placeholder={ __( 'Search campaigns...', 'snel-newsletter' ) }
                                className="pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6] w-64"
                            />
                        </div>
                        <Select
                            value={ filterStatus }
                            onChange={ setFilterStatus }
                            options={ [
                                { value: '', label: __( 'All statuses', 'snel-newsletter' ) },
                                { value: 'sent', label: __( 'Sent', 'snel-newsletter' ) },
                                { value: 'draft', label: __( 'Draft', 'snel-newsletter' ) },
                                { value: 'sending', label: __( 'Sending', 'snel-newsletter' ) },
                                { value: 'scheduled', label: __( 'Scheduled', 'snel-newsletter' ) },
                            ] }
                        />
                    </div>
                </div>

                <table className="w-full">
                    <thead>
                        <tr className="border-b border-gray-200 bg-gray-50/50">
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Campaign', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Status', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Recipients', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Open Rate', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Click Rate', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Date', 'snel-newsletter' ) }</th>
                            <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        { filtered.length > 0 ? (
                            filtered.map( ( campaign ) => (
                                <CampaignRow key={ campaign.id } campaign={ campaign } />
                            ) )
                        ) : (
                            <tr>
                                <td colSpan="7" className="px-4 py-12 text-center">
                                    <Send size={ 32 } className="mx-auto text-gray-300 mb-3" />
                                    <p className="text-sm text-gray-500">{ __( 'No campaigns found', 'snel-newsletter' ) }</p>
                                </td>
                            </tr>
                        ) }
                    </tbody>
                </table>

                {/* Pagination */}
                { totalPages > 1 && (
                    <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                        <p className="text-xs text-gray-500">
                            { __( 'Showing', 'snel-newsletter' ) } { filtered.length } { __( 'of', 'snel-newsletter' ) } { campaigns.length }
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
        </div>
    );
}
