const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/admin/index.js',
	},
	output: {
		...defaultConfig.output,
		filename: '[name].js',
	},
};
