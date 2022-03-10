import { createMachine } from "xstate";

const documentEditorStateMachine = createMachine({
	id: "documentEditor",
	initial: "loading",
	context: {
		openDocuments: [],
	},
	states: {
		loading: {},
	},
});
