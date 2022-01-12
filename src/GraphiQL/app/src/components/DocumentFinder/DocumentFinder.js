import { useQuery, gql } from '@apollo/client';
import { useDocumentEditorContext } from '../../context/DocumentEditorContext';

export const GET_DOCUMENTS = gql`
query GetDocuments {
    graphqlDocuments(where: { stati: [ PUBLISH, DRAFT ]}) {
        nodes {
            id
            title
            content
            rawContent: content(format: RAW)
        }
    }
}
`;

const DocumentFinder = (props) => {

    const { data, loading, error } = useQuery(GET_DOCUMENTS);

    const { openDocument } = useDocumentEditorContext();

    if ( loading ) {
        return <div>Loading...</div>;
    }

    if ( error ) {
        return <div>Error!</div>;
    }

    return (
        <>
            { data.graphqlDocuments.nodes.map( (document, index) => {
                return (
                    <div key={index}>
                        <div onClick={ () => { openDocument( document ) }} >
                            {document.title}
                        </div>
                    </div>
                );
            })}
        </>
    )
}

export default DocumentFinder;