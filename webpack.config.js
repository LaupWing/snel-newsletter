const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        index: './src/index.js',
        editor: './src/editor.js',
        'blocks/newsletter-button/index': './src/blocks/newsletter-button/index.js',
        'blocks/newsletter-download/index': './src/blocks/newsletter-download/index.js',
    },
};
