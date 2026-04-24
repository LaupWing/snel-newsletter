import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Settings as SettingsIcon, Mail, Cloud, Save, Loader2, CheckCircle, AlertTriangle, Eye, EyeOff, ScrollText, Download } from 'lucide-react';
import Select from '../components/Select';
import Tabs from '../components/Tabs';

const API_URL = window.snelNewsletter?.restUrl;
const NONCE = window.snelNewsletter?.nonce;

function api( path, opts = {} ) {
    return fetch( `${ API_URL }${ path }`, {
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        ...opts,
    } ).then( ( r ) => r.json() );
}

const SES_REGIONS = [
    { value: 'us-east-1', label: 'US East (N. Virginia)' },
    { value: 'us-east-2', label: 'US East (Ohio)' },
    { value: 'us-west-1', label: 'US West (N. California)' },
    { value: 'us-west-2', label: 'US West (Oregon)' },
    { value: 'eu-west-1', label: 'EU (Ireland)' },
    { value: 'eu-west-2', label: 'EU (London)' },
    { value: 'eu-west-3', label: 'EU (Paris)' },
    { value: 'eu-central-1', label: 'EU (Frankfurt)' },
    { value: 'eu-north-1', label: 'EU (Stockholm)' },
    { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
    { value: 'ap-southeast-2', label: 'Asia Pacific (Sydney)' },
    { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
    { value: 'ca-central-1', label: 'Canada (Central)' },
    { value: 'sa-east-1', label: 'South America (São Paulo)' },
];

function InputField( { label, hint, type = 'text', value, onChange, placeholder, required } ) {
    const [ showPassword, setShowPassword ] = useState( false );
    const isSecret = type === 'password';

    return (
        <div className="space-y-1.5">
            <label className="block text-xs font-medium text-gray-700">
                { label }
                { required && <span className="text-red-400 ml-0.5">*</span> }
            </label>
            <div className="relative">
                <input
                    type={ isSecret && ! showPassword ? 'password' : 'text' }
                    value={ value }
                    onChange={ ( e ) => onChange( e.target.value ) }
                    placeholder={ placeholder }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                />
                { isSecret && value && (
                    <button
                        type="button"
                        onClick={ () => setShowPassword( ! showPassword ) }
                        className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        { showPassword ? <EyeOff size={ 14 } /> : <Eye size={ 14 } /> }
                    </button>
                ) }
            </div>
            { hint && <p className="text-xs text-gray-400">{ hint }</p> }
        </div>
    );
}

function SesSettings( { settings, setSettings } ) {
    const [ testEmail, setTestEmail ] = useState( '' );
    const [ testSending, setTestSending ] = useState( false );
    const [ testResult, setTestResult ] = useState( null ); // { success: bool, message: string }

    const allFieldsFilled = settings.ses_access_key && settings.ses_secret_key && settings.ses_region;

    const handleTestSend = () => {
        if ( ! testEmail || ! allFieldsFilled ) return;
        setTestSending( true );
        setTestResult( null );
        api( '/settings/test-email', {
            method: 'POST',
            body: JSON.stringify( { email: testEmail } ),
        } ).then( ( data ) => {
            setTestSending( false );
            if ( data?.success ) {
                setTestResult( { success: true, message: __( 'Test email sent! Check your inbox.', 'snel-newsletter' ) } );
            } else {
                setTestResult( { success: false, message: data?.message || __( 'Failed to send. Check your credentials.', 'snel-newsletter' ) } );
            }
            setTimeout( () => setTestResult( null ), 5000 );
        } ).catch( () => {
            setTestSending( false );
            setTestResult( { success: false, message: __( 'Connection error.', 'snel-newsletter' ) } );
        } );
    };

    return (
        <div className="space-y-6">
            <div className="bg-white border border-gray-200 rounded-lg p-5">
                <div className="flex items-center gap-3 mb-5">
                    <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                        <Cloud size={ 16 } className="text-blue-600" />
                    </div>
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">{ __( 'AWS SES Configuration', 'snel-newsletter' ) }</h2>
                        <p className="text-xs text-gray-400">{ __( 'Connect your Amazon SES account to send emails', 'snel-newsletter' ) }</p>
                    </div>
                </div>

                <div className="space-y-4">
                    <InputField
                        label={ __( 'Access Key ID', 'snel-newsletter' ) }
                        value={ settings.ses_access_key }
                        onChange={ ( v ) => setSettings( { ...settings, ses_access_key: v } ) }
                        placeholder="AKIAIOSFODNN7EXAMPLE"
                        required
                    />
                    <InputField
                        label={ __( 'Secret Access Key', 'snel-newsletter' ) }
                        type="password"
                        value={ settings.ses_secret_key }
                        onChange={ ( v ) => setSettings( { ...settings, ses_secret_key: v } ) }
                        placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
                        required
                    />
                    <div className="space-y-1.5">
                        <label className="block text-xs font-medium text-gray-700">
                            { __( 'Region', 'snel-newsletter' ) }
                            <span className="text-red-400 ml-0.5">*</span>
                        </label>
                        <Select
                            value={ settings.ses_region }
                            onChange={ ( v ) => setSettings( { ...settings, ses_region: v } ) }
                            fullWidth
                            options={ [
                                { value: '', label: __( 'Select region...', 'snel-newsletter' ) },
                                ...SES_REGIONS,
                            ] }
                        />
                        <p className="text-xs text-gray-400">{ __( 'Choose the region where your SES is configured.', 'snel-newsletter' ) }</p>
                    </div>
                </div>

                { allFieldsFilled && (
                    <div className="flex items-center gap-2 mt-4 px-3 py-2 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <CheckCircle size={ 14 } className="text-emerald-600" />
                        <p className="text-xs text-emerald-700">{ __( 'SES credentials configured. Connection will be verified on first send.', 'snel-newsletter' ) }</p>
                    </div>
                ) }

                { ( settings.ses_access_key || settings.ses_secret_key ) && ! allFieldsFilled && (
                    <div className="flex items-center gap-2 mt-4 px-3 py-2 bg-amber-50 border border-amber-100 rounded-lg">
                        <AlertTriangle size={ 14 } className="text-amber-600" />
                        <p className="text-xs text-amber-700">{ __( 'Please fill in all required fields.', 'snel-newsletter' ) }</p>
                    </div>
                ) }

                {/* Test email */}
                { allFieldsFilled && (
                    <div className="mt-5 pt-4 border-t border-gray-100">
                        <p className="text-xs font-medium text-gray-700 mb-2">{ __( 'Test connection', 'snel-newsletter' ) }</p>
                        <div className="flex items-center gap-2">
                            <input
                                type="email"
                                value={ testEmail }
                                onChange={ ( e ) => setTestEmail( e.target.value ) }
                                placeholder="you@example.com"
                                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                            />
                            <button
                                type="button"
                                onClick={ handleTestSend }
                                disabled={ ! testEmail || testSending }
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors disabled:opacity-50 shrink-0"
                            >
                                { testSending ? (
                                    <><Loader2 size={ 14 } className="animate-spin" /> { __( 'Sending...', 'snel-newsletter' ) }</>
                                ) : (
                                    <><Mail size={ 14 } /> { __( 'Send Test', 'snel-newsletter' ) }</>
                                ) }
                            </button>
                        </div>
                        <p className="text-xs text-gray-400 mt-1.5">{ __( 'Save settings first, then send a test email to verify your SES configuration.', 'snel-newsletter' ) }</p>
                        { testResult && (
                            <div className={ `flex items-center gap-2 mt-2 px-3 py-2 rounded-lg ${ testResult.success ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100' }` }>
                                { testResult.success ? <CheckCircle size={ 14 } className="text-emerald-600" /> : <AlertTriangle size={ 14 } className="text-red-600" /> }
                                <p className={ `text-xs ${ testResult.success ? 'text-emerald-700' : 'text-red-700' }` }>{ testResult.message }</p>
                            </div>
                        ) }
                    </div>
                ) }
            </div>
        </div>
    );
}

function SenderSettings( { settings, setSettings } ) {
    return (
        <div className="space-y-6">
            <div className="bg-white border border-gray-200 rounded-lg p-5">
                <div className="flex items-center gap-3 mb-5">
                    <div className="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center">
                        <Mail size={ 16 } className="text-purple-600" />
                    </div>
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">{ __( 'Sender Information', 'snel-newsletter' ) }</h2>
                        <p className="text-xs text-gray-400">{ __( 'How your newsletters appear in subscriber inboxes', 'snel-newsletter' ) }</p>
                    </div>
                </div>

                <div className="space-y-4">
                    <InputField
                        label={ __( 'From Name', 'snel-newsletter' ) }
                        value={ settings.from_name }
                        onChange={ ( v ) => setSettings( { ...settings, from_name: v } ) }
                        placeholder="Your Newsletter"
                        hint={ __( 'The name subscribers see in their inbox.', 'snel-newsletter' ) }
                        required
                    />
                    <InputField
                        label={ __( 'From Email', 'snel-newsletter' ) }
                        type="email"
                        value={ settings.from_email }
                        onChange={ ( v ) => setSettings( { ...settings, from_email: v } ) }
                        placeholder="newsletter@yourdomain.com"
                        hint={ __( 'Must be a verified email or domain in AWS SES.', 'snel-newsletter' ) }
                        required
                    />
                    <InputField
                        label={ __( 'Reply-To Email', 'snel-newsletter' ) }
                        type="email"
                        value={ settings.reply_to }
                        onChange={ ( v ) => setSettings( { ...settings, reply_to: v } ) }
                        placeholder="hello@yourdomain.com"
                        hint={ __( 'Where replies go. Leave empty to use the from email.', 'snel-newsletter' ) }
                    />
                </div>

                {/* Preview */}
                { ( settings.from_name || settings.from_email ) && (
                    <div className="mt-5 pt-4 border-t border-gray-100">
                        <p className="text-xs font-medium text-gray-500 mb-2">{ __( 'Inbox preview', 'snel-newsletter' ) }</p>
                        <div className="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                            <p className="text-sm font-semibold text-gray-900">{ settings.from_name || 'Your Newsletter' }</p>
                            <p className="text-xs text-gray-400">{ settings.from_email || 'newsletter@yourdomain.com' }</p>
                            <p className="text-sm text-gray-700 mt-1">Your next campaign subject line</p>
                            <p className="text-xs text-gray-400 truncate">Preview text will appear here in most email clients...</p>
                        </div>
                    </div>
                ) }
            </div>
        </div>
    );
}

function LogsTab() {
    const [ logs, setLogs ]       = useState( null );
    const [ loading, setLoading ] = useState( true );

    useEffect( () => {
        api( '/logs' )
            .then( ( data ) => setLogs( data?.logs || [] ) )
            .finally( () => setLoading( false ) );
    }, [] );

    const handleDownload = () => {
        const url = `${ API_URL }logs/download&_wpnonce=${ NONCE }`;
        window.location.href = url;
    };

    const statusColor = ( status ) =>
        status === 'failed' ? 'text-red-600 bg-red-50' : 'text-amber-600 bg-amber-50';

    return (
        <div className="space-y-4">
            <div className="bg-white border border-gray-200 rounded-lg p-5">
                <div className="flex items-center justify-between mb-5">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-gray-50 rounded-lg flex items-center justify-center">
                            <ScrollText size={ 16 } className="text-gray-600" />
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold text-gray-900">{ __( 'Error Logs', 'snel-newsletter' ) }</h2>
                            <p className="text-xs text-gray-400">{ __( 'Failed and retrying sends from the queue', 'snel-newsletter' ) }</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={ handleDownload }
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        <Download size={ 12 } />
                        { __( 'Download CSV', 'snel-newsletter' ) }
                    </button>
                </div>

                { loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 size={ 18 } className="animate-spin text-gray-400" />
                    </div>
                ) : ! logs?.length ? (
                    <div className="flex items-center gap-2 px-3 py-4 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <CheckCircle size={ 14 } className="text-emerald-600" />
                        <p className="text-xs text-emerald-700">{ __( 'No errors found. Everything looks good!', 'snel-newsletter' ) }</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="border-b border-gray-100">
                                    <th className="text-left py-2 pr-4 font-medium text-gray-500">{ __( 'Email', 'snel-newsletter' ) }</th>
                                    <th className="text-left py-2 pr-4 font-medium text-gray-500">{ __( 'Campaign', 'snel-newsletter' ) }</th>
                                    <th className="text-left py-2 pr-4 font-medium text-gray-500">{ __( 'Status', 'snel-newsletter' ) }</th>
                                    <th className="text-left py-2 pr-4 font-medium text-gray-500">{ __( 'Error', 'snel-newsletter' ) }</th>
                                    <th className="text-left py-2 font-medium text-gray-500">{ __( 'Date', 'snel-newsletter' ) }</th>
                                </tr>
                            </thead>
                            <tbody>
                                { logs.map( ( log ) => (
                                    <tr key={ log.id } className="border-b border-gray-50 hover:bg-gray-50">
                                        <td className="py-2 pr-4 text-gray-700 font-mono">{ log.email }</td>
                                        <td className="py-2 pr-4 text-gray-600">{ log.campaign || '—' }</td>
                                        <td className="py-2 pr-4">
                                            <span className={ `inline-flex px-1.5 py-0.5 rounded text-xs font-medium ${ statusColor( log.status ) }` }>
                                                { log.status }
                                            </span>
                                        </td>
                                        <td className="py-2 pr-4 text-gray-500 max-w-xs truncate" title={ log.error_message }>{ log.error_message || '—' }</td>
                                        <td className="py-2 text-gray-400">{ log.created_at }</td>
                                    </tr>
                                ) ) }
                            </tbody>
                        </table>
                    </div>
                ) }
            </div>
        </div>
    );
}

const TABS = [
    { id: 'ses', label: __( 'AWS SES', 'snel-newsletter' ), icon: Cloud },
    { id: 'sender', label: __( 'Sender', 'snel-newsletter' ), icon: Mail },
    { id: 'logs', label: __( 'Logs', 'snel-newsletter' ), icon: ScrollText },
];

export default function Settings() {
    const [ activeTab, setActiveTab ] = useState( 'ses' );
    const [ settings, setSettings ] = useState( {
        ses_access_key: '',
        ses_secret_key: '',
        ses_region: '',
        from_name: '',
        from_email: '',
        reply_to: '',
    } );
    const [ saving, setSaving ] = useState( false );
    const [ saved, setSaved ] = useState( false );
    const [ loading, setLoading ] = useState( true );

    // Load settings on mount.
    useEffect( () => {
        api( '/settings' )
            .then( ( data ) => {
                if ( data && ! data.code ) {
                    setSettings( ( prev ) => ( { ...prev, ...data } ) );
                }
            } )
            .finally( () => setLoading( false ) );
    }, [] );

    const handleSave = () => {
        setSaving( true );
        api( '/settings', {
            method: 'POST',
            body: JSON.stringify( settings ),
        } ).then( () => {
            setSaving( false );
            setSaved( true );
            setTimeout( () => setSaved( false ), 3000 );
        } );
    };

    return (
        <div className="p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-xl font-bold text-gray-900">
                        Snel <em className="font-serif font-normal italic">Newsletter</em>
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">{ __( 'Plugin settings', 'snel-newsletter' ) }</p>
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
                        <><Save size={ 14 } /> { __( 'Save Settings', 'snel-newsletter' ) }</>
                    ) }
                </button>
            </div>

            <Tabs tabs={ TABS } active={ activeTab } onChange={ setActiveTab } />

            { loading ? (
                <div className="flex items-center justify-center py-12">
                    <Loader2 size={ 20 } className="animate-spin text-gray-400" />
                </div>
            ) : (
                <>
                    { activeTab === 'ses' && <SesSettings settings={ settings } setSettings={ setSettings } /> }
                    { activeTab === 'sender' && <SenderSettings settings={ settings } setSettings={ setSettings } /> }
                    { activeTab === 'logs' && <LogsTab /> }
                </>
            ) }
        </div>
    );
}
