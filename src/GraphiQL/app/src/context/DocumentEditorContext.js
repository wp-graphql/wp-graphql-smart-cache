import {
	useContext,
	createContext,
	useState,
	useEffect,
	useReducer,
} from "@wordpress/element";
import { v4 as uuid } from "uuid";
import { useMutation, gql } from "@apollo/client";
import { GET_DOCUMENTS } from "../components/DocumentFinder/DocumentFinder";
import { Modal, Button } from "antd";
import { ExclamationCircleOutlined } from "@ant-design/icons";

export const DocumentEditorContext = createContext();
export const useDocumentEditorContext = () => useContext(DocumentEditorContext);

const GraphQLDocumentFragment = `
fragment GraphQLDocument on GraphQLDocument {
    __typename
    id
    title
    content
    rawContent: content(format: RAW)
    status
}
`;
const CREATE_DOCUMENT_MUTATION = gql`
	mutation CREATE_GRAPHQL_DOCUMENT($input: CreateGraphqlDocumentInput!) {
		createGraphqlDocument(input: $input) {
			graphqlDocument {
				...GraphQLDocument
			}
		}
	}
	${GraphQLDocumentFragment}
`;

const UPDATE_DOCUMENT_MUTATION = gql`
	mutation UPDATE_GRAPHQL_DOCUMENT($input: UpdateGraphqlDocumentInput!) {
		updateGraphqlDocument(input: $input) {
			graphqlDocument {
				...GraphQLDocument
			}
		}
	}
	${GraphQLDocumentFragment}
`;

const DELETE_DOCUMENT_MUTATION = gql`
	mutation DELETE_GRAPHQL_DOCUMENT($input: DeleteGraphqlDocumentInput!) {
		deleteGraphqlDocument(input: $input) {
			graphqlDocument {
				__typename
				id
			}
		}
	}
`;

