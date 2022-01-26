import { useQuery, gql } from "@apollo/client";
import { useState } from "@wordpress/element";
import { List, Avatar, Skeleton, Spin } from "antd";
import { useDocumentEditorContext } from "../../context/DocumentEditorContext";
import styled from "styled-components";
import InfiniteScroll from "react-infinite-scroll-component";
const { GraphQL } = window.wpGraphiQL;
const { parse } = GraphQL;

export const GET_DOCUMENTS = gql`
	query GetDocuments($first: Int, $after: String) {
		graphqlDocuments(
			first: $first
			after: $after
			where: { stati: [PUBLISH, DRAFT] }
		) {
			pageInfo {
				hasNextPage
				endCursor
			}
			nodes {
				id
				title
				content
				query: content(format: RAW)
				status
				description
			}
		}
	}
`;

const StyledDocumentFinderWrapper = styled.div`
	padding: 0px;
	.ant-list-item {
		margin: 0;
		padding: 10px;
	}
	.ant-list-item.active {
		background: aliceblue;
	}
`;

const getOperationTypes = (queryDocument) => {
	try {
		const parsed = parse(queryDocument);
		return parsed.definitions.map((definition) => {
			return definition.operation;
		});
	} catch (e) {
		console.error(e);
		return [];
	}
};

const DocumentFinder = (props) => {
	const [listLength, setListLength] = useState(0);

	const { data, loading, error, fetchMore } = useQuery(GET_DOCUMENTS, {
		variables: {
			first: 20,
			after: null,
		},
		onCompleted: (data) => {
			setListLength(data.graphqlDocuments.nodes.length ?? 0);
		},
	});

	const { openDocument, activeDocumentId } = useDocumentEditorContext();

	if (loading || !data || !data.graphqlDocuments) {
		return <Spin />;
	}

	if (error) {
		return <div>Error!</div>;
	}

	const {
		graphqlDocuments: {
			pageInfo: { hasNextPage, endCursor },
		},
	} = data;

	return (
		<StyledDocumentFinderWrapper id="document-finder-list">
			<InfiniteScroll
				data={data}
				dataLength={listLength}
				next={async () => {
					if (!hasNextPage || !endCursor) {
						return;
					}

					await fetchMore({
						variables: {
							first: 20,
							after: endCursor ?? null,
						},
						updateQuery: (prev, { fetchMoreResult }) => {
							// Merge the previous nodes with the new nodes
							const mergedNodes = [
								...prev.graphqlDocuments.nodes,
								...fetchMoreResult.graphqlDocuments.nodes,
							];

							setListLength(mergedNodes.length);

							// Update the query with the new data
							const newData = {
								...fetchMoreResult,
								graphqlDocuments: {
									...fetchMoreResult.graphqlDocuments,
									nodes: mergedNodes,
								},
							};

							console.log({ newData });

							return newData;
						},
					});
				}}
				hasMore={data?.graphqlDocuments.pageInfo.hasNextPage ?? false}
				loader={<Skeleton avatar paragraph={{ rows: 1 }} active />}
				endMessage={
					<p style={{ textAlign: "center" }}>
						There are no more documents.
					</p>
				}
				scrollableTarget="document-finder-sider"
			>
				<List
					dataSource={data?.graphqlDocuments?.nodes ?? []}
					renderItem={(document) => {
						// get a list of the operation types within the document
						const operationTypes = getOperationTypes(
							document?.query
						);

						return (
							<List.Item
								id={document.id}
								onClick={() => {
									openDocument(document);
								}}
								className={
									document.id === activeDocumentId
										? "active"
										: ""
								}
							>
								<List.Item.Meta
									avatar={
										<Avatar.Group
											maxCount={2}
											maxStyle={{
												color: "#f56a00",
												backgroundColor: "#fde3cf",
											}}
										>
											{operationTypes.map(
												(operationType) => {
													return (
														<Avatar
															title={`Document Type: ${operationType}`}
															style={{
																color: "#fff",
																backgroundColor:
																	operationType ===
																	"subscription"
																		? "#87d068"
																		: operationType ===
																		  "mutation"
																		? "#f56a00"
																		: "#1890ff",
															}}
															shape="circle"
														>
															{operationType ===
															"subscription"
																? "S"
																: operationType ===
																  "mutation"
																? "M"
																: "Q"}
														</Avatar>
													);
												}
											)}
										</Avatar.Group>
									}
									title={document.title}
								/>
							</List.Item>
						);
					}}
				/>
			</InfiniteScroll>
		</StyledDocumentFinderWrapper>
	);
};

export default DocumentFinder;
