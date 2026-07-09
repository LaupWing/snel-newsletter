import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Database, RefreshCw } from 'lucide-react';
import SourceCard from './SourceCard';
import MappingPanel from './MappingPanel';
import { api } from './api';

export default function Sources() {
	const [ sources, setSources ]   = useState( [] );
	const [ loading, setLoading ]   = useState( true );
	const [ selected, setSelected ] = useState( null );

	const loadSources = useCallback( () => {
		setLoading( true );
		api( '/cpt-sources/scan' ).then( ( data ) => {
			setSources( Array.isArray( data ) ? data : [] );
			setLoading( false );
		} );
	}, [] );

	useEffect( () => { loadSources(); }, [ loadSources ] );

	if ( selected ) {
		return (
			<MappingPanel
				source={ selected }
				onBack={ () => { setSelected( null ); loadSources(); } }
				onSaved={ loadSources }
			/>
		);
	}

	const connectable = sources.filter( ( s ) => s.connectable );
	const rest        = sources.filter( ( s ) => ! s.connectable );

	return (
		<div className="p-6">
			<div className="flex items-center justify-between mb-8">
				<div>
					<h1 className="text-xl font-bold text-gray-900">
						Snel <em className="font-serif font-normal italic">Newsletter</em>
					</h1>
					<p className="text-sm text-gray-500 mt-1">{ __( 'Import subscribers from a post type', 'snel-newsletter' ) }</p>
				</div>
				<button
					type="button"
					onClick={ loadSources }
					disabled={ loading }
					className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer disabled:opacity-50"
				>
					<RefreshCw size={ 12 } className={ loading ? 'animate-spin' : '' } />
					{ __( 'Rescan', 'snel-newsletter' ) }
				</button>
			</div>

			{ loading ? (
				<div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center text-sm text-gray-400">
					{ __( 'Scanning post types...', 'snel-newsletter' ) }
				</div>
			) : connectable.length === 0 ? (
				<div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
					<Database size={ 32 } className="mx-auto text-gray-300 mb-3" />
					<p className="text-sm text-gray-500">{ __( 'No post type has an email field yet.', 'snel-newsletter' ) }</p>
					<p className="text-xs text-gray-400 mt-1.5 max-w-md mx-auto">
						{ __( 'A field only appears here once at least one post has saved a value that looks like an email address.', 'snel-newsletter' ) }
					</p>
				</div>
			) : (
				<div className="space-y-3">
					{ connectable.map( ( source ) => (
						<SourceCard
							key={ source.post_type }
							source={ source }
							onSelect={ () => setSelected( source ) }
						/>
					) ) }
				</div>
			) }

			{ ! loading && rest.length > 0 && (
				<div className="mt-8">
					<h2 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
						{ __( 'No email field found', 'snel-newsletter' ) }
					</h2>
					<div className="bg-white border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-50">
						{ rest.map( ( s ) => (
							<div key={ s.post_type } className="px-5 py-3 flex items-center justify-between">
								<div className="flex items-center gap-2">
									<span className="text-sm text-gray-600">{ s.label }</span>
									<code className="text-xs text-gray-400">{ s.post_type }</code>
								</div>
								<span className="text-xs text-gray-400">
									{ s.count } { __( 'posts', 'snel-newsletter' ) }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }
		</div>
	);
}
