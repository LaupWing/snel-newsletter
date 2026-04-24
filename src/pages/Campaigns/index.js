import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Send, Plus, Search, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import Select from '../../components/Select';
import CampaignRow from './CampaignRow';
import CampaignDetail from './CampaignDetail';
import WarmupButton from './WarmupButton';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

export default function Campaigns() {
    const [ selectedCampaign, setSelectedCampaign ] = useState( null );
    const [ warmupActive, setWarmupActive ] = useState( false );

    useEffect( () => {
        api( '/warmup' ).then( ( data ) => setWarmupActive( !! data.enabled ) );
    }, [] );
    const [ campaigns, setCampaigns ] = useState( [] );
    const [ search, setSearch ] = useState( '' );
    const [ filterStatus, setFilterStatus ] = useState( '' );
    const [ page, setPage ] = useState( 1 );
    const [ totalPages, setTotalPages ] = useState( 1 );
    const [ counts, setCounts ] = useState( { total: 0, sent: 0, draft: 0, sending: 0, scheduled: 0 } );
    const [ loading, setLoading ] = useState( true );

    const loadCampaigns = useCallback( () => {
        setLoading( true );
        const params = new URLSearchParams( { page, per_page: 20 } );
        if ( search ) params.set( 'search', search );
        if ( filterStatus ) params.set( 'status', filterStatus );

        api( `/campaigns?${ params }` ).then( ( data ) => {
            setCampaigns( data.campaigns || [] );
            setTotalPages( data.pages || 1 );
            setCounts( data.counts || counts );
            setLoading( false );
        } );
    }, [ page, search, filterStatus ] );

    useEffect( () => { loadCampaigns(); }, [ loadCampaigns ] );

    // Debounce search.
    const [ searchInput, setSearchInput ] = useState( '' );
    useEffect( () => {
        const timer = setTimeout( () => { setSearch( searchInput ); setPage( 1 ); }, 300 );
        return () => clearTimeout( timer );
    }, [ searchInput ] );

    const handleDelete = ( id ) => {
        if ( ! confirm( __( 'Are you sure you want to delete this campaign?', 'snel-newsletter' ) ) ) return;
        api( `/campaigns/${ id }`, { method: 'DELETE' } ).then( () => loadCampaigns() );
    };

    const handleDuplicate = ( id ) => {
        api( `/campaigns/${ id }/duplicate`, { method: 'POST' } ).then( () => loadCampaigns() );
    };

    return (
        <>
        { selectedCampaign && (
            <CampaignDetail campaignId={ selectedCampaign } onClose={ () => setSelectedCampaign( null ) } />
        ) }
        <div className="p-6">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Create and manage campaigns', 'snel-newsletter' ) }</p>
                </div>
                <div className="flex items-center gap-2">
                    <WarmupButton
                        active={ warmupActive }
                        onToggle={ ( val ) => {
                            setWarmupActive( val );
                            api( `/warmup/${ val ? 'enable' : 'disable' }`, { method: 'POST' } );
                        } }
                    />
                    <a
                        href="post-new.php?post_type=snel_newsletter"
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors no-underline"
                    >
                        <Plus size={ 14 } />
                        { __( 'New Campaign', 'snel-newsletter' ) }
                    </a>
                </div>
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
                { counts.scheduled > 0 && (
                    <div className="flex items-center gap-2">
                        <span className="w-2 h-2 rounded-full bg-purple-500" />
                        <span className="text-sm text-gray-600">
                            <strong className="text-gray-900">{ counts.scheduled }</strong> { __( 'scheduled', 'snel-newsletter' ) }
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
                                value={ searchInput }
                                onChange={ ( e ) => setSearchInput( e.target.value ) }
                                placeholder={ __( 'Search campaigns...', 'snel-newsletter' ) }
                                className="pl-9 pr-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6] w-64"
                            />
                        </div>
                        <Select
                            value={ filterStatus }
                            onChange={ ( v ) => { setFilterStatus( v ); setPage( 1 ); } }
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
                        { ! loading && campaigns.length > 0 ? (
                            campaigns.map( ( campaign ) => (
                                <CampaignRow
                                    key={ campaign.id }
                                    campaign={ campaign }
                                    onDelete={ () => handleDelete( campaign.id ) }
                                    onDuplicate={ () => handleDuplicate( campaign.id ) }
                                    onViewStats={ () => setSelectedCampaign( campaign.id ) }
                                />
                            ) )
                        ) : (
                            <tr>
                                <td colSpan="7" className="px-4 py-12 text-center">
                                    { loading ? (
                                        <Loader2 size={ 20 } className="mx-auto animate-spin text-gray-400" />
                                    ) : (
                                        <>
                                            <Send size={ 32 } className="mx-auto text-gray-300 mb-3" />
                                            <p className="text-sm text-gray-500">{ __( 'No campaigns yet', 'snel-newsletter' ) }</p>
                                            <a
                                                href="post-new.php?post_type=snel_newsletter"
                                                className="inline-flex items-center gap-2 mt-3 px-4 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors no-underline"
                                            >
                                                <Plus size={ 14 } />
                                                { __( 'Create your first campaign', 'snel-newsletter' ) }
                                            </a>
                                        </>
                                    ) }
                                </td>
                            </tr>
                        ) }
                    </tbody>
                </table>

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
        </div>
        </>
    );
}
