import { useRef, useState, useEffect } from '@wordpress/element';

/**
 * Wraps children in a spinning conic-gradient border.
 *
 * The gradient is an oversized square sized to the wrapper's diagonal, so it
 * covers every corner at every rotation angle. It spins via the standalone
 * `rotate` property (see .animate-gradient-spin) rather than `transform`, so it
 * never fights the translate utilities that centre it.
 */
function useGradientSize( ref ) {
    const [ size, setSize ] = useState( 200 );

    useEffect( () => {
        const update = () => {
            if ( ref.current ) {
                const { offsetWidth: w, offsetHeight: h } = ref.current;
                setSize( Math.sqrt( w * w + h * h ) * 1.1 );
            }
        };

        update();
        window.addEventListener( 'resize', update );
        const observer = new ResizeObserver( update );
        if ( ref.current ) {
            observer.observe( ref.current );
        }

        return () => {
            window.removeEventListener( 'resize', update );
            observer.disconnect();
        };
    }, [] );

    return size;
}

export default function GradientButton( {
    children,
    radius = '0.5rem',
    border = '2px',
    className = '',
    style,
    ...rest
} ) {
    const ref          = useRef( null );
    const gradientSize = useGradientSize( ref );

    return (
        <span
            ref={ ref }
            className={ `relative inline-flex overflow-hidden ${ className }` }
            style={ { padding: border, borderRadius: radius, ...style } }
            { ...rest }
        >
            <span
                className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 animate-gradient-spin bg-[conic-gradient(from_0deg,#06b6d4,#3b82f6,#8b5cf6,#d946ef,#f43f5e,#f97316,#eab308,#22c55e,#06b6d4)]"
                style={ { width: `${ gradientSize }px`, height: `${ gradientSize }px` } }
            />
            { children }
        </span>
    );
}
