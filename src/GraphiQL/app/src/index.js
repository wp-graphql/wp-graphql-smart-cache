import { FileOutlined } from "@ant-design/icons";
import DocumentEditor from "./components/DocumentEditor/DocumentEditor";
import { DocumentEditorContextProvider } from "./context/DocumentEditorContext";
const { hooks } = window.wpGraphiQL;

/**
 * Hook into GraphiQL to render the persisted queries document editor screen
 */
hooks.addFilter(
	"graphiql_router_screens",
	"graphiql-document-editor",
	(screens) => {
		screens.splice(1, 0, {
			id: "graphiql-document-editor",
			title: "Document Editor",
			icon: <FileOutlined />,
			render: () => <DocumentEditor />,
		});
		return screens;
	}
);

hooks.addFilter("graphiql_app", "graphiql-auth", (app) => {
	return <DocumentEditorContextProvider>{app}</DocumentEditorContextProvider>;
});
