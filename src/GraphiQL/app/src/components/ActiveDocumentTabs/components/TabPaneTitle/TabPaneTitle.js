import { Badge, Dropdown, Menu, Button, Space } from "antd";
import { MoreOutlined } from "@ant-design/icons";

// const TabPaneTitleMenu = ({document}) => {
//     return (
//         <Menu>
//             <Menu.Item>
//                 <a target="_blank" rel="noopener noreferrer" href="https://www.antgroup.com">
//                 1st menu item
//                 </a>
//             </Menu.Item>
//             <Menu.Item>
//                 <a target="_blank" rel="noopener noreferrer" href="https://www.aliyun.com">
//                 2nd menu item
//                 </a>
//             </Menu.Item>
//             <Menu.Item>
//                 <a target="_blank" rel="noopener noreferrer" href="https://www.luohanacademy.com">
//                 3rd menu item
//                 </a>
//             </Menu.Item>
//         </Menu>
//     )
// }

// const TabPaneTitleMenuDropdown = ({ document }) => {
//     return (
//         <Dropdown overlay={<TabPaneTitleMenu document={document} />} >
//             <MoreOutlined />
//         </Dropdown>
//     )
// }

const TabPaneTitle = ({ document }) => {
	const { isDirty } = document;

	return (
		<>
			<Badge
				status={isDirty ? "warning" : "success"}
				text={
					<Space>
						<span>{document.title}</span>
					</Space>
				}
			/>
		</>
	);
};

export default TabPaneTitle;
