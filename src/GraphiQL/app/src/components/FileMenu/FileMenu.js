import { useDocumentEditorContext } from '../../context/DocumentEditorContext';
import { Menu, Layout } from 'antd';
import { CloseCircleOutlined, DeleteOutlined, DownOutlined, FileAddOutlined, QuestionCircleOutlined, SaveOutlined, SlackOutlined, YoutubeOutlined } from '@ant-design/icons';

const { Header } = Layout;
const { SubMenu } = Menu;

const FileMenu = () => {

    const { createDocument, saveDocument, closeDocument } = useDocumentEditorContext();

    return (
        <Header style={{ background: 'white', padding: 0, lineHeight:'35px', height: 'auto' }}  >
            <Menu mode="horizontal">
            <SubMenu key="file" title="File">
                <Menu.ItemGroup title="Document">
                    <Menu.Item key="document-create" onClick={() => createDocument() }> <FileAddOutlined /> New Document</Menu.Item>
                    <Menu.Item key="document-save" onClick={() => saveDocument() }> <SaveOutlined /> Save Document</Menu.Item>
                    <Menu.Item key="document-close" onClick={() => closeDocument() }> <CloseCircleOutlined /> Close Document</Menu.Item>
                    <Menu.Item key="document-delete" onClick={() => deleteDocument() }> <DeleteOutlined /> Delete Document</Menu.Item>
                </Menu.ItemGroup>   
            </SubMenu>
            <SubMenu key="help" title="Help">
                <Menu.ItemGroup title="Help">
                    <Menu.Item key="help-docs" ><a href="https://www.wpgraphql.com/docs/introduction/" target="_blank"><QuestionCircleOutlined /> WPGraphQL Documentation</a></Menu.Item>
                    <Menu.Item key="help-videos" ><a href="https://www.youtube.com/c/WPGraphQL" target="_blank"><YoutubeOutlined /> Videos</a></Menu.Item>
                    <Menu.Item key="help-slack" ><a href="https://join.slack.com/t/wp-graphql/shared_invite/zt-3vloo60z-PpJV2PFIwEathWDOxCTTLA" target="_blank"><SlackOutlined /> Slack Community</a></Menu.Item>
                </Menu.ItemGroup>
            </SubMenu>
            
        </Menu>
        </Header>
    )

}

export default FileMenu