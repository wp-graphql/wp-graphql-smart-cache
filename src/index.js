import {
    Input,
  } from "antd";

const { hooks, getEndpoint, useAppContext } = wpGraphiQL
const { useState } = wp.element

const SaveButton = props => {
    const { GraphiQL } = props

    const appContext = useAppContext();
    const { query, setQuery, queryParams, externalFragments } = appContext;

    const { index } = props;

    return (
        <>
            <span >
                <Input
                id={`persistedDocument-${index}`}
                name="persisted-document-name"
                data-lpignore="true" // prevents last pass extension from trying to autofill
                defaultValue={props.name ?? ""}
                placeholder={props.name ?? "Document name"}
                onChange={(e) => {
                    console.log(e.target.value)
                    if (props.name === e.target.value) {
                        console.log("same ${e.target.value}")
                    return;
                    }

                    // Rename the document
                    //_onOperationRename(e);
                    props.name = e.target.value
                }}
                value={props.name}
                style={{
                    color: `green`,
                    width: `40ch`,
                    fontSize: `smaller`,
                }}
                />
            </span>
        <GraphiQL.Button
            label={`Save As ...`}
            onClick={() => {
                var payload = {
                    "query": appContext.query
                }
                if ( props.name ) {
                    payload['queryId'] = props.name
                }
                console.log("Payload: ", props, payload)
                var post = fetch(
                    appContext.endpoint,
                    {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(payload)
                    }
                ).then( response => {
                    if( 500 == response.status ) {
                        console.log(response)
                        throw response.json()
                    }
                    return response.json()
                }).catch( response => {
                    return response
                })
                console.log(post)
            }}
        />
        </>
    )
}

hooks.addFilter( 'graphiql_toolbar_after_buttons', 'graphiql-persisted-queries', (res, props) => {
    res.push(
        <SaveButton {...props} />
    )
    return res
})
