import { QueryEditor } from "graphiql/dist/components/QueryEditor";
import { Button, Input, PageHeader, Space } from "antd";
import { CheckOutlined, EditOutlined, SaveOutlined } from "@ant-design/icons";
import { useState } from "@wordpress/element";
import { useDocumentEditorContext } from "../../../../context/DocumentEditorContext";
const { useAppContext } = window.wpGraphiQL;

const DocumentActions = () => {
	const { saveDocument } = useDocumentEditorContext();

	return (
		<Button
			icon={<SaveOutlined />}
			onClick={() => {
				saveDocument();
			}}
			type="default"
		>
			Save
		</Button>
	);
};

const DocumentTitle = ({ graphqlDocument }) => {
	const [isEditing, setIsEditing] = useState(false);
	const [value, setValue] = useState(graphqlDocument?.title ?? "");

	return (
		<div
			style={{
				display: `flex`,
				flexDirection: "row",
				justifyContent: "start",
			}}
		>
			<Space>
				{isEditing ? (
					<Input
						value={value}
						onChange={(e) => {
							setValue(e.target.value);
						}}
						onBlur={() => setIsEditing(false)}
					/>
				) : (
					value
				)}
				<Button
					type={isEditing ? "primary" : "text"}
					title={
						isEditing
							? "Set New Document Title"
							: "Edit Document Title"
					}
					onClick={() => setIsEditing(!isEditing)}
					icon={isEditing ? <CheckOutlined /> : <EditOutlined />}
				/>
			</Space>
		</div>
	);
};

const ActiveTabHeader = ({ graphqlDocument }) => {
	return (
		<PageHeader
			title={<DocumentTitle graphqlDocument={graphqlDocument} />}
			extra={<DocumentActions />}
		/>
	);
};

const DocumentTabPane = ({ graphqlDocument }) => {
	const { schema } = useAppContext();
	const { editCurrentDocument } = useDocumentEditorContext();

	const handleEditQuery = (payload) => {
		console.log({ handleEditQuery: payload });
		editCurrentDocument({
			query: payload,
		});
	};

	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				width: "100%",
			}}
		>
			<ActiveTabHeader graphqlDocument={graphqlDocument} />
			<div
				style={{
					width: `100%`,
					padding: `2px`,
					background: `#f7f7f7`,
				}}
			>
				<QueryEditor
					schema={schema}
					// validationRules={this.props.validationRules}
					value={graphqlDocument.query ?? null}
					onEdit={handleEditQuery}
					// onHintInformationRender={this.handleHintInformationRender}
					// onClickReference={this.handleClickReference}
					// onCopyQuery={this.handleCopyQuery}
					// onPrettifyQuery={this.handlePrettifyQuery}
					// onMergeQuery={this.handleMergeQuery}
					// onRunQuery={this.handleEditorRunQuery}
					// editorTheme={this.props.editorTheme}
					readOnly={false}
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
		</div>
	);
};

export default DocumentTabPane;
