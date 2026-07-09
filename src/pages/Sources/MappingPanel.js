import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ArrowLeft, Mail, Tag, Check, X, Minus, Zap, Download, Loader2 } from 'lucide-react';
import Select from '../../components/Select';
import TagInput from './TagInput';
import { api } from './api';

const STATUS_META = {
	new:       { icon: Check, tone: 'text-green-600' },
	existing:  { icon: Minus, tone: 'text-gray-300' },
	duplicate: { icon: Minus, tone: 'text-gray-300' },
	invalid:   { icon: X,     tone: 'text-red-500' },
};

function Stat( { value, label, tone = 'text-gray-900' } ) {
	return (
		<div className="bg-white border border-gray-200 rounded-lg px-4 py-3">
			<div className={ `text-lg font-semibold ${ tone }` }>{ value }</div>
			<div className="text-[11px] text-gray-500 mt-0.5">{ label }</div>
		</div>
	);
}

export default function MappingPanel( { source, onBack, onSaved } ) {
	const saved = source.config;

	const [ emailField, setEmailField ] = useState(
		saved?.email_field || source.email_fields[ 0 ]?.key || ''
	);
	const [ tagKey, setTagKey ] = useState(
		saved?.tag_field
			? `${ saved.tag_source }:${ saved.tag_field }`
			: ''
	);
	const [ manualTags, setManualTags ] = useState( saved?.manual_tags || [] );
	const [ autoSync, setAutoSync ]     = useState( !! saved?.auto_sync );

	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ importing, setImporting ] = useState( false );
	const [ result, setResult ]   = useState( null );

	useEffect( () => {
		if ( ! emailField ) return;

		setLoading( true );
		setResult( null );
		const [ tagSource, tagField ] = tagKey ? tagKey.split( ':' ) : [ 'meta', '' ];

		const params = new URLSearchParams( {
			post_type:   source.post_type,
			email_field: emailField,
			tag_field:   tagField,
			tag_source:  tagSource,
			manual_tags: manualTags.join( ',' ),
		} );

		api( `/cpt-sources/preview?${ params }` ).then( ( data ) => {
			setPreview( data?.rows ? data : null );
			setLoading( false );
		} );
	}, [ source.post_type, emailField, tagKey, manualTags ] );

	const payload = () => {
		const [ tagSource, tagField ] = tagKey ? tagKey.split( ':' ) : [ 'meta', '' ];
		return {
			post_type:   source.post_type,
			email_field: emailField,
			tag_field:   tagField,
			tag_source:  tagSource,
			manual_tags: manualTags,
			auto_sync:   autoSync,
		};
	};

	const handleImport = () => {
		setImporting( true );
		api( `/cpt-sources/${ source.post_type }/import`, {
			method: 'POST',
			body: JSON.stringify( payload() ),
		} ).then( ( res ) => {
			setImporting( false );
			setResult( res?.result || null );
			onSaved?.();
		} );
	};

	const handleAutoSyncToggle = ( next ) => {
		setAutoSync( next );
		// Persist immediately — a toggle that needs a second click to save is a lie.
		api( '/cpt-sources', {
			method: 'POST',
			body: JSON.stringify( { ...payload(), auto_sync: next } ),
		} ).then( () => onSaved?.() );
	};

	const emailOptions = source.email_fields.map( ( f ) => ( {
		value: f.key,
		label: `${ f.key }  ·  ${ Math.round( f.confidence * 100 ) }%`,
	} ) );

	const tagOptions = [
		{ value: '', label: __( 'None — no field', 'snel-newsletter' ) },
		...source.tag_fields.map( ( f ) => ( {
			value: `${ f.source }:${ f.key }`,
			label: `${ f.key }  ·  ${ f.source }`,
		} ) ),
	];

	const totals = preview?.totals;

	return (
		<div className="p-6">
			<button
				type="button"
				onClick={ onBack }
				className="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-900 transition-colors cursor-pointer mb-4"
			>
				<ArrowLeft size={ 13 } />
				{ __( 'All sources', 'snel-newsletter' ) }
			</button>

			<div className="mb-8">
				<div className="flex items-center gap-2">
					<h1 className="text-xl font-bold text-gray-900">{ source.label }</h1>
					<code className="text-xs text-gray-400">{ source.post_type }</code>
				</div>
				<p className="text-sm text-gray-500 mt-1">
					{ __( 'Detection is a guess — confirm the fields and check the preview.', 'snel-newsletter' ) }
				</p>
			</div>

			{ /* Mapping */ }
			<div className="bg-white border border-gray-200 rounded-lg px-5 py-5 mb-4 space-y-5">
				<div className="grid sm:grid-cols-2 gap-5">
					<div>
						<label className="flex items-center gap-1.5 text-xs font-medium text-gray-700 mb-2">
							<Mail size={ 13 } className="text-gray-400" />
							{ __( 'Email field', 'snel-newsletter' ) }
							<span className="text-red-500">*</span>
						</label>
						<Select options={ emailOptions } value={ emailField } onChange={ setEmailField } fullWidth />
					</div>
					<div>
						<label className="flex items-center gap-1.5 text-xs font-medium text-gray-700 mb-2">
							<Tag size={ 13 } className="text-gray-400" />
							{ __( 'Tags from a field', 'snel-newsletter' ) }
							<span className="text-gray-400 font-normal">{ __( '(optional)', 'snel-newsletter' ) }</span>
						</label>
						<Select options={ tagOptions } value={ tagKey } onChange={ setTagKey } fullWidth />
					</div>
				</div>

				<div>
					<label className="flex items-center gap-1.5 text-xs font-medium text-gray-700 mb-2">
						<Tag size={ 13 } className="text-gray-400" />
						{ __( 'Extra tags', 'snel-newsletter' ) }
						<span className="text-gray-400 font-normal">{ __( '(optional)', 'snel-newsletter' ) }</span>
					</label>
					<TagInput tags={ manualTags } onChange={ setManualTags } />
					<p className="text-[11px] text-gray-400 mt-1.5">
						{ __( 'Applied to every subscriber from this source, on top of the field above.', 'snel-newsletter' ) }
					</p>
				</div>
			</div>

			{ /* Auto sync */ }
			<div className="bg-white border border-gray-200 rounded-lg px-5 py-4 mb-4 flex items-center justify-between">
				<div className="flex items-start gap-2.5">
					<Zap size={ 15 } className={ autoSync ? 'text-amber-500 mt-0.5' : 'text-gray-300 mt-0.5' } />
					<div>
						<div className="text-sm font-medium text-gray-900">{ __( 'Auto-sync', 'snel-newsletter' ) }</div>
						<p className="text-xs text-gray-500 mt-0.5">
							{ __( 'Add new subscribers automatically whenever a post of this type is published.', 'snel-newsletter' ) }
						</p>
					</div>
				</div>
				<button
					type="button"
					role="switch"
					aria-checked={ autoSync }
					onClick={ () => handleAutoSyncToggle( ! autoSync ) }
					className={ `relative shrink-0 w-10 h-[22px] rounded-full transition-colors cursor-pointer ${ autoSync ? 'bg-amber-500' : 'bg-gray-200' }` }
				>
					<span className={ `absolute top-0.5 left-0.5 w-[18px] h-[18px] bg-white rounded-full shadow-sm transition-transform ${ autoSync ? 'translate-x-[18px]' : '' }` } />
				</button>
			</div>

			{ /* Totals */ }
			{ totals && (
				<div className="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-4">
					<Stat value={ totals.scanned } label={ __( 'Posts scanned', 'snel-newsletter' ) } />
					<Stat value={ totals.importable } label={ __( 'Will import', 'snel-newsletter' ) } tone="text-green-600" />
					<Stat value={ totals.existing } label={ __( 'Already subscribed', 'snel-newsletter' ) } />
					<Stat value={ totals.duplicate } label={ __( 'Duplicate in source', 'snel-newsletter' ) } />
					<Stat value={ totals.invalid } label={ __( 'Invalid', 'snel-newsletter' ) } tone={ totals.invalid ? 'text-red-600' : 'text-gray-900' } />
					<Stat value={ totals.no_email } label={ __( 'No email', 'snel-newsletter' ) } />
				</div>
			) }

			{ /* Preview */ }
			<div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
				<div className="px-5 py-3.5 border-b border-gray-100 flex items-center gap-2 bg-gray-50/50">
					<span className="text-sm font-semibold text-gray-700">{ __( 'Preview', 'snel-newsletter' ) }</span>
					{ preview && (
						<span className="ml-1 px-2 py-0.5 text-xs text-gray-500 bg-gray-100 rounded-full">
							{ preview.rows.length }
						</span>
					) }
				</div>

				{ loading ? (
					<div className="px-5 py-12 text-center text-sm text-gray-400">
						{ __( 'Reading posts...', 'snel-newsletter' ) }
					</div>
				) : ! preview || preview.rows.length === 0 ? (
					<div className="px-5 py-12 text-center text-sm text-gray-400">
						{ __( 'No posts have a value in this field.', 'snel-newsletter' ) }
					</div>
				) : (
					<table className="w-full">
						<thead>
							<tr className="border-b border-gray-100 bg-gray-50/50">
								<th className="px-5 py-2.5 w-10"></th>
								<th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Post', 'snel-newsletter' ) }</th>
								<th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Email', 'snel-newsletter' ) }</th>
								<th className="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{ __( 'Tags', 'snel-newsletter' ) }</th>
							</tr>
						</thead>
						<tbody className="divide-y divide-gray-50">
							{ preview.rows.map( ( row ) => {
								const meta = STATUS_META[ row.status ] || STATUS_META.new;
								const Icon = meta.icon;
								return (
									<tr key={ row.post_id } className="hover:bg-gray-50/50 transition-colors">
										<td className="px-5 py-3"><Icon size={ 14 } className={ meta.tone } /></td>
										<td className="px-5 py-3 text-sm text-gray-600 truncate max-w-[220px]">
											{ row.title || <span className="text-gray-300">{ __( '(no title)', 'snel-newsletter' ) }</span> }
										</td>
										<td className="px-5 py-3">
											<code className={ `text-xs ${ row.status === 'invalid' ? 'text-red-600' : 'text-gray-700' }` }>
												{ row.email }
											</code>
										</td>
										<td className="px-5 py-3">
											{ row.tags.length ? (
												<div className="flex flex-wrap gap-1">
													{ row.tags.map( ( t ) => (
														<span key={ t } className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 border border-purple-100 rounded-full">
															<Tag size={ 9 } />
															{ t }
														</span>
													) ) }
												</div>
											) : (
												<span className="text-xs text-gray-300">—</span>
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				) }
			</div>

			{ /* Import */ }
			<div className="mt-4 bg-white border border-gray-200 rounded-lg px-5 py-4 flex items-center justify-between">
				<div className="text-xs text-gray-500">
					{ result ? (
						<span className="text-gray-700">
							<strong className="text-green-600">{ result.imported }</strong> { __( 'imported', 'snel-newsletter' ) }
							{ ' · ' }
							<strong>{ result.tagged }</strong> { __( 'existing tagged', 'snel-newsletter' ) }
							{ result.invalid > 0 && (
								<>{ ' · ' }<strong className="text-red-600">{ result.invalid }</strong> { __( 'invalid', 'snel-newsletter' ) }</>
							) }
						</span>
					) : saved?.last_sync ? (
						<>{ __( 'Last synced', 'snel-newsletter' ) } { saved.last_sync }</>
					) : (
						__( 'Existing subscribers keep their tags — nothing is overwritten.', 'snel-newsletter' )
					) }
				</div>
				<button
					type="button"
					onClick={ handleImport }
					disabled={ importing || ! emailField || ! totals }
					className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
				>
					{ importing
						? <><Loader2 size={ 13 } className="animate-spin" />{ __( 'Importing...', 'snel-newsletter' ) }</>
						: <><Download size={ 13 } />{ totals?.importable ? `${ __( 'Import', 'snel-newsletter' ) } ${ totals.importable }` : __( 'Run import', 'snel-newsletter' ) }</> }
				</button>
			</div>
		</div>
	);
}
