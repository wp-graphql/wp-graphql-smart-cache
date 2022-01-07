import { useContext, createContext, useState, useEffect } from '@wordpress/element';

export const DocumentEditorContext = createContext();
export const useDocumentEditorContext = () => useContext(DocumentEditorContext);

const documentEditorContext = {
    createDocument: () => { alert( 'createDocument' ); },
    closeDocument: () => { alert( 'closeDocument' ); },
    saveDocument: () => { alert( 'saveDocument' ); },
    deleteDocument: () => { alert( 'deleteDocument' ); },
}

export const DocumentEditorContextProvider = ({ children }) => {
    return (
        <DocumentEditorContext.Provider value={documentEditorContext}>
            {children}
        </DocumentEditorContext.Provider>
    )
}