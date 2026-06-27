import React, { useState, useCallback } from 'react';
import { PROVIDER_TYPES, getProviderLabel } from './constants.js';
import { BrainIcon, CheckIcon, ArrowRightIcon } from './icons.jsx';

/**
 * 3-step onboarding wizard shown when no providers are configured.
 * Guides the user through: choose provider → enter API key → assign siteaccess.
 * Calls onAddProvider with a partial provider object to pre-fill the drawer.
 */
export default function OnboardingWizard({ onAddProvider, siteaccesses = [], currentSiteaccess = '' }) {
    const [step, setStep] = useState(0);
    const [providerType, setProviderType] = useState('');
    const [apiKey, setApiKey] = useState('');
    const [siteaccess, setSiteaccess] = useState(currentSiteaccess);

    const handleFinish = useCallback(() => {
        onAddProvider({
            identifier: providerType,
            apiKey,
            siteaccesses: siteaccess ? [siteaccess] : [],
        });
    }, [onAddProvider, providerType, apiKey, siteaccess]);

    const steps = ['Choose Provider', 'Enter API Key', 'Assign Siteaccess'];
    const canProceed = step === 0 ? !!providerType : step === 1 ? !!apiKey.trim() : true;

    return (
        <div className="ai-onboarding">
            <div className="ai-onboarding__hero">
                <div className="ai-onboarding__hero-icon" aria-hidden="true">
                    <BrainIcon size={40} />
                </div>
                <h2 className="ai-onboarding__title">Welcome to AI Assistant</h2>
                <p className="ai-onboarding__subtitle">
                    Set up your first AI provider in 3 quick steps.
                </p>
            </div>

            <div className="ai-onboarding__steps">
                {steps.map((label, i) => (
                    <div
                        key={i}
                        className={`ai-onboarding__step-indicator ${i === step ? 'ai-onboarding__step-indicator--active' : ''} ${i < step ? 'ai-onboarding__step-indicator--done' : ''}`}
                    >
                        <span className="ai-onboarding__step-number">
                            {i < step ? <CheckIcon size={14} /> : i + 1}
                        </span>
                        <span className="ai-onboarding__step-label">{label}</span>
                    </div>
                ))}
            </div>

            <div className="ai-onboarding__body">
                {step === 0 && (
                    <div className="ai-onboarding__options">
                        {PROVIDER_TYPES.map(pt => (
                            <button
                                key={pt.value}
                                type="button"
                                className={`ai-onboarding__provider-option ${providerType === pt.value ? 'ai-onboarding__provider-option--selected' : ''}`}
                                onClick={() => setProviderType(pt.value)}
                            >
                                <span className="ai-onboarding__provider-name">{pt.label}</span>
                                <span className="ai-onboarding__provider-check">
                                    {providerType === pt.value ? <CheckIcon size={16} /> : null}
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                {step === 1 && (
                    <div className="ai-onboarding__field">
                        <label className="ibexa-label" htmlFor="onboarding-api-key">
                            API Key for {getProviderLabel(providerType)}
                        </label>
                        <input
                            id="onboarding-api-key"
                            type="password"
                            className="ibexa-input form-control"
                            value={apiKey}
                            onChange={(e) => setApiKey(e.target.value)}
                            placeholder="Paste your API key..."
                            autoFocus
                        />
                        {providerType === 'ollama' && (
                            <p className="ai-onboarding__hint">
                                Ollama runs locally — enter any placeholder if no key is required.
                            </p>
                        )}
                    </div>
                )}

                {step === 2 && (
                    <div className="ai-onboarding__field">
                        <label className="ibexa-label" htmlFor="onboarding-siteaccess">
                            Assign to siteaccess (optional)
                        </label>
                        <select
                            id="onboarding-siteaccess"
                            className="ibexa-input form-control"
                            value={siteaccess}
                            onChange={(e) => setSiteaccess(e.target.value)}
                        >
                            <option value="">— Not now —</option>
                            {siteaccesses.map(sa => (
                                <option key={sa} value={sa}>{sa}</option>
                            ))}
                        </select>
                        <p className="ai-onboarding__hint">
                            You can change this later from the provider card.
                        </p>
                    </div>
                )}
            </div>

            <div className="ai-onboarding__footer">
                {step > 0 && (
                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--tertiary"
                        onClick={() => setStep(s => s - 1)}
                    >
                        Back
                    </button>
                )}
                {step < 2 ? (
                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--primary"
                        disabled={!canProceed}
                        onClick={() => setStep(s => s + 1)}
                    >
                        Next <ArrowRightIcon size={14} />
                    </button>
                ) : (
                    <button
                        type="button"
                        className="ibexa-btn ibexa-btn--primary"
                        onClick={handleFinish}
                    >
                        Create Provider
                    </button>
                )}
            </div>
        </div>
    );
}
