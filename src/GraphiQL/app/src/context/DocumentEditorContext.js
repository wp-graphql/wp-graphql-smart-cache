import { useContext, createContext, useState, useEffect } from '@wordpress/element';
import { v4 as uuid } from 'uuid';
import { useMutation, gql } from '@apollo/client';
import { GET_DOCUMENTS } from '../components/DocumentFinder/DocumentFinder';

export const DocumentEditorContext = createContext();
export const useDocumentEditorContext = () => useContext(DocumentEditorContext);

const CREATE_DOCUMENT_MUTATION = gql`
mutation CREATE_GRAPHQL_DOCUMENT($input: CreateGraphqlDocumentInput!) {
    createGraphqlDocument(input: $input) {
      graphqlDocument {
        __typename
        id
        title
        content
        rawContent: content(format: RAW)
      }
    }
  }
`;

export const DocumentEditorContextProvider = ({ children }) => {

    const [ openTabs, setOpenTabs ] = useState([]);
    const [ activeTabId, setActiveTabId ] = useState(0);

    const [ createDocumentOnServer, { data, loading, error } ] = useMutation( CREATE_DOCUMENT_MUTATION );

    /**
     * Create a new GraphQL Document
     */
    const createDocument = () => {
        const newDocument = {
            id: uuid(),
            __typename: 'TemporaryGraphQLDocument',
            title: 'New Document',
            // whether the document has changes that need to be saved
            isDirty: true,
        }
        const newOpenTabs = [ ...openTabs, newDocument ];
        console.log( { newOpenTabs })
        setOpenTabs(newOpenTabs);
        setActiveTabId( newDocument.id );
    }

    const getDocumentByKey = (key) => {
        return openTabs.find( (tab) => tab.id === key );
    }

    const closeDocument = ( id = null ) => {

        // If no id is provided, close the active tab
        if ( id === null ) {
            id = activeTabId;
        }

        const documentToClose = getDocumentByKey( id );
        let confirmed = true;
        
        if ( documentToClose?.isDirty === true ) {
            confirmed = confirm( 'You have unsaved changes. Please save before closing.' );
        }

        if ( false === confirmed ) {
            reuturn;
        }

        // get index of document to close
        const indexToClose = openTabs.findIndex( (tab) => tab.id === id );

        // if the document to close is the active tab, set the active tab to the next tab, or the previous tab if there is no next tab, or the first tab if its the only option
        if ( id === activeTabId ) {
            const newActiveTabId = openTabs[ indexToClose + 1 ]?.id ? openTabs[ indexToClose + 1 ]?.id : ( openTabs[ indexToClose - 1 ]?.id ? openTabs[ indexToClose - 1 ]?.id : 0 );
            setActiveTabId( newActiveTabId );
        }

        const newOpenTabs = openTabs.filter( (tab) => tab.id !== id );
        console.log( { newOpenTabs })
        setOpenTabs(newOpenTabs);
        
    }

    const openDocument = document => {

        if ( ! document ) {
            console.error( 'No document provided to openDocument' );
            return;
        }

        if ( ! document.id ) {
            console.error( 'No document id provided to openDocument' );
            return;
        }

        // check if the document is already open
        const isOpen = openTabs.find( (tab) => tab.id === document.id );
        if ( isOpen ) {
            setActiveTabId( document.id );
            return;
        }

        const newDocument = {...document, isDirty: false};
        const newOpenTabs = [ ...openTabs, newDocument ];
        setOpenTabs(newOpenTabs);
        setActiveTabId( newDocument.id );
    }
    
    const getCurrentDocument = () => {
        return openTabs.find( (tab) => tab.id === activeTabId );
    }

    const documentEditorContext = {
        createDocument,
        closeDocument,
        openDocument,
        saveDocument: () => { 
            const documentToSave = getCurrentDocument();
            
            if ( ! documentToSave ) {
                console.error( 'No document to save' );
            }
            
            if ( ! documentToSave.isDirty ) {
                console.error( 'Document has no changes to save' );
            }

            // If the document is in memory only, we need to create the document on the server
            if ( 'TemporaryGraphQLDocument' === documentToSave.__typename ) {

                createDocumentOnServer({
                    variables: {
                        input: {
                            title: documentToSave.title,
                            content: documentToSave.content,
                        }
                    },
                    refetchQueries: [
                        GET_DOCUMENTS,
                    ]
                }).then((response) => {
                    console.log(response);

                    // Replace active document with the one that was just created
                    const currentDocument = getCurrentDocument();
                    const newDocument = { ...response.data.createGraphqlDocument.graphqlDocument, isDirty: false };
                    
                    // Find index of current tab
                    const currentTabIndex = openTabs.findIndex( (tab) => tab.id === currentDocument.id );

                    console.log( { currentTabIndex });

                    const newTabs = [ ...openTabs ];

                    console.log( { newTabs });

                    newTabs[currentTabIndex] = newDocument;
                    setOpenTabs(newTabs);
                    setActiveTabId( newDocument.id );

                }).catch((error) => {
                    console.error(error);
                });

            // else, we need to update the document on the server
            } else {



            }

            alert( `saveDocument ${activeTabId}` ); 
        },
        deleteDocument: () => { alert( 'deleteDocument' ); },
        activeTabId,
        setActiveTabId,
        openTabs,
    }

    return (
        <DocumentEditorContext.Provider value={documentEditorContext}>
            {children}
        </DocumentEditorContext.Provider>
    )
}