import { createMachine } from "xstate";

const documentEditorStateMachine = createMachine(
	{
		id: "documentEditor",
		initial: "loading",
		context: {
			openDocuments: [],
		},
		states: {
			loading: {
				on: {
					DOCUMENTS_LOADED: "editing",
					NO_DOCUMENTS_LOADED: "empty",
				},
			},
			settingActiveDocument: {
				on: {
					SUCCESS: {
						target: "editing",
					},
					FAILURE: {
						target: "empty",
					},
				},
			},
			empty: {
				on: {
					CREATE_DOCUMENT: {
						target: "creating",
					},
					OPEN_DOCUMENT: {
						target: "opening",
					},
				},
			},
			editing: {
				on: {
					CREATE_DOCUMENT: {
						target: "creating",
					},
					OPEN_DOCUMENT: {
						target: "opening",
					},
					DELETE_DOCUMENT: {
						target: "deleting",
					},
					SAVE_DOCUMENT: {
						target: "saving",
					},
					CLOSE_DOCUMENT: {
						target: "closing",
					},
				},
			},
			creating: {
				on: {
					SUCCESS: {
						target: "editing",
					},
					ERROR: {
						target: "showErrorToast",
					},
				},
			},
			showErrorToast: {
				on: {
					SHOW_ERROR_IN_TOAST: [
						{
							target: "settingActiveDocument",
							cond: "hasOpenDocuments",
						},
						{ target: "empty" },
					],
				},
			},
			opening: {
				on: {
					SUCCESS: {
						target: "editing",
					},
					LOADING: {
						target: "opening",
					},
					ERROR: {
						target: "showErrorToast",
					},
				},
			},
			saving: {
				on: {
					SUCCESS: {
						target: "editing",
					},
					ERROR: {
						target: "showErrorToast",
					},
				},
			},
			deleting: {},
			closing: {
				on: {
					DOCUMENT_CLOSED: [
						{
							target: "settingActiveDocument",
							cond: "hasOpenDocuments",
						},
						{
							target: "empty",
							cond: "hasNoOpenDocuments",
						},
					],
				},
			},
		},
	},
	{
		guards: {
			hasOpenDocuments: (context, event) => {
				return context.openDocuments.length > 0;
			},
			hasNoOpenDocuments: (context, event) => {
				return (
					!context?.openDocuments ||
					context.openDocuments.length === 0
				);
			},
		},
	}
);
