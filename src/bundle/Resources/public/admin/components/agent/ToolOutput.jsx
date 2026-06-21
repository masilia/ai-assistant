import React from 'react';

/**
 * Renders structured tool output (search results, content details, folder, plan, etc.).
 *
 * @param {{ output: object, toolName: string, stepIndex?: number, totalSteps?: number, loading?: boolean, progressMessage?: string }} props
 */
function ToolOutput({ output, toolName, stepIndex, totalSteps, loading, progressMessage }) {
    if (!output && !loading) return null;

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
                        <span className="agent-chat__tool-output-label">Content ID</span>
                        <span>{data.content_id}</span>
                    </div>
                )}
                {data.remote_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Remote ID</span>
                        <span className="agent-chat__tool-output-code">{data.remote_id}</span>
                    </div>
                )}
                {data.location_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Location ID</span>
                        <span>{data.location_id}</span>
                    </div>
                )}
                {data.name && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Name</span>
                        <span>{data.name}</span>
                    </div>
                )}
                {data.content_type && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Type</span>
                        <span>{data.content_type}</span>
                    </div>
                )}
                {data.version_no && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Version</span>
                        <span>{data.version_no}</span>
                    </div>
                )}
            </div>
        );
    };

    const renderFolderResult = (data) => {
        return (
            <div className="agent-chat__tool-output-card">
                <div className="agent-chat__tool-output-field">
                    <span className="agent-chat__tool-output-label">Folder</span>
                    <span>{data.name || data.remote_id || 'Created'}</span>
                </div>
                {data.location_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Location ID</span>
                        <span>{data.location_id}</span>
                    </div>
                )}
                {data.content_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Content ID</span>
                        <span>{data.content_id}</span>
                    </div>
                )}
            </div>
        );
    };

    const renderPlanResult = (data) => {
        const steps = data.steps || data.toolCalls || [];
        return (
            <div className="agent-chat__tool-output-card">
                {data.siteaccess && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Site</span>
                        <span>{data.siteaccess}</span>
                    </div>
                )}
                {data.parent_location_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Parent</span>
                        <span>{data.parent_location_id}</span>
                    </div>
                )}
                {steps.length > 0 && (
                    <div className="agent-chat__tool-output-steps">
                        <span className="agent-chat__tool-output-label">{steps.length} step{steps.length !== 1 ? 's' : ''}</span>
                        {steps.map((step, i) => (
                            <div key={i} className="agent-chat__tool-output-step">
                                <span className="agent-chat__tool-output-step-num">{i + 1}</span>
                                <span>{step.name || step.tool || 'step'}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    const renderImageResult = (data) => {
        const imageUrl = data.url || data.image_url || data.imageUri;
        return (
            <div className="agent-chat__tool-output-card">
                {imageUrl && (
                    <div className="agent-chat__tool-output-image">
                        <img src={imageUrl} alt="Generated image" loading="lazy" />
                    </div>
                )}
                {data.content_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Content ID</span>
                        <span>{data.content_id}</span>
                    </div>
                )}
                {data.remote_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Remote ID</span>
                        <span className="agent-chat__tool-output-code">{data.remote_id}</span>
                    </div>
                )}
                {!imageUrl && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Image</span>
                        <span>Generated</span>
                    </div>
                )}
            </div>
        );
    };

    const renderExploreSite = (data) => {
        const siteaccesses = data.siteaccesses || [];
        return (
            <div className="agent-chat__tool-output-card">
                {siteaccesses.length > 0 && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Siteaccesses</span>
                        <span>{siteaccesses.length} found</span>
                    </div>
                )}
                {data.root_location_id && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Root Location</span>
                        <span>{data.root_location_id}</span>
                    </div>
                )}
                {data.matched_siteaccess && (
                    <div className="agent-chat__tool-output-field">
                        <span className="agent-chat__tool-output-label">Matched</span>
                        <span className="agent-chat__tool-output-code">{data.matched_siteaccess}</span>
                    </div>
                )}
                {siteaccesses.length > 0 && (
                    <div className="agent-chat__tool-output-steps">
                        {siteaccesses.map((sa, i) => (
                            <div key={i} className="agent-chat__tool-output-step">
                                <span className="agent-chat__tool-output-step-num">{i + 1}</span>
                                <span>{sa}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    const renderDefault = (data) => {
        // Try to extract a meaningful summary
        if (data.message) {
            return (
                <div className="agent-chat__tool-output-card">
                    <div className="agent-chat__tool-output-field">
                        <span>{data.message}</span>
                    </div>
                </div>
            );
        }

        return (
            <pre className="agent-chat__tool-output-json">
                {JSON.stringify(data, null, 2)}
            </pre>
        );
    };

    const renderOutput = () => {
        if (toolName === 'explore_site') {
            return renderExploreSite(output);
        }

        if (toolName === 'search_content') {
            return renderSearchResults(output);
        }

        if (['create_content', 'update_content', 'load_content'].includes(toolName)) {
            return renderContentResult(output);
        }

        if (toolName === 'create_folder') {
            return renderFolderResult(output);
        }

        if (toolName === 'propose_plan') {
            return renderPlanResult(output);
        }

        if (['create_image', 'generate_image'].includes(toolName)) {
            return renderImageResult(output);
        }

        if (['trash_content', 'restore_content'].includes(toolName)) {
            return renderContentResult(output);
        }

        return renderDefault(output);
    };

    const isMultiStep = totalSteps != null && totalSteps > 1;

    return (
        <div className={`agent-chat__tool-output${isMultiStep ? ' agent-chat__tool-output--stepped' : ''}${loading ? ' agent-chat__tool-output--loading' : ''}`}>
            <div className="agent-chat__tool-output-header-row">
                {isMultiStep && (
                    <span className="agent-chat__tool-output-step-badge">
                        {stepIndex + 1}/{totalSteps}
                    </span>
                )}
                <span className="agent-chat__tool-output-label">{formatToolName(toolName)}</span>
                {loading && (
                    <span className="agent-chat__tool-output-spinner">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                        </svg>
                    </span>
                )}
            </div>
            {loading ? (
                <div className="agent-chat__tool-output-loading">
                    {progressMessage || 'Processing...'}
                </div>
            ) : (
                renderOutput()
            )}
        </div>
    );
}

function formatToolName(name) {
    if (!name) return '';
    return name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default ToolOutput;
