import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Send, Eye, MousePointerClick, Users, Clock, MoreHorizontal, Copy, Trash2, BarChart3, Loader2, Zap } from 'lucide-react';

const STATUS_STYLES = {
    sent: { bg: 'bg-emerald-50 text-emerald-700', label: 'Sent' },
    draft: { bg: 'bg-gray-100 text-gray-600', label: 'Draft' },
    sending: { bg: 'bg-blue-50 text-blue-700', label: 'Sending' },
    scheduled: { bg: 'bg-purple-50 text-purple-700', label: 'Scheduled' },
    failed: { bg: 'bg-red-50 text-red-700', label: 'Failed' },
};

export { STATUS_STYLES };

export default function CampaignRow( { campaign, onDelete, onDuplicate, onViewStats } ) {
    const [ menuOpen, setMenuOpen ] = useState( false );
    const status = STATUS_STYLES[ campaign.status ] || STATUS_STYLES.draft;
    const openRate = campaign.sent > 0 ? Math.round( ( campaign.opened / campaign.sent ) * 100 ) : 0;
    const clickRate = campaign.sent > 0 ? Math.round( ( campaign.clicked / campaign.sent ) * 100 ) : 0;

    return (
        <tr className="border-b border-gray-100 hover:bg-gray-50/50 transition-colors">
            <td className="px-4 py-3">
                <div>
                    { campaign.edit_url ? (
                        <a href={ campaign.edit_url } className="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors no-underline">{ campaign.subject }</a>
                    ) : (
                        <p className="text-sm font-medium text-gray-900">{ campaign.subject }</p>
                    ) }
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
                { campaign.type === 'workflow' ? (
                    <div>
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold bg-violet-50 text-violet-600 rounded-full">
                            <Zap size={ 10 } fill="currentColor" />
                            { __( 'Workflow', 'snel-newsletter' ) }
                        </span>
                        { campaign.automation_name && (
                            <p className="text-[11px] text-violet-400 mt-1 truncate max-w-[140px]">{ campaign.automation_name }</p>
                        ) }
                    </div>
                ) : (
                    <span className="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold bg-blue-50 text-blue-600 rounded-full">
                        { __( 'Broadcast', 'snel-newsletter' ) }
                    </span>
                ) }
            </td>
            <td className="px-4 py-3">
                <span className={ `inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ${ status.bg }` }>
                    { campaign.status === 'sending' && <Loader2 size={ 10 } className="animate-spin" /> }
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
                                { campaign.status === 'draft' && campaign.edit_url && (
                                    <a href={ campaign.edit_url } className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2 no-underline">
                                        <Send size={ 12 } />
                                        { __( 'Edit', 'snel-newsletter' ) }
                                    </a>
                                ) }
                                <button type="button" onClick={ () => { setMenuOpen( false ); onViewStats && onViewStats(); } } className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                    <BarChart3 size={ 12 } />
                                    { __( 'View Stats', 'snel-newsletter' ) }
                                </button>
                                <button type="button" onClick={ () => { setMenuOpen( false ); onDuplicate && onDuplicate(); } } className="w-full text-left px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2">
                                    <Copy size={ 12 } />
                                    { __( 'Duplicate', 'snel-newsletter' ) }
                                </button>
                                <button type="button" onClick={ () => { setMenuOpen( false ); onDelete && onDelete(); } } className="w-full text-left px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2">
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
