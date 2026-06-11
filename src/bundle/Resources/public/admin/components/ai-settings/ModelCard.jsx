import React from 'react';

export default function ModelCard({ model, isChatActive, isImageActive, onEdit, onDelete }) {
    return (
        <div className={`ai-model-row ${isChatActive ? 'ai-model-row--active-chat' : ''} ${isImageActive ? 'ai-model-row--active-image' : ''}`}>
            <div className="ai-model-row__info">
                <div className="ai-model-row__name">{model.name}</div>
                <div className="ai-model-row__details">
                    <code>{model.identifier}</code>
                    <span>Temp: {model.temperature}</span>
                    <span>Tokens: {model.maxTokens}</span>
                </div>
                <div className="ai-model-row__badges">
                    {isChatActive && <span className="ai-model-row__badge ai-model-row__badge--chat">Chat</span>}
                    {isImageActive && <span className="ai-model-row__badge ai-model-row__badge--image">Image</span>}
                    {model.supportsImage && !isImageActive && <span className="ai-model-row__badge ai-model-row__badge--image">Image Capable</span>}
                </div>
            </div>

            <div className="ai-model-row__actions">
                <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text" onClick={() => onEdit(model)} title={`Edit ${model.name}`} aria-label={`Edit ${model.name}`}>
                    <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                        <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#edit" />
                    </svg>
                </button>
                <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text ai-btn--danger" onClick={() => onDelete(model.id)} title={`Delete ${model.name}`} aria-label={`Delete ${model.name}`}>
                    <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                        <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#trash" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
