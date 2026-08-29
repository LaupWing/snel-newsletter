import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Tag, Plus, Zap, Search } from 'lucide-react';
import type { TagRule } from '../../types';

/**
 * Pick from tags that already exist. Deliberately not free-text: a typo here
 * silently creates a near-miss tag, which is how an automation ends up looking
 * broken. New tags are made on the Tags page.
 *
 * @param {Object}   props
 * @param {string[]} props.tags     Selected tag names.
 * @param {Function} props.onChange
 * @param {Object[]} props.options  All existing tags: { tag, count }.
 * @param {Object}   props.triggers tag name -> { name, status } of the automation it triggers.
 */
type Props = {
	tags: string[];
	onChange: ( tags: string[] ) => void;
	options: TagRule[];
	triggers?: Record< string, { name: string; status: string } >;
};

export default function TagSelect( { tags, onChange, options, triggers = {} }: Props ) {
	const [ open, setOpen ]     = useState( false );
	const [ search, setSearch ] = useState( '' );
	const wrapRef = useRef< HTMLDivElement >( null );

	useEffect( () => {
		if ( ! open ) return;

		const onClickOutside = ( e: MouseEvent ) => {
			if ( wrapRef.current && ! wrapRef.current.contains( e.target as Node ) ) {
				setOpen( false );
				setSearch( '' );
			}
		};

		document.addEventListener( 'mousedown', onClickOutside );
		return () => document.removeEventListener( 'mousedown', onClickOutside );
	}, [ open ] );

	const available = options.filter(
		( o ) => ! tags.includes( o.tag ) && o.tag.toLowerCase().includes( search.trim().toLowerCase() )
	);

	const add = ( tag: string ) => {
		onChange( [ ...tags, tag ] );
		setSearch( '' );
		setOpen( false );
	};

	return (
		<div ref={ wrapRef } className="relative">
			<div className="flex flex-wrap items-center gap-1.5 min-h-[34px] px-2 py-1.5 bg-white border border-gray-200 rounded-lg">
				{ tags.map( ( tag ) => {
					const trigger = triggers[ tag ];
					return (
						<span
							key={ tag }
							className={ `inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full border ${ trigger
								? 'text-amber-700 bg-amber-50 border-amber-100'
								: 'text-purple-700 bg-purple-50 border-purple-100'
							}` }
						>
							{ trigger ? <Zap size={ 9 } /> : <Tag size={ 9 } /> }
							{ tag }
							<button
								type="button"
								onClick={ () => onChange( tags.filter( ( t ) => t !== tag ) ) }
								className={ `transition-colors cursor-pointer ${ trigger ? 'text-amber-400 hover:text-amber-700' : 'text-purple-400 hover:text-purple-700' }` }
							>
								<X size={ 10 } />
							</button>
						</span>
					);
				} ) }

				<button
					type="button"
					onClick={ () => setOpen( ! open ) }
					className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 border border-dashed border-gray-200 rounded-full transition-colors cursor-pointer"
				>
					<Plus size={ 10 } />
					{ tags.length ? __( 'Add tag', 'snel-newsletter' ) : __( 'Select a tag', 'snel-newsletter' ) }
				</button>
			</div>

			{ open && (
				<div className="absolute z-20 mt-1 w-full max-w-xs bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
					<div className="flex items-center gap-1.5 px-2.5 py-2 border-b border-gray-100">
						<Search size={ 12 } className="text-gray-400 shrink-0" />
						<input
							type="text"
							value={ search }
							autoFocus
							onChange={ ( e ) => setSearch( e.target.value ) }
							placeholder={ __( 'Search tags', 'snel-newsletter' ) }
							className="flex-1 border-0 shadow-none outline-none text-xs text-gray-700 placeholder:text-gray-400 focus:ring-0 p-0 bg-transparent"
						/>
					</div>

					<div className="max-h-56 overflow-y-auto py-1">
						{ available.length === 0 ? (
							<p className="px-3 py-3 text-[11px] text-gray-400">
								{ options.length === 0
									? __( 'No tags yet. Create one on the Tags page.', 'snel-newsletter' )
									: __( 'No match.', 'snel-newsletter' ) }
							</p>
						) : available.map( ( o ) => {
							const trigger = triggers[ o.tag ];
							return (
								<button
									key={ o.tag }
									type="button"
									onClick={ () => add( o.tag ) }
									className="w-full flex items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50 transition-colors cursor-pointer"
								>
									{ trigger
										? <Zap size={ 11 } className="text-amber-500 shrink-0" />
										: <Tag size={ 11 } className="text-gray-400 shrink-0" /> }
									<span className="flex-1 text-xs text-gray-700 truncate">{ o.tag }</span>
									{ trigger && (
										<span className="text-[10px] text-amber-600 truncate max-w-[100px]" title={ trigger.name }>
											{ trigger.name }
										</span>
									) }
									<span className="text-[10px] text-gray-400 shrink-0">{ o.count }</span>
								</button>
							);
						} ) }
					</div>
				</div>
			) }
		</div>
	);
}
