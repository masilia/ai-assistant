import React from 'react';

export default function ConfirmModal({ title, description, confirmLabel = 'Confirm', onConfirm, onCancel }) {
    return (
        <div className="ai-confirm-overlay" onClick={onCancel} role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <div className="ai-confirm" onClick={(e) => e.stopPropagation()}>
                <div className="ai-confirm__icon" aria-hidden="true">⚠️</div>
                <h3 className="ai-confirm__title" id="confirm-title">{title}</h3>
                <p className="ai-confirm__desc">{description}</p>
                <div className="ai-confirm__actions">
                    <button type="button" className="ai-confirm__btn ai-confirm__btn--cancel" onClick={onCancel}>
                        Cancel
                    </button>
                    <button type="button" className="ai-confirm__btn ai-confirm__btn--danger" onClick={onConfirm}>
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
