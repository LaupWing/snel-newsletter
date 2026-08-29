import { __ } from '@wordpress/i18n';
import { ArrowRight, Mail, Tag, Zap, Plug } from 'lucide-react';

function Confidence( { value }: { value: number } ) {
	const pct  = Math.round( value * 100 );
	const tone = value >= 0.9
		? 'text-green-700 bg-green-50 border-green-100'
		: value >= 0.6
			? 'text-amber-700 bg-amber-50 border-amber-100'
			: 'text-gray-500 bg-gray-50 border-gray-100';

	return (
		<span className={ `px-1.5 py-0.5 text-[10px] font-medium rounded-full border ${ tone }` }>
			{ pct }%
		</span>
	);
}

type Props = {
	source: any;
	onSelect: () => void;
};

export default function SourceCard( { source, onSelect }: Props ) {
	const isCustom = source.kind === 'custom';
	const email    = source.email_fields[ 0 ];
	const tag      = source.tag_fields[ 0 ];

	return (
		<button
			type="button"
			onClick={ onSelect }
			className="w-full text-left bg-white border border-gray-200 rounded-lg px-5 py-4 hover:border-gray-300 hover:bg-gray-50/30 transition-colors cursor-pointer group"
		>
			<div className="flex items-start justify-between">
				<div className="min-w-0">
					<div className="flex items-center gap-2 flex-wrap">
						<h3 className="text-sm font-semibold text-gray-900">{ source.label }</h3>
						<code className="text-xs text-gray-400">{ source.id }</code>
						{ isCustom && (
							<span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 bg-blue-50 border border-blue-100 rounded-full">
								<Plug size={ 9 } />
								{ __( 'registered', 'snel-newsletter' ) }
							</span>
						) }
						{ source.config && (
							<span className="px-1.5 py-0.5 text-[10px] font-medium text-green-700 bg-green-50 border border-green-100 rounded-full">
								{ __( 'connected', 'snel-newsletter' ) }
							</span>
						) }
						{ source.config?.auto_sync && (
							<span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 border border-amber-100 rounded-full">
								<Zap size={ 9 } />
								{ __( 'auto-sync', 'snel-newsletter' ) }
							</span>
						) }
					</div>
					<p className="text-xs text-gray-500 mt-0.5">
						{ isCustom
							? source.description || __( 'Custom source', 'snel-newsletter' )
							: `${ source.count } ${ __( 'published posts', 'snel-newsletter' ) }` }
					</p>
				</div>
				<ArrowRight size={ 15 } className="text-gray-300 group-hover:text-gray-500 transition-colors shrink-0 mt-0.5" />
			</div>

			{ isCustom ? (
				source.count !== null && (
					<div className="mt-3.5 text-xs text-gray-500">
						{ source.count } { __( 'rows available', 'snel-newsletter' ) }
					</div>
				)
			) : (
				<div className="mt-3.5 flex flex-wrap items-center gap-x-8 gap-y-2">
					<div className="flex items-center gap-2 min-w-0">
						<Mail size={ 13 } className="text-gray-400 shrink-0" />
						<span className="text-xs text-gray-500">{ __( 'Email', 'snel-newsletter' ) }</span>
						<code className="text-xs text-gray-700 truncate">{ email.key }</code>
						<Confidence value={ email.confidence } />
						{ source.email_fields.length > 1 && (
							<span className="text-[10px] text-gray-400">+{ source.email_fields.length - 1 }</span>
						) }
					</div>

					<div className="flex items-center gap-2 min-w-0">
						<Tag size={ 13 } className="text-gray-400 shrink-0" />
						<span className="text-xs text-gray-500">{ __( 'Tags', 'snel-newsletter' ) }</span>
						{ tag ? (
							<>
								<code className="text-xs text-gray-700 truncate">{ tag.key }</code>
								<span className="px-1.5 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 border border-purple-100 rounded-full">
									{ tag.source }
								</span>
							</>
						) : (
							<span className="text-xs text-gray-300">{ __( 'none detected', 'snel-newsletter' ) }</span>
						) }
					</div>
				</div>
			) }
		</button>
	);
}
