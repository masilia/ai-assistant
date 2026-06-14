import React from 'react';

/**
 * Renders an execution plan as a visual tree.
 *
 * @param {{ plan: { description: string, steps: Array<{tool: string, params: object}> }, onConfirm: () => void, onCancel: () => void }} props
 */
function PlanDisplay({ plan, onConfirm, onCancel }) {
    if (!plan || !plan.steps || plan.steps.length === 0) {
        return null;
    }

    const formatToolName = (tool) => {
        return tool.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    };

    const formatParams = (params) => {
        if (!params || typeof params !== 'object') return null;

        return Object.entries(params).map(([key, value]) => {
            if (value === null || value === undefined) return null;

            const displayValue = typeof value === 'object'
                ? JSON.stringify(value)
                : String(value);

            return (
                <div key={key} className="agent-chat__plan-param">
                    <span className="agent-chat__plan-param-key">{key}:</span>
                    <span className="agent-chat__plan-param-value">{displayValue}</span>
                </div>
            );
        });
    };

    return (
        <div className="agent-chat__plan">
            <div className="agent-chat__plan-header">
                <span className="agent-chat__plan-title">Execution Plan</span>
                {plan.description && (
                    <span className="agent-chat__plan-desc">{plan.description}</span>
                )}
            </div>
            <div className="agent-chat__plan-steps">
                {plan.steps.map((step, index) => (
                    <div key={index} className="agent-chat__plan-step">
                        <div className="agent-chat__plan-step-header">
                            <span className="agent-chat__plan-step-num">{index + 1}</span>
                            <span className="agent-chat__plan-step-tool">{formatToolName(step.tool)}</span>
                        </div>
                        <div className="agent-chat__plan-step-params">
                            {formatParams(step.params)}
                        </div>
                    </div>
                ))}
            </div>
            <div className="agent-chat__plan-actions">
                <button
                    type="button"
                    className="agent-chat__plan-confirm"
                    onClick={onConfirm}
                >
                    Execute Plan
                </button>
                <button
                    type="button"
                    className="agent-chat__plan-cancel"
                    onClick={onCancel}
                >
                    Cancel
                </button>
            </div>
        </div>
    );
}

export default PlanDisplay;
