import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ArrowLeft, Mail, User, Clock, Tag, Plus, X, Save, Loader2, CheckCircle, Trash2 } from 'lucide-react';
import TagBadge from '../../components/TagBadge';
import type { Subscriber, SubscriberStatus } from '../../types';

const STATUS_OPTIONS: { value: SubscriberStatus; label: string; bg: string }[] = [
    { value: 'active', label: 'Active', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    { value: 'unsubscribed', label: 'Unsubscribed', bg: 'bg-gray-100 text-gray-600 border-gray-200' },
    { value: 'bounced', label: 'Bounced', bg: 'bg-red-50 text-red-700 border-red-200' },
];

type Props = {
    subscriber: Subscriber;
    allTags: string[];
    onBack: () => void;
    api: ( path: string, opts?: RequestInit ) => Promise< any >;
    onRefresh?: () => void;
};

export default function SubscriberDetail( { subscriber, allTags, onBack, api, onRefresh }: Props ) {
    const [ name, setName ] = useState( subscriber.name || '' );
    const [ status, setStatus ] = useState( subscriber.status );
    const [ tags, setTags ] = useState( subscriber.tags || [] );
    const [ newTag, setNewTag ] = useState( '' );
    const [ showTagInput, setShowTagInput ] = useState( false );
    const [ saving, setSaving ] = useState( false );
    const [ saved, setSaved ] = useState( false );

    const handleSave = () => {
        setSaving( true );
        api( `/subscribers/${ subscriber.id }`, {
            method: 'PUT',
            body: JSON.stringify( { name, status, tags } ),
        } ).then( () => {
            setSaving( false );
            setSaved( true );
            setTimeout( () => setSaved( false ), 3000 );
            onRefresh && onRefresh();
        } );
    };

    const handleAddTag = () => {
        const tag = newTag.trim().toLowerCase();
        if ( tag && ! tags.includes( tag ) ) {
            setTags( [ ...tags, tag ] );
        }
        setNewTag( '' );
        setShowTagInput( false );
    };

    const handleRemoveTag = ( tag: string ) => {
        setTags( tags.filter( ( t ) => t !== tag ) );
    };

    const handleDelete = () => {
        if ( ! confirm( __( 'Are you sure you want to delete this subscriber?', 'snel-newsletter' ) ) ) return;
        api( `/subscribers/${ subscriber.id }`, { method: 'DELETE' } ).then( () => {
            onBack();
            onRefresh && onRefresh();
        } );
    };

    const currentStatus = STATUS_OPTIONS.find( ( s ) => s.value === status );

    return (
        <div>
            {/* Back button */}
            <button
                type="button"
                onClick={ onBack }
                className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors mb-6"
            >
                <ArrowLeft size={ 14 } />
                { __( 'Back to subscribers', 'snel-newsletter' ) }
            </button>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main info */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Email card */}
                    <div className="bg-white border border-gray-200 rounded-lg p-5">
                        <div className="flex items-center justify-between mb-5">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                                    <User size={ 18 } className="text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">{ subscriber.email }</p>
                                    <p className="text-xs text-gray-400">
                                        <Clock size={ 10 } className="inline mr-1" />
                                        { __( 'Added', 'snel-newsletter' ) } { subscriber.created_at }
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={ handleSave }
                                disabled={ saving }
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                            >
                                { saving ? (
                                    <><Loader2 size={ 14 } className="animate-spin" /> { __( 'Saving...', 'snel-newsletter' ) }</>
                                ) : saved ? (
                                    <><CheckCircle size={ 14 } /> { __( 'Saved!', 'snel-newsletter' ) }</>
                                ) : (
                                    <><Save size={ 14 } /> { __( 'Save', 'snel-newsletter' ) }</>
                                ) }
                            </button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">{ __( 'Name', 'snel-newsletter' ) }</label>
                                <input
                                    type="text"
                                    value={ name }
                                    onChange={ ( e ) => setName( e.target.value ) }
                                    placeholder={ __( 'Subscriber name...', 'snel-newsletter' ) }
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">{ __( 'Email', 'snel-newsletter' ) }</label>
                                <div className="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                                    <Mail size={ 14 } className="text-gray-400" />
                                    { subscriber.email }
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-2">{ __( 'Status', 'snel-newsletter' ) }</label>
                                <div className="flex items-center gap-2">
                                    { STATUS_OPTIONS.map( ( opt ) => (
                                        <button
                                            key={ opt.value }
                                            type="button"
                                            onClick={ () => setStatus( opt.value ) }
                                            className={ `px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${ status === opt.value
                                                ? opt.bg + ' border-current'
                                                : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'
                                            }` }
                                        >
                                            { opt.label }
                                        </button>
                                    ) ) }
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tags card */}
                    <div className="bg-white border border-gray-200 rounded-lg p-5">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-2">
                                <Tag size={ 14 } className="text-gray-400" />
                                <h3 className="text-sm font-semibold text-gray-900">{ __( 'Tags', 'snel-newsletter' ) }</h3>
                            </div>
                            <button
                                type="button"
                                onClick={ () => setShowTagInput( true ) }
                                className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors"
                            >
                                <Plus size={ 10 } />
                                { __( 'Add Tag', 'snel-newsletter' ) }
                            </button>
                        </div>

                        { showTagInput && (
                            <div className="flex items-center gap-2 mb-3">
                                <input
                                    type="text"
                                    value={ newTag }
                                    onChange={ ( e ) => setNewTag( e.target.value ) }
                                    onKeyDown={ ( e ) => e.key === 'Enter' && handleAddTag() }
                                    placeholder={ __( 'Tag name...', 'snel-newsletter' ) }
                                    autoFocus
                                    className="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                                />
                                <button type="button" onClick={ handleAddTag } className="px-3 py-1.5 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                                    { __( 'Add', 'snel-newsletter' ) }
                                </button>
                                <button type="button" onClick={ () => { setShowTagInput( false ); setNewTag( '' ); } } className="p-1.5 text-gray-400 hover:text-gray-600 transition-colors">
                                    <X size={ 14 } />
                                </button>
                            </div>
                        ) }

                        { /* Existing tags from allTags as suggestions */ }
                        { showTagInput && allTags.filter( ( t ) => ! tags.includes( t ) ).length > 0 && (
                            <div className="mb-3">
                                <p className="text-xs text-gray-400 mb-1.5">{ __( 'Existing tags:', 'snel-newsletter' ) }</p>
                                <div className="flex flex-wrap gap-1">
                                    { allTags.filter( ( t ) => ! tags.includes( t ) ).map( ( tag ) => (
                                        <button
                                            key={ tag }
                                            type="button"
                                            onClick={ () => setTags( [ ...tags, tag ] ) }
                                            className="px-2 py-0.5 text-xs text-gray-500 bg-gray-100 hover:bg-purple-50 hover:text-purple-700 rounded-full transition-colors"
                                        >
                                            + { tag }
                                        </button>
                                    ) ) }
                                </div>
                            </div>
                        ) }

                        <div className="flex flex-wrap gap-1.5">
                            { tags.length > 0 ? (
                                tags.map( ( tag ) => (
                                    <TagBadge key={ tag } tag={ tag } removable onRemove={ () => handleRemoveTag( tag ) } />
                                ) )
                            ) : (
                                <p className="text-xs text-gray-400 italic">{ __( 'No tags assigned', 'snel-newsletter' ) }</p>
                            ) }
                        </div>
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Quick stats */}
                    <div className="bg-white border border-gray-200 rounded-lg p-5">
                        <h3 className="text-sm font-semibold text-gray-900 mb-4">{ __( 'Activity', 'snel-newsletter' ) }</h3>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-500">{ __( 'Emails received', 'snel-newsletter' ) }</span>
                                <span className="text-sm font-medium text-gray-900">—</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-500">{ __( 'Emails opened', 'snel-newsletter' ) }</span>
                                <span className="text-sm font-medium text-gray-900">—</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-500">{ __( 'Links clicked', 'snel-newsletter' ) }</span>
                                <span className="text-sm font-medium text-gray-900">—</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-500">{ __( 'Last opened', 'snel-newsletter' ) }</span>
                                <span className="text-sm font-medium text-gray-900">—</span>
                            </div>
                        </div>
                        <p className="text-xs text-gray-400 mt-3 italic">{ __( 'Stats will appear once tracking is enabled.', 'snel-newsletter' ) }</p>
                    </div>

                    {/* Danger zone */}
                    <div className="bg-white border border-red-100 rounded-lg p-5">
                        <h3 className="text-sm font-semibold text-red-600 mb-2">{ __( 'Danger Zone', 'snel-newsletter' ) }</h3>
                        <p className="text-xs text-gray-500 mb-3">{ __( 'Permanently delete this subscriber and all associated data.', 'snel-newsletter' ) }</p>
                        <button
                            type="button"
                            onClick={ handleDelete }
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                        >
                            <Trash2 size={ 12 } />
                            { __( 'Delete Subscriber', 'snel-newsletter' ) }
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
