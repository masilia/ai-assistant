import React from 'react';

export default function ModelCard({ model, onActivate, onEdit, onDelete }) {
    return (
        <div className={`ai-model-row ${model.isActive ? 'ai-model-row--active' : ''}`}>
            <div className="ai-model-row__info">
                <div className="ai-model-row__name">{model.name}</div>
                <div className="ai-model-row__details">
                    <code>{model.identifier}</code>
                    <span>Temp: {model.temperature}</span>
                    <span>Tokens: {model.maxTokens}</span>
                </div>
            </div>

            <div
                className={`ibexa-toggle ibexa-toggle--small ${model.isActive ? 'ibexa-toggle--is-checked' : ''}`}
                onClick={() => onActivate(model.id)}
            >
                <label className="ibexa-toggle__switcher" aria-label={`Toggle ${model.name} active`}>
                    <input
                        className="ibexa-toggle__input"
                        type="checkbox"
                        checked={model.isActive}
                        onChange={() => onActivate(model.id)}
                    />
                    <span className="ibexa-toggle__indicator" />
                </label>
            </div>

            <div className="ai-model-row__actions">
                <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text" onClick={() => onEdit(model)} title="Edit">
                    <svg className="ibexa-icon ibexa-icon--small">
                        <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#edit" />
                    </svg>
                </button>
                <button type="button" className="ibexa-btn ibexa-btn--ghost ibexa-btn--small ibexa-btn--no-text ai-btn--danger" onClick={() => onDelete(model.id)} title="Delete">
                    <svg className="ibexa-icon ibexa-icon--small">
                        <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#trash" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
