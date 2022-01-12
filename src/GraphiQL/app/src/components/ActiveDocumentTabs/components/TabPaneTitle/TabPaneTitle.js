import { Badge } from "antd";
import { useDocumentEditorContext } from "../../../../context/DocumentEditorContext";

const TabPaneTitle = ({ document }) => {

    const { isDirty } = document;

    return (
        <>
            <Badge status={ isDirty ? "warning" : "success" } text={document.title}  />
        </>
    )
}

export default TabPaneTitle;