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
import { Modal, Button, Space } from "antd";
import { SaveOutlined } from "@ant-design/icons";

export const DocumentEditorContext = createContext();
export const useDocumentEditorContext = () => useContext(DocumentEditorContext);

const GraphQLDocumentFragment = `
fragment GraphQLDocument on GraphqlDocument {
    __typename
    id
    title
    content
    query: content(format: RAW)
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

/**
 * Returns the list of open documents as stored in localStorage,
 * or an empty array if none are open.
 */
const getDefualtOpenTabs = () => {
	let defualtOpenTabs = [];
	if (window && window.localStorage) {
		const tabs = window.localStorage.getItem(
			"graphiql:documentEditor:openDocuments"
		);
		defualtOpenTabs = tabs ? JSON.parse(tabs) : [];
	}

	return defualtOpenTabs;
};

/**
 *
 * @returns the active tab id, or null if none is active.
 */
const getDefaultactiveDocumentId = () => {
	let defaultactiveDocumentId = null;
	if (window && window.localStorage) {
		defaultactiveDocumentId = window.localStorage.getItem(
			"graphiql:documentEditor:activeDocumentId"
		);
	}

	// If there's no activeDocumentId in localStorage, but there are open documents, use
	// the first open document as the active tab.
	if (null === defaultactiveDocumentId && getDefualtOpenTabs().length > 0) {
		defaultactiveDocumentId = getDefualtOpenTabs()[0].id ?? null;
	}

	return defaultactiveDocumentId;
};

export const DocumentEditorContextProvider = ({ children }) => {
	const [openTabs, _setOpenTabs] = useState(getDefualtOpenTabs());
	const [activeDocumentId, _setActiveDocumentId] = useState(
		getDefaultactiveDocumentId()
	);
	const [createDocumentOnServer, createDocumentMutationResponse] =
		useMutation(CREATE_DOCUMENT_MUTATION);
	const [deleteDocumentOnServer, deleteDocumentMutationResponse] =
		useMutation(DELETE_DOCUMENT_MUTATION);
	const [isModalVisible, setIsModalVisible] = useState(false);

	const setActiveDocumentId = (id) => {
		if (window && window.localStorage) {
			window.localStorage.setItem(
				"graphiql:documentEditor:activeDocumentId",
				id
			);
		}
		_setActiveDocumentId(id);
	};

	const setOpenTabs = (openTabs) => {
		if (window && window.localStorage) {
			window.localStorage.setItem(
				"graphiql:documentEditor:openDocuments",
				JSON.stringify(openTabs)
			);
		}
		_setOpenTabs(openTabs);
	};

	const [modalConfig, setModalConfig] = useReducer(
		(state, newState) => ({ ...state, ...newState }),
		{
			title: null,
			visible: false,
			footer: null,
			content: null,
			centered: false,
			closable: true,
			maskClosable: false,
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

		const operations = [
			`query ${operationName}{posts{nodes{id,title,date}}}`,
			`mutation ${operationName} ($input:CreatePostInput!){
				createPost(input:$input){
				  post {
					id
					title
				  }
				}
			  }`,
			`subscription ${operationName} { postPublished { post { id, title, date } } }`,
			`query ${operationName}{posts{nodes{id,title,date}}}
			mutation ${operationName}_1 ($input:CreatePostInput!){
				createPost(input:$input){
				  post {
					id
					title
				  }
				}
			  }`,
			`query ${operationName}{posts{nodes{id,title,date}}}
			  mutation ${operationName}_1 ($input:CreatePostInput!){
				createPost(input:$input){
				  post {
					id
					title
				  }
				}
			  }
			  query ${operationName}_2{posts{nodes{id,title,date}}}
			  mutation ${operationName}_3 ($input:CreatePostInput!){
				createPost(input:$input){
				  post {
					id
					title
				  }
				}
			  }`,
		];

		const newDocument = {
			id: uuid(),
			__typename: "TemporaryGraphQLDocument",
			title: `${operationName}`,
			query: operations[Math.floor(Math.random() * operations.length)], // get a random operation
			isDirty: true,
		};
		const newOpenTabs = [...openTabs, newDocument];
		console.log({ newOpenTabs });
		setOpenTabs(newOpenTabs);
		setActiveDocumentId(newDocument.id);
	};

	const getDocumentByKey = (key) => {
		return openTabs.find((tab) => tab.id === key);
	};

	const _closeDocumentAndUpdateTabs = (documentId) => {
		// get index of document to close
		const indexToClose = openTabs.findIndex((tab) => tab.id === documentId);

		// if the document to close is the active tab, set the active tab to the next tab, or the previous tab if there is no next tab, or the first tab if its the only option
		if (documentId === activeDocumentId) {
			const newactiveDocumentId = openTabs[indexToClose + 1]?.id
				? openTabs[indexToClose + 1]?.id
				: openTabs[indexToClose - 1]?.id
				? openTabs[indexToClose - 1]?.id
				: 0;
			setActiveDocumentId(newactiveDocumentId);
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
						content: documentToSave.query,
					},
				},
				refetchQueries: [
					GET_DOCUMENTS,
					{ variables: { first: 20, after: null } },
				],
				onCompleted: (response) => {
					console.log({ onCompleted: response });

					// Replace active document with the one that was just created
					const currentDocument = getCurrentDocument();
					const newDocument = {
						...response.createGraphqlDocument.graphqlDocument,
						isDirty: false,
					};

					// Find index of current tab
					const currentTabIndex = openTabs.findIndex(
						(tab) => tab.id === currentDocument.id
					);

					console.log({ currentTabIndex });

					const newTabs = [...openTabs];

					console.log({ newTabs });

					newTabs[currentTabIndex] = newDocument;
					setOpenTabs(newTabs);

					// if the document to close is the active tab, set the active tab to the next tab, or the previous tab if there is no next tab, or the first tab if its the only option
					if (documentId === activeDocumentId) {
						const newactiveDocumentId = openTabs[indexToClose + 1]
							?.id
							? openTabs[indexToClose + 1]?.id
							: openTabs[indexToClose - 1]?.id
							? openTabs[indexToClose - 1]?.id
							: 0;
						setActiveDocumentId(newactiveDocumentId);
					}

					return true;
				},
				onError: (error) => {
					// alert( `Error creating document: ${error.message}` );
					console.error(error);
					return false;
				},
			});
			// 	.then((res) => {
			// 		return true;
			// 	})
			// 	.catch((error) => {
			// 		console.error(error);
			// 		return false;
			// 	});

			return created;

			// else, we need to update the document on the server
		} else {
		}

		return false;
	};

	const closeDocument = (id = null) => {
		// If no id is provided, close the active tab
		if (id === null) {
			id = activeDocumentId;
		}

		const documentToClose = getDocumentByKey(id);

		if (documentToClose?.isDirty === true) {
			// show modal
			setModalConfig({
				visible: true,
				title: null,
				content: (
					<>
						<h2>
							Do you want to save the changes you made to "$
							{documentToClose.title}"?
						</h2>
						<p>Your changes will be lost if you don't save them.</p>
					</>
				),
				closable: false,
				footer: [
					<Space>
						<Button
							block
							key="submit"
							type="primary"
							icon={<SaveOutlined />}
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
						</Button>
						<Button
							block
							key="submit"
							type="danger"
							ghost
							onClick={() => {
								_closeDocumentAndUpdateTabs(id);
								setModalConfig({ visible: false });
							}}
						>
							Close without Saving
						</Button>
						<Button
							block
							key="submit"
							type="default"
							onClick={() => {
								setModalConfig({ visible: false });
							}}
						>
							Continue Editing
						</Button>
					</Space>,
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
			setActiveDocumentId(document.id);
			return;
		}

		const newDocument = { ...document, isDirty: false };
		const newOpenTabs = [...openTabs, newDocument];
		setOpenTabs(newOpenTabs);
		setActiveDocumentId(newDocument.id);
	};

	const getCurrentDocument = () => {
		return openTabs.find((tab) => tab.id === activeDocumentId);
	};

	const deleteDocument = async (id = null) => {
		alert("deleteDocument");

		if (id === null) {
			id = activeDocumentId;
		}

		const documentToDelete = getDocumentByKey(id);

		if (!documentToDelete) {
			console.error("No document to delete");
			return false;
		}

		const deleted = await deleteDocumentOnServer({
			variables: {
				input: {
					id: id,
					forceDelete: true,
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
			//     if ( documentId === activeDocumentId ) {
			//         const newactiveDocumentId = openTabs[ indexToClose + 1 ]?.id ? openTabs[ indexToClose + 1 ]?.id : ( openTabs[ indexToClose - 1 ]?.id ? openTabs[ indexToClose - 1 ]?.id : 0 );
			//         setActiveDocumentId( newactiveDocumentId );
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

		return deleted;
	};

	const documentEditorContext = {
		createDocument,
		closeDocument,
		openDocument,
		saveDocument,
		deleteDocument,
		activeDocumentId,
		setActiveDocumentId,
		openTabs,
	};

	return (
		<DocumentEditorContext.Provider value={documentEditorContext}>
			{children}
			<Modal {...modalConfig}>{modalConfig?.content ?? null}</Modal>
		</DocumentEditorContext.Provider>
	);
};
