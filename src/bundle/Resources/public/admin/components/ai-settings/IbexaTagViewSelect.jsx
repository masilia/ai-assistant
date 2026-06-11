import React, { useState, useRef, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';

/**
 * IbexaTagViewSelect — multi-select dropdown with removable tags,
 * following Ibexa's `.ibexa-tag-view-select` design pattern.
 * Uses a document-level portal so items escape any overflow:hidden ancestors.
 */
export default function IbexaTagViewSelect({ values = [], options, placeholder = 'Select…', onChange, disabled = false, label, id }) {
    const [expanded, setExpanded] = useState(false);
    const [focused, setFocused] = useState(false);
    const [itemsStyle, setItemsStyle] = useState({});
    const wrapperRef = useRef(null);
    const itemsRef = useRef(null);

    const positionItems = useCallback(() => {
        if (!wrapperRef.current) return;
        const rect = wrapperRef.current.getBoundingClientRect();
        setItemsStyle({
            position: 'fixed',
            top: rect.bottom + 2,
            left: rect.left,
            width: rect.width,
            zIndex: 10500,
        });
    }, []);

    useEffect(() => {
        if (!expanded) return;
        positionItems();
        const handleClick = (e) => {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target)
                && itemsRef.current && !itemsRef.current.contains(e.target)) {
                setExpanded(false);
            }
        };
        const handleScroll = () => positionItems();
        document.addEventListener('mousedown', handleClick);
        window.addEventListener('scroll', handleScroll, true);
        window.addEventListener('resize', handleScroll);
        return () => {
            document.removeEventListener('mousedown', handleClick);
            window.removeEventListener('scroll', handleScroll, true);
            window.removeEventListener('resize', handleScroll);
        };
    }, [expanded, positionItems]);

    const handleToggle = useCallback((val) => {
        const next = values.includes(val)
            ? values.filter(v => v !== val)
            : [...values, val];
        onChange(next);
    }, [values, onChange]);

    const handleRemove = useCallback((val, e) => {
        e.stopPropagation();
        onChange(values.filter(v => v !== val));
    }, [values, onChange]);

    const handleKeyDown = useCallback((e) => {
        if (disabled) return;
        if (e.key === 'Escape') {
            setExpanded(false);
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setExpanded(prev => !prev);
        }
    }, [disabled]);

    const selectedLabels = options.filter(o => values.includes(o.value));

    const dropdown = (
        <div
            ref={wrapperRef}
            className={`ibexa-dropdown ibexa-dropdown--multi ${expanded ? 'ibexa-dropdown--expanded' : ''} ${focused ? 'ibexa-dropdown--focused' : ''} ${disabled ? 'ibexa-dropdown--disabled' : ''}`}
        >
            {label && (
                <label className="ibexa-label" htmlFor={id}>{label}</label>
            )}
            <div className="ibexa-dropdown__wrapper">
                <div
                    className="ibexa-dropdown__selection-info"
                    role="listbox"
                    aria-expanded={expanded}
                    aria-haspopup="listbox"
                    tabIndex={disabled ? -1 : 0}
                    onClick={() => !disabled && setExpanded(prev => !prev)}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    onKeyDown={handleKeyDown}
                >
                    {selectedLabels.length > 0 ? (
                        selectedLabels.map(opt => (
                            <span key={opt.value} className="ibexa-dropdown__selected-item ibexa-tag ibexa-tag--deletable ibexa-tag--secondary">
                                <span className="ibexa-tag__content">{opt.label}</span>
                                <button
                                    type="button"
                                    className="ibexa-tag__remove-btn"
                                    aria-label={`Remove ${opt.label}`}
                                    onClick={(e) => handleRemove(opt.value, e)}
                                >
                                    <svg className="ibexa-icon ibexa-icon--tiny" aria-hidden="true">
                                        <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#discard" />
                                    </svg>
                                </button>
                            </span>
                        ))
                    ) : (
                        <span className="ibexa-dropdown__selected-placeholder">{placeholder}</span>
                    )}
                </div>
            </div>
            {expanded && createPortal(
                <div
                    ref={itemsRef}
                    className="ibexa-dropdown__items"
                    role="listbox"
                    aria-multiselectable="true"
                    style={itemsStyle}
                >
                    <ul className="ibexa-dropdown__items-list">
                        {options.map(opt => (
                            <li
                                key={opt.value}
                                className={`ibexa-dropdown__item ${values.includes(opt.value) ? 'ibexa-dropdown__item--selected' : ''}`}
                                role="option"
                                aria-selected={values.includes(opt.value)}
                                onClick={() => handleToggle(opt.value)}
                            >
                                <span className="ibexa-dropdown__item-check">
                                    {values.includes(opt.value) && (
                                        <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                                            <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#checkmark" />
                                        </svg>
                                    )}
                                </span>
                                <span>{opt.label}</span>
                            </li>
                        ))}
                    </ul>
                </div>,
                document.body
            )}
        </div>
    );

    return dropdown;
}
