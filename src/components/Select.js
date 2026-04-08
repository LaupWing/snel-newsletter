import { useState, useRef, useEffect } from '@wordpress/element';
import { ChevronDown } from 'lucide-react';

/**
 * Custom styled select dropdown.
 *
 * @param {Object}   props
 * @param {Array}    props.options    - Array of { value, label }
 * @param {string}   props.value      - Current selected value
 * @param {Function} props.onChange   - Called with new value
 * @param {boolean}  [props.disabled] - Disable the select
 * @param {string}   [props.className] - Additional classes for the trigger
 */
export default function Select( { options, value, onChange, disabled = false, className = '' } ) {
    const [ open, setOpen ] = useState( false );
    const ref = useRef();
    const current = options.find( ( o ) => o.value === value ) || options[ 0 ];

    useEffect( () => {
        const handleClick = ( e ) => {
            if ( ref.current && ! ref.current.contains( e.target ) ) setOpen( false );
        };
        document.addEventListener( 'mousedown', handleClick );
        return () => document.removeEventListener( 'mousedown', handleClick );
    }, [] );

    return (
        <div className="relative" ref={ ref }>
            <button
                type="button"
                onClick={ () => ! disabled && setOpen( ! open ) }
                disabled={ disabled }
                className={ `inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${ className }` }
            >
                <span className="text-gray-700">{ current?.label }</span>
                <ChevronDown size={ 12 } className={ `text-gray-400 transition-transform ${ open ? 'rotate-180' : '' }` } />
            </button>
            { open && (
                <div className="absolute left-0 top-full mt-1 bg-white rounded-lg shadow-lg ring-1 ring-black/10 py-1 z-50 min-w-[140px] max-h-60 overflow-y-auto">
                    { options.map( ( opt ) => (
                        <button
                            key={ opt.value }
                            type="button"
                            onClick={ () => { onChange( opt.value ); setOpen( false ); } }
                            className={ `w-full text-left px-3 py-2 text-xs transition-colors cursor-pointer ${ opt.value === value
                                ? 'bg-purple-50 text-purple-700 font-medium'
                                : 'text-gray-600 hover:bg-gray-50'
                            }` }
                        >
                            { opt.label }
                        </button>
                    ) ) }
                </div>
            ) }
        </div>
    );
}