export const DocumentEditorContextProvider = ({ children }) => {
	const [openTabs, setOpenTabs] = useState([]);
	const [activeTabId, setActiveTabId] = useState(0);
	const [createDocumentOnServer, createDocumentMutationResponse] =
		useMutation(CREATE_DOCUMENT_MUTATION);
	const [deleteDocumentOnServer, deleteDocumentMutationResponse] =
		useMutation(DELETE_DOCUMENT_MUTATION);
	const [isModalVisible, setIsModalVisible] = useState(false);

	const [modalConfig, setModalConfig] = useReducer(
		(state, newState) => ({ ...state, ...newState }),
		{
			title: null,
			visible: false,
			footer: null,
			content: null,
			centered: false,
			closable: true,
			onCancel: () => {
				setModalConfig({ visible: false });
			},
		}
	);

	/**
	 * Create a new GraphQL Document
	 */
	const createDocument = () => {
		const operationName = `GetPosts_` + new Date().valueOf();
		const newDocument = {
			id: uuid(),
			__typename: "TemporaryGraphQLDocument",
			title: `query ${operationName}`,
			content: `query ${operationName}{posts{nodes{id,title,date}}}`,
			isDirty: true,
		};
		const newOpenTabs = [...openTabs, newDocument];
		console.log({ newOpenTabs });
		setOpenTabs(newOpenTabs);
		setActiveTabId(newDocument.id);
	};

	const getDocumentByKey = (key) => {
		return openTabs.find((tab) => tab.id === key);
	};

	const _closeDocumentAndUpdateTabs = (documentId) => {
		// get index of document to close
		const indexToClose = openTabs.findIndex((tab) => tab.id === documentId);

		// if the document to close is the active tab, set the active tab to the next tab, or the previous tab if there is no next tab, or the first tab if its the only option
		if (documentId === activeTabId) {
			const newActiveTabId = openTabs[indexToClose + 1]?.id
				? openTabs[indexToClose + 1]?.id
				: openTabs[indexToClose - 1]?.id
				? openTabs[indexToClose - 1]?.id
				: 0;
			setActiveTabId(newActiveTabId);
		}

		const newOpenTabs = openTabs.filter((tab) => tab.id !== documentId);
		console.log({ newOpenTabs });
		setOpenTabs(newOpenTabs);
	};

	const saveDocument = async (documentId = null) => {
		let documentToSave;

		if (!documentId) {
			documentToSave = getCurrentDocument();
		} else {
			documentToSave = getDocumentByKey(documentId);
		}

		if (!documentToSave) {
			console.error("No document to save");
			return false;
		}

		if (!documentToSave.isDirty) {
			console.error("Document has no changes to save");
			return false;
		}

		// If the document is in memory only, we need to create the document on the server
		if ("TemporaryGraphQLDocument" === documentToSave.__typename) {
			const created = await createDocumentOnServer({
				variables: {
					input: {
						title: documentToSave.title,
						content: documentToSave.content,
					},
				},
				refetchQueries: [GET_DOCUMENTS],
				// onCompleted: (response) => {
				//     console.log(response);

				//     // Replace active document with the one that was just created
				//     const currentDocument = getCurrentDocument();
				//     const newDocument = { ...response.data.createGraphqlDocument.graphqlDocument, isDirty: false };

				//     // Find index of current tab
				//     const currentTabIndex = openTabs.findIndex( (tab) => tab.id === currentDocument.id );

				//     console.log( { currentTabIndex });

				//     const newTabs = [ ...openTabs ];

				//     console.log( { newTabs });

				//     newTabs[currentTabIndex] = newDocument;
				//     setOpenTabs(newTabs);

				//     // if the document to close is the active tab, set the active tab to the next tab, or the previous tab if there is no next tab, or the first tab if its the only option
				//     if ( documentId === activeTabId ) {
				//         const newActiveTabId = openTabs[ indexToClose + 1 ]?.id ? openTabs[ indexToClose + 1 ]?.id : ( openTabs[ indexToClose - 1 ]?.id ? openTabs[ indexToClose - 1 ]?.id : 0 );
				//         setActiveTabId( newActiveTabId );
				//     }

				//     return true;
				// },
				// onError: (error) => {
				//     // alert( `Error creating document: ${error.message}` );
				//     return false;
				// }
			})
				.then((res) => {
					return true;
				})
				.catch((error) => {
					console.error(error);
					return false;
				});

			return created;

			// else, we need to update the document on the server
		} else {
		}

		return false;
	};

	const closeDocument = (id = null) => {
		// If no id is provided, close the active tab
		if (id === null) {
			id = activeTabId;
		}

		const documentToClose = getDocumentByKey(id);

		if (documentToClose?.isDirty === true) {
			// show modal
			setModalConfig({
				visible: true,
				title: "Close GraphQL Document",
				content: "Do you want to save your changes before closing?",
				footer: [
					<Button
						key="submit"
						type="primary"
						onClick={() => {
							_closeDocumentAndUpdateTabs(id);
							setModalConfig({ visible: false });
						}}
					>
						Close without Saving
					</Button>,
					<Button
						key="submit"
						type="primary"
						onClick={() => {
							setModalConfig({ visible: false });
						}}
					>
						Continue Editing
					</Button>,
					<Button
						key="submit"
						type="primary"
						onClick={async () => {
							const saved = await saveDocument(id);
							console.log({ saved });
							if (saved) {
								_closeDocumentAndUpdateTabs(id);
								setModalConfig({ visible: false });
							} else {
								alert("Error saving document!");
							}
						}}
					>
						Save and Close
					</Button>,
				],
			});

			// confirm( {
			//     title: 'You have unsaved changes. Would you like to save before closing?',
			//     icon: <ExclamationCircleOutlined />,
			//     content: 'If you close this document, you will lose any unsaved changes.',
			//     okText: 'Close Without Saving',
			//     cancelText: 'Continue Editing',
			//     footer: null,
			//     onOk: async () => {
			//         _closeDocumentAndUpdateTabs( id );
			//     },
			//     onCancel:() =>{
			//         return;
			//         // _closeDocumentAndUpdateTabs( id );
			//     }
			// });
		} else {
			_closeDocumentAndUpdateTabs(id);
		}
	};

	const openDocument = (document) => {
		if (!document) {
			console.error("No document provided to openDocument");
			return;
		}

		if (!document.id) {
			console.error("No document id provided to openDocument");
			return;
		}

		// check if the document is already open
		const isOpen = openTabs.find((tab) => tab.id === document.id);
		if (isOpen) {
			setActiveTabId(document.id);
			return;
		}

		const newDocument = { ...document, isDirty: false };
		const newOpenTabs = [...openTabs, newDocument];
		setOpenTabs(newOpenTabs);
		setActiveTabId(newDocument.id);
	};

	const getCurrentDocument = () => {
		return openTabs.find((tab) => tab.id === activeTabId);
	};

	const deleteDocument = async (id = null) => {
		alert("deleteDocument");

		if (id === null) {
			id = activeTabId;
		}

		const documentToDelete = getDocumentByKey(id);

		if (!documentToDelete) {
			console.error("No document to delete");
			return false;
		}
	};

	const documentEditorContext = {
		createDocument,
		closeDocument,
		openDocument,
		saveDocument,
		deleteDocument,
		activeTabId,
		setActiveTabId,
		openTabs,
	};

	return (
		<DocumentEditorContext.Provider value={documentEditorContext}>
			{children}
			<Modal {...modalConfig}>{modalConfig?.content ?? null}</Modal>
		</DocumentEditorContext.Provider>
	);
};
