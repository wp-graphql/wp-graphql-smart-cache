import ActiveDocumentTabs from "../ActiveDocumentTabs/ActiveDocumentTabs";
import DocumentFinder from "../DocumentFinder/DocumentFinder";
import FileMenu from "../FileMenu/FileMenu";

import { Layout } from "antd";
const { Sider, Content } = Layout;

const DocumentEditor = () => {
    return (
        <Layout className="graphql-document-editor" style={{height: `100%`, overflowY: `scroll`}}>
            <FileMenu />
            <Layout style={{ height: `100%` }}>
                <Sider width={300} style={{ background: `#fff`, overflowY: `scroll` }}>
                    <div >
                        <DocumentFinder />
                    </div>
                </Sider>
                <Layout style={{ padding: '24px', overflowY: `scroll` }}>
                    <Content>
                        <ActiveDocumentTabs />
                    </Content>
                </Layout>
            </Layout>
        </Layout>
    )
}

export default DocumentEditor;