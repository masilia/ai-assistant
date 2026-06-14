import React from 'react';

/**
 * Confirmation dialog for agent actions.
 *
 * @param {{ message: string, onConfirm: () => void, onCancel: () => void }} props
 */
function ActionConfirmDialog({ message, onConfirm, onCancel }) {
    return (
        <div className="agent-chat__confirm-overlay">
            <div className="agent-chat__confirm-dialog">
                <div className="agent-chat__confirm-message">{message}</div>
                <div className="agent-chat__confirm-actions">
                    <button
                        type="button"
                        className="agent-chat__confirm-yes"
                        onClick={onConfirm}
                    >
                        Yes, proceed
                    </button>
                    <button
                        type="button"
                        className="agent-chat__confirm-no"
                        onClick={onCancel}
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
}

export default ActionConfirmDialog;
