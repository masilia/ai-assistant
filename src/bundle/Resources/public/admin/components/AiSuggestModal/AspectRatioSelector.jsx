import React from 'react';

const ASPECT_RATIOS = [
    { value: '1:1',  label: '1:1' },
    { value: '16:9', label: '16:9' },
    { value: '9:16', label: '9:16' },
    { value: '4:3',  label: '4:3' },
    { value: '3:4',  label: '3:4' },
];

export default function AspectRatioSelector({ visible, onSelect }) {
    if (!visible) return null;

    return (
        <div className="ai-suggest-modal__aspect-ratio">
            <span className="ai-suggest-modal__aspect-ratio-label">Pick a size to generate:</span>
            <div className="ai-suggest-modal__aspect-ratio-options">
                {ASPECT_RATIOS.map((ratio) => (
                    <button
                        key={ratio.value}
                        type="button"
                        className="ai-suggest-modal__aspect-ratio-btn"
                        onClick={() => onSelect(ratio.value)}
                    >
                        {ratio.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
