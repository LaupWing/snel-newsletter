const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        index: './src/index.tsx',
        editor: './src/editor.ts',
        'blocks/newsletter-button/index': './src/blocks/newsletter-button/index.ts',
        'blocks/newsletter-download/index': './src/blocks/newsletter-download/index.ts',
    },
};
