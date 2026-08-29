// wp-scripts externalizes these to window.wp.* at build time, so they are not
// installed locally; ambient shims keep tsc happy (imports are typed as any).
declare module '@wordpress/blocks';
declare module '@wordpress/block-editor';
declare module '@wordpress/components';
declare module '@wordpress/data';
declare module '@wordpress/editor';
declare module '@wordpress/hooks';
declare module '@wordpress/plugins';

declare module '*.css';
