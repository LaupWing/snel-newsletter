import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Users, Send, Mail, MousePointerClick, ArrowRight, TrendingUp, Clock, Eye, BarChart3 } from 'lucide-react';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

const EMPTY_STATS = {
    subscribers: 0,
    campaignsSent: 0,
    avgOpenRate: 0,
    avgClickRate: 0,
};

function StatCard( { icon: Icon, iconBg, iconColor, label, value, suffix } ) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg p-5">
            <div className="flex items-center justify-between mb-3">
                <div className={ `w-8 h-8 ${ iconBg } rounded-lg flex items-center justify-center` }>
                    <Icon size={ 16 } className={ iconColor } />
                </div>
            </div>
            <p className="text-2xl font-bold text-gray-900">
                { value }{ suffix && <span className="text-sm font-normal text-gray-400 ml-0.5">{ suffix }</span> }
            </p>
            <p className="text-xs text-gray-500 mt-1">{ label }</p>
        </div>
    );
}

function CampaignRow( { campaign } ) {
    const statusColors = {
        sent: 'bg-emerald-50 text-emerald-700',
        draft: 'bg-gray-100 text-gray-600',
        sending: 'bg-blue-50 text-blue-700',
        failed: 'bg-red-50 text-red-700',
    };

    return (
        <div className="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors">
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-gray-900 truncate">{ campaign.subject }</p>
                <p className="text-xs text-gray-400 mt-0.5">
                    <Clock size={ 10 } className="inline mr-1" />
                    { campaign.sent_at || campaign.created_at }
                </p>
            </div>
            <div className="flex items-center gap-4 text-xs text-gray-500 shrink-0">
                <div className="flex items-center gap-1" title={ __( 'Recipients', 'snel-newsletter' ) }>
                    <Users size={ 12 } />
                    { campaign.recipients }
                </div>
                <div className="flex items-center gap-1" title={ __( 'Open rate', 'snel-newsletter' ) }>
                    <Eye size={ 12 } />
                    { campaign.open_rate }%
                </div>
                <div className="flex items-center gap-1" title={ __( 'Click rate', 'snel-newsletter' ) }>
                    <MousePointerClick size={ 12 } />
                    { campaign.click_rate }%
                </div>
            </div>
            <span className={ `px-2 py-0.5 text-xs font-medium rounded-full ${ statusColors[ campaign.status ] || statusColors.draft }` }>
                { campaign.status }
            </span>
        </div>
    );
}

export default function Dashboard() {
    const [ stats, setStats ] = useState( EMPTY_STATS );
    const [ campaigns, setCampaigns ] = useState( [] );

    useEffect( () => {
        api( '/dashboard' ).then( ( data ) => {
            if ( ! data ) return;
            setStats( {
                subscribers: data.subscribers || 0,
                campaignsSent: data.campaignsSent || 0,
                avgOpenRate: data.avgOpenRate || 0,
                avgClickRate: data.avgClickRate || 0,
            } );
            setCampaigns( data.recentCampaigns || [] );
        } ).catch( () => {} );
    }, [] );

    return (
        <div className="p-6">
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-xl font-bold text-gray-900">
                    Snel <em className="font-serif font-normal italic">Newsletter</em>
                </h1>
                <p className="text-sm text-gray-500 mt-1">
                    { __( 'Lightweight newsletter toolkit by Snelstack', 'snel-newsletter' ) } — v{ window.snelNewsletter?.version }
                </p>
            </div>

            {/* Stats grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    icon={ Users }
                    iconBg="bg-blue-50"
                    iconColor="text-blue-600"
                    label={ __( 'Total Subscribers', 'snel-newsletter' ) }
                    value={ stats.subscribers }
                />
                <StatCard
                    icon={ Send }
                    iconBg="bg-purple-50"
                    iconColor="text-purple-600"
                    label={ __( 'Campaigns Sent', 'snel-newsletter' ) }
                    value={ stats.campaignsSent }
                />
                <StatCard
                    icon={ Mail }
                    iconBg="bg-emerald-50"
                    iconColor="text-emerald-600"
                    label={ __( 'Avg. Open Rate', 'snel-newsletter' ) }
                    value={ stats.avgOpenRate }
                    suffix="%"
                />
                <StatCard
                    icon={ MousePointerClick }
                    iconBg="bg-amber-50"
                    iconColor="text-amber-600"
                    label={ __( 'Avg. Click Rate', 'snel-newsletter' ) }
                    value={ stats.avgClickRate }
                    suffix="%"
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {/* Recent campaigns */}
                <div className="lg:col-span-2 bg-white border border-gray-200 rounded-lg">
                    <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                                <BarChart3 size={ 16 } className="text-blue-600" />
                            </div>
                            <h2 className="text-sm font-semibold text-gray-900">
                                { __( 'Recent Campaigns', 'snel-newsletter' ) }
                            </h2>
                        </div>
                        <a href="?page=snel-newsletter-campaigns" className="text-xs text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors">
                            { __( 'View all', 'snel-newsletter' ) }
                            <ArrowRight size={ 12 } />
                        </a>
                    </div>
                    { campaigns.length > 0 ? (
                        <div className="divide-y divide-gray-100">
                            { campaigns.map( ( c ) => <CampaignRow key={ c.id } campaign={ c } /> ) }
                        </div>
                    ) : (
                        <div className="px-5 py-12 text-center">
                            <Send size={ 32 } className="mx-auto text-gray-300 mb-3" />
                            <p className="text-sm text-gray-500">{ __( 'No campaigns yet', 'snel-newsletter' ) }</p>
                            <p className="text-xs text-gray-400 mt-1">{ __( 'Create your first campaign to start sending newsletters.', 'snel-newsletter' ) }</p>
                            <a href="?page=snel-newsletter-campaigns" className="inline-flex items-center gap-2 mt-4 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                <Send size={ 14 } />
                                { __( 'Create Campaign', 'snel-newsletter' ) }
                            </a>
                        </div>
                    ) }
                </div>

                {/* Quick actions */}
                <div className="bg-white border border-gray-200 rounded-lg p-5">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
                            <TrendingUp size={ 16 } className="text-emerald-600" />
                        </div>
                        <h2 className="text-sm font-semibold text-gray-900">
                            { __( 'Quick Actions', 'snel-newsletter' ) }
                        </h2>
                    </div>
                    <div className="space-y-2">
                        <a href="?page=snel-newsletter-campaigns" className="flex items-center justify-between text-sm text-gray-600 hover:text-blue-600 transition-colors">
                            { __( 'Create new campaign', 'snel-newsletter' ) }
                            <ArrowRight size={ 14 } />
                        </a>
                        <a href="?page=snel-newsletter-subscribers" className="flex items-center justify-between text-sm text-gray-600 hover:text-blue-600 transition-colors">
                            { __( 'Manage subscribers', 'snel-newsletter' ) }
                            <ArrowRight size={ 14 } />
                        </a>
                        <a href="?page=snel-newsletter-subscribers" className="flex items-center justify-between text-sm text-gray-600 hover:text-blue-600 transition-colors">
                            { __( 'Import subscribers (CSV)', 'snel-newsletter' ) }
                            <ArrowRight size={ 14 } />
                        </a>
                        <a href="?page=snel-newsletter-settings" className="flex items-center justify-between text-sm text-gray-600 hover:text-blue-600 transition-colors">
                            { __( 'Configure email provider', 'snel-newsletter' ) }
                            <ArrowRight size={ 14 } />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
