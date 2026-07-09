import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Tag } from 'lucide-react';

/**
 * Free-text tag entry. Commit a tag with Enter or comma; Backspace on an
 * empty input removes the last one.
 *
 * @param {Object}   props
 * @param {string[]} props.tags
 * @param {Function} props.onChange
 */
export default function TagInput( { tags, onChange } ) {
	const [ draft, setDraft ] = useState( '' );

	const commit = ( raw ) => {
		const next = raw.trim().replace( /,$/, '' ).trim();
		if ( next && ! tags.includes( next ) ) {
			onChange( [ ...tags, next ] );
		}
		setDraft( '' );
	};

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' || e.key === ',' ) {
			e.preventDefault();
			commit( draft );
		} else if ( e.key === 'Backspace' && ! draft && tags.length ) {
			onChange( tags.slice( 0, -1 ) );
		}
	};

	return (
		<div className="flex flex-wrap items-center gap-1.5 min-h-[34px] px-2 py-1.5 bg-white border border-gray-200 rounded-lg focus-within:border-gray-300 transition-colors">
			{ tags.map( ( tag ) => (
				<span
					key={ tag }
					className="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 border border-purple-100 rounded-full"
				>
					<Tag size={ 9 } />
					{ tag }
					<button
						type="button"
						onClick={ () => onChange( tags.filter( ( t ) => t !== tag ) ) }
						className="text-purple-400 hover:text-purple-700 transition-colors cursor-pointer"
					>
						<X size={ 10 } />
					</button>
				</span>
			) ) }
			<input
				type="text"
				value={ draft }
				onChange={ ( e ) => setDraft( e.target.value ) }
				onKeyDown={ handleKeyDown }
				onBlur={ () => commit( draft ) }
				placeholder={ tags.length ? '' : __( 'Type a tag, press Enter', 'snel-newsletter' ) }
				className="flex-1 min-w-[120px] border-0 shadow-none outline-none text-xs text-gray-700 placeholder:text-gray-400 focus:ring-0 p-0 bg-transparent"
			/>
		</div>
	);
}
