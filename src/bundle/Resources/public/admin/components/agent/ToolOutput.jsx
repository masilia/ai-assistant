import React from 'react';

/**
 * Renders structured tool output (search results, content details, etc.).
 *
 * @param {{ output: object, toolName: string }} props
 */
function ToolOutput({ output, toolName }) {
    if (!output) return null;

    const renderSearchResults = (data) => {
        if (!data.results || !Array.isArray(data.results)) return null;

        return (
            <div className="agent-chat__tool-output-table">
                <div className="agent-chat__tool-output-header">
                    <span>{data.count} results found</span>
                </div>
                <table className="agent-chat__tool-output-list">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Remote ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.results.map((item, index) => (
                            <tr key={index}>
                                <td>{item.content_id}</td>
                                <td>{item.name}</td>
                                <td>{item.content_type}</td>
                                <td className="agent-chat__tool-output-code">{item.remote_id}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    };

    const renderContentResult = (data) => {
        return (
            <div className="agent-chat__tool-output-card">
                {data.content_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Content ID:</span>
                        <span>{data.content_id}</span>
                    </div>
                )}
                {data.remote_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Remote ID:</span>
                        <span className="agent-chat__tool-output-code">{data.remote_id}</span>
                    </div>
                )}
                {data.location_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Location ID:</span>
                        <span>{data.location_id}</span>
                    </div>
                )}
                {data.name && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Name:</span>
                        <span>{data.name}</span>
                    </div>
                )}
            </div>
        );
    };

    const renderDefault = (data) => {
        return (
            <pre className="agent-chat__tool-output-json">
                {JSON.stringify(data, null, 2)}
            </pre>
        );
    };

    const renderOutput = () => {
        if (toolName === 'search_content') {
            return renderSearchResults(output);
        }

        if (['create_content', 'update_content', 'load_content'].includes(toolName)) {
            return renderContentResult(output);
        }

        return renderDefault(output);
    };

    return (
        <div className="agent-chat__tool-output">
            <div className="agent-chat__tool-output-label">{toolName}</div>
            {renderOutput()}
        </div>
    );
}

export default ToolOutput;
