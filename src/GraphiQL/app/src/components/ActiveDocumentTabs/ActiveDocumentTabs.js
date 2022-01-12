import { Tabs, Empty, Button, Badge, Popconfirm } from 'antd';
import { FileAddOutlined, CloseOutlined } from '@ant-design/icons';
import { useDocumentEditorContext } from '../../context/DocumentEditorContext';
import TabPaneTitle from './components/TabPaneTitle/TabPaneTitle';
import styled from 'styled-components';

const { TabPane } = Tabs;

const StyledTabContainer = styled.div`
height: 100%;
> .ant-tabs {
    height: 100%;
    > .ant-tabs-nav {
        margin-bottom: 0;
    }
    > .ant-tabs-content-holder {
        height: 100%;
        > .ant-tabs-content {
            height: 100%;
            width: 100%;
            border: 1px solid #f0f0f0;
            border-top: none;
            > .ant-tabs-tabpane {
                height: 100%;
                display: flex;
                padding: 14px;
                background: white;
            }
        }
    }
}
`;

const ActiveDocumentTabs = () => {

    const { activeTabId, setActiveTabId, openTabs, createDocument, closeDocument } = useDocumentEditorContext();

    // Return empty state if there are no open document tabs
    if ( ! openTabs || openTabs.length === 0 ) {
        return (
            <Empty description="You have no GraphQL documents open. Open a GraphQL document now." >
                <Button type="primary" icon={<FileAddOutlined /> } onClick={() => { createDocument() }}>Create New Document</Button>
            </Empty>
        );
    }

    const onEditTab = (key, action) => {

        switch ( action ) {
            case 'remove':
                closeDocument( key );
                break;
            case 'add':
                createDocument();
                break;
        }
        
    }
    
    return (
        <StyledTabContainer>
            <Tabs 
                type="editable-card"
                activeKey={activeTabId}
                onEdit={onEditTab}
                onChange={
                    (key) => { 
                        const newActiveTabId = key.toString();
                        setActiveTabId( newActiveTabId );
                    }
                }
            >
                { openTabs.map( (tab, index) => {

                    return (
                        <TabPane 
                            tab={<TabPaneTitle document={tab} />} 
                            key={tab?.id ?? index} 
                            closable={true}
                            // closeIcon={
                            //     <Popconfirm 
                            //         title="Are you sure you want to close this document?"
                            //         onConfirm={() => { closeDocument( tab?.id ) }}
                            //         okText="Yes"
                            //         cancelText="No"
                            //         >
                            //             <CloseOutlined />
                            //         </Popconfirm>

                            // }
                        >
                            <div style={{ width: `100%` }}>
                                <pre>{JSON.stringify(tab, 2, null)}</pre>
                            </div>
                        </TabPane>
                    );
                }) }
            </Tabs>
        </StyledTabContainer>
    )

}

export default ActiveDocumentTabs;