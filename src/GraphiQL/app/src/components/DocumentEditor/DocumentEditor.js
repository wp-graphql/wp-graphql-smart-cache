import ActiveDocumentTabs from "../ActiveDocumentTabs/ActiveDocumentTabs";
import DocumentFinder from "../DocumentFinder/DocumentFinder";
import FileMenu from "../FileMenu/FileMenu";

const DocumentEditor = () => {
    return (
        <div className="graphql-document-editor">
            <FileMenu />
            <DocumentFinder />
            <ActiveDocumentTabs />
        </div>
    )
}

export default DocumentEditor;