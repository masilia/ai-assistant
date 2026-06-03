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

            <label className="ai-toggle" aria-label={`Toggle ${model.name} active`}>
                <input
                    type="checkbox"
                    checked={model.isActive}
                    onChange={() => onActivate(model.id)}
                />
                <span className="ai-toggle__track" />
            </label>

            <div className="ai-model-row__actions">
                <button type="button" className="ai-icon-btn" onClick={() => onEdit(model)}>
                    Edit
                </button>
                <button type="button" className="ai-icon-btn ai-icon-btn--danger" onClick={() => onDelete(model.id)}>
                    Delete
                </button>
            </div>
        </div>
    );
}
