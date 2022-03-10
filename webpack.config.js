const defaults = require("@wordpress/scripts/config/webpack.config");
const path = require("path");


module.exports = {
	...defaults,
	entry: {
		index: path.resolve(process.cwd(), "packages/wp-graphiql-document-editor", "index.js"),
	},
	externals: {
		react: "React",
		"react-dom": "ReactDOM",
		wpGraphiQL: "wpGraphiQL",
		graphql: "wpGraphiQL.GraphQL",
	},
};
