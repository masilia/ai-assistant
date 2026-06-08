import React from 'react';
import { CloseIcon } from './icons.jsx';

export default function ConfirmModal({ title, description, confirmLabel = 'Confirm', onConfirm, onCancel }) {
    return (
        <div className="ai-confirm-overlay" onClick={onCancel}>
            <div
                className="ibexa-modal ai-confirm-modal"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
            >
                <div className="modal-dialog modal-sm">
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title" id="confirm-title">{title}</h5>
                            <button
                                className="close ibexa-btn ibexa-btn--ghost ibexa-btn--no-text ibexa-btn--small"
                                onClick={onCancel}
                                type="button"
                                aria-label="Close"
                            >
                                <CloseIcon size="small" />
                            </button>
                        </div>
                        <div className="modal-body">
                            <div className="ai-confirm-modal__icon" aria-hidden="true">⚠️</div>
                            <p className="ai-confirm-modal__desc">{description}</p>
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="ibexa-btn ibexa-btn--tertiary" onClick={onCancel}>
                                Cancel
                            </button>
                            <button type="button" className="ibexa-btn ibexa-btn--primary ai-btn--danger" onClick={onConfirm}>
                                {confirmLabel}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
