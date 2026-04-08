import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Send, Plus, Search, ChevronLeft, ChevronRight } from 'lucide-react';
import Select from '../../components/Select';
import CampaignRow from './CampaignRow';

const MOCK_CAMPAIGNS = [
    { id: 1, subject: 'Welcome to the Fitness Newsletter!', status: 'sent', recipients: 3842, sent: 3842, opened: 2881, clicked: 921, sent_at: '2026-03-15 10:30', tags: [ 'fitness' ] },
    { id: 2, subject: '5 Exercises You Can Do at Home', status: 'sent', recipients: 3910, sent: 3910, opened: 2738, clicked: 1564, sent_at: '2026-03-08 09:00', tags: [ 'fitness', 'nutrition' ] },
    { id: 3, subject: 'New Year, New Goals — Your 2026 Plan', status: 'sent', recipients: 4012, sent: 4012, opened: 3210, clicked: 802, sent_at: '2026-01-02 08:00', tags: [ 'fitness' ] },
    { id: 4, subject: 'Premium Workout Plan — Early Access', status: 'sent', recipients: 512, sent: 512, opened: 410, clicked: 245, sent_at: '2026-02-14 12:00', tags: [ 'paid' ] },
    { id: 5, subject: 'Weekly Nutrition Tips #12', status: 'draft', recipients: 0, sent: 0, opened: 0, clicked: 0, sent_at: null, tags: [ 'nutrition' ] },
    { id: 6, subject: 'Summer Body Challenge — Registration Open', status: 'draft', recipients: 0, sent: 0, opened: 0, clicked: 0, sent_at: null, tags: [ 'fitness', 'free-trial' ] },
    { id: 7, subject: 'How Protein Timing Affects Your Gains', status: 'sending', recipients: 3900, sent: 1240, opened: 0, clicked: 0, sent_at: '2026-04-08 14:00', tags: [ 'fitness', 'nutrition' ] },
];

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
