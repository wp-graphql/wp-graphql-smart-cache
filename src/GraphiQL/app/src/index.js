import { LineChartOutlined } from '@ant-design/icons';
const { hooks } = window.wpGraphiQL;

/**
 * Hook into GraphiQL to render the persisted queries document editor screen
 */
hooks.addFilter( 'graphiql_router_screens', 'graphiql-persisted-queries', (screens) => {
    screens.push({
        id: 'graphiql-persisted-queries',
        title: 'Persisted Queries',
        icon: <LineChartOutlined />,
        render: () => <h2>Persisted Queries...</h2>
    });
    return screens;
})