import React, { useState, useRef, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';

/**
 * IbexaDropdown — single-select dropdown following Ibexa design system.
 * Uses a document-level portal so items escape any overflow:hidden ancestors.
 */
export default function IbexaDropdown({ value, options, placeholder = 'Select…', onChange, disabled = false, label, id }) {
    const [expanded, setExpanded] = useState(false);
    const [focused, setFocused] = useState(false);
    const [itemsStyle, setItemsStyle] = useState({});
    const wrapperRef = useRef(null);
    const itemsRef = useRef(null);

    const selectedOption = options.find(o => String(o.value) === String(value ?? ''));

    // Position items below the trigger
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

    const handleSelect = useCallback((val) => {
        onChange(val);
        setExpanded(false);
    }, [onChange]);

    const handleKeyDown = useCallback((e) => {
        if (disabled) return;
        if (e.key === 'Escape') {
            setExpanded(false);
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setExpanded(prev => !prev);
        }
    }, [disabled]);

    const dropdown = (
        <div
            ref={wrapperRef}
            className={`ibexa-dropdown ibexa-dropdown--single ${expanded ? 'ibexa-dropdown--expanded' : ''} ${focused ? 'ibexa-dropdown--focused' : ''} ${disabled ? 'ibexa-dropdown--disabled' : ''}`}
        >
            {label && (
                <label className="ibexa-label" htmlFor={id}>{label}</label>
            )}
            <div className="ibexa-dropdown__wrapper">
                <div
                    className="ibexa-dropdown__selection-info"
                    role="combobox"
                    aria-expanded={expanded}
                    aria-haspopup="listbox"
                    tabIndex={disabled ? -1 : 0}
                    onClick={() => !disabled && setExpanded(prev => !prev)}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    onKeyDown={handleKeyDown}
                >
                    {selectedOption ? (
                        <span className="ibexa-dropdown__selected-item-label">{selectedOption.label}</span>
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
                    style={itemsStyle}
                >
                    <ul className="ibexa-dropdown__items-list">
                        {options.map(opt => {
                            const isSelected = String(opt.value) === String(value ?? '');
                            return (
                                <li
                                    key={opt.value}
                                    className={`ibexa-dropdown__item ${isSelected ? 'ibexa-dropdown__item--selected' : ''}`}
                                    role="option"
                                    aria-selected={isSelected}
                                    onClick={() => handleSelect(opt.value)}
                                >
                                    <span className="ibexa-dropdown__item-check">
                                        {isSelected && (
                                            <svg className="ibexa-icon ibexa-icon--small" aria-hidden="true">
                                                <use xlinkHref="/bundles/ibexaadminui/img/ibexa-icons.svg#checkmark" />
                                            </svg>
                                        )}
                                    </span>
                                    <span>{opt.label}</span>
                                </li>
                            );
                        })}
                    </ul>
                </div>,
                document.body
            )}
        </div>
    );

    return dropdown;
}
