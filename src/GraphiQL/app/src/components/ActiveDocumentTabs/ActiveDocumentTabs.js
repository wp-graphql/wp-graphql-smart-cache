import { Tabs, Empty, Button } from "antd";
import { FileAddOutlined } from "@ant-design/icons";
import { useDocumentEditorContext } from "../../context/DocumentEditorContext";
import TabPaneTitle from "./components/TabPaneTitle/TabPaneTitle";
import styled from "styled-components";
import { QueryEditor } from "graphiql/dist/components/QueryEditor";

const { useAppContext } = window.wpGraphiQL;

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
	const {
		activeDocumentId,
		setActiveDocumentId,
		openTabs,
		createDocument,
		closeDocument,
	} = useDocumentEditorContext();

	const { schema } = useAppContext();

	// Return empty state if there are no open document tabs
	if (!openTabs || openTabs.length === 0) {
		return (
			<Empty description="You have no GraphQL documents open. Open a GraphQL document now.">
				<Button
					type="primary"
					icon={<FileAddOutlined />}
					onClick={() => {
						createDocument();
					}}
				>
					Create New Document
				</Button>
			</Empty>
		);
	}

	const onEditTab = (key, action) => {
		switch (action) {
			case "remove":
				closeDocument(key);
				break;
			case "add":
				createDocument();
				break;
		}
	};

	return (
		<StyledTabContainer>
			<Tabs
				type="editable-card"
				activeKey={activeDocumentId}
				onEdit={onEditTab}
				onChange={(key) => {
					const newactiveDocumentId = key.toString();
					setActiveDocumentId(newactiveDocumentId);
				}}
			>
				{openTabs.map((tab, index) => {
					return (
						<TabPane
							tab={<TabPaneTitle document={tab} />}
							key={tab?.id ?? index}
							closable={true}
						>
							<div
								style={{
									width: `100%`,
									padding: `10px`,
									background: `#f7f7f7`,
								}}
							>
								<QueryEditor
									schema={schema}
									// validationRules={this.props.validationRules}
									value={tab.query ?? null}
									// onEdit={this.handleEditQuery}
									// onHintInformationRender={this.handleHintInformationRender}
									// onClickReference={this.handleClickReference}
									// onCopyQuery={this.handleCopyQuery}
									// onPrettifyQuery={this.handlePrettifyQuery}
									// onMergeQuery={this.handleMergeQuery}
									// onRunQuery={this.handleEditorRunQuery}
									// editorTheme={this.props.editorTheme}
									readOnly={true}
									// externalFragments={this.props.externalFragments}
								/>
								{/* <pre>{JSON.stringify(tab, null, 2)}</pre> */}
								{/* <Tabs type="card">
									<TabPane
										tab={<h4>Headers</h4>}
										key={'headers'}
										closable={false}
									>
										<h2>Goo</h2>
									</TabPane>
								</Tabs> */}
							</div>
						</TabPane>
					);
				})}
			</Tabs>
		</StyledTabContainer>
	);
};

export default ActiveDocumentTabs;
