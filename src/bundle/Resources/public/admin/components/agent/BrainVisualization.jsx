import React, { useState, useEffect, useMemo, useRef, useCallback } from 'react';

/**
 * Tool names → workflow phase mapping.
 * Multiple tools can map to the same phase.
 * ask_user maps to 'waiting' — a distinct state that pauses the agent.
 */
const TOOL_TO_PHASE = {
    explore_site: 'searching',
    propose_plan: 'validating',
    create_content: 'generating',
    update_content: 'generating',
    create_folder: 'generating',
    trash_content: 'generating',
    restore_content: 'generating',
    ask_user: 'waiting',
};

/**
 * Phase definitions: order, labels, icons.
 */
const PHASES = [
    { id: 'understanding', label: 'Understanding Request' },
    { id: 'searching', label: 'Searching Sources' },
    { id: 'validating', label: 'Validating Results' },
    { id: 'generating', label: 'Generating Output' },
    { id: 'waiting', label: 'Waiting for Input' },
];

/** SVG icons */
const ICON_CHECK = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <path d="M20 6 9 17l-5-5" />
    </svg>
);

const ICON_SPINNER = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v4" />
        <path d="m16.2 7.8 2.9-2.9" />
        <path d="M20 12h-4" />
        <path d="m16.2 16.2 2.9 2.9" />
        <path d="M12 20v-4" />
        <path d="m7.8 16.2-2.9 2.9" />
        <path d="M4 12h4" />
        <path d="m7.8 7.8-2.9-2.9" />
    </svg>
);

const ICON_PENDING = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10" />
    </svg>
);

const ICON_ERROR = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4" />
        <path d="M12 16h.01" />
    </svg>
);

const ICON_WAITING = (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 6v6l4 2" />
    </svg>
);

/** Colors matching SCSS */
const CYAN = '#00e5ff';
const VIOLET = '#7c4dff';

/**
 * Swarm Worker Visualization.
 *
 * Vertical checklist of 4 workflow phases.
 * SVG particles flow between active steps.
 * Completed steps slide out and hide.
 * After all done, shows summary then exits.
 *
 * @param {{
 *   steps: Array<{ tool: string, call_id: string, loading: boolean, result?: object }>,
 *   message: string,
 *   isComplete: boolean,
 *   onExit: () => void
 * }} props
 */
function BrainVisualization({ steps, message, isComplete, onExit }) {
    const [phase, setPhase] = useState('active');
    const [particles, setParticles] = useState([]);
    const particleIdRef = useRef(0);
    const animFrameRef = useRef(null);
    const stepRefsMap = useRef({});
    const containerRef = useRef(null);

    // Map raw steps to the 5 workflow phases
    const workflowPhases = useMemo(() => {
        // status: pending | active | complete | error | waiting
        const result = PHASES.map((p) => ({
            ...p,
            status: 'pending',
            toolCalls: [],
            progressMessage: null,
        }));

        const phaseIndex = {};
        PHASES.forEach((p, i) => { phaseIndex[p.id] = i; });

        let activePhaseFound = false;

        for (const step of steps) {
            const phaseId = TOOL_TO_PHASE[step.tool] ?? 'generating';
            const idx = phaseIndex[phaseId];
            if (idx === undefined) continue;

            result[idx].toolCalls.push(step);

            if (step.loading) {
                result[idx].status = phaseId === 'waiting' ? 'waiting' : 'active';
                if (step.progressMessage) {
                    result[idx].progressMessage = step.progressMessage;
                }
                activePhaseFound = true;
                for (let j = 0; j < idx; j++) {
                    if (result[j].status === 'pending') {
                        result[j].status = 'complete';
                    }
                }
            } else if (step.result) {
                // Respect success flag from step_result event
                const succeeded = step.result.success !== false;
                result[idx].status = succeeded ? 'complete' : 'error';
                result[idx].progressMessage = null;
                for (let j = 0; j < idx; j++) {
                    if (result[j].status === 'pending') {
                        result[j].status = 'complete';
                    }
                }
            }
        }

        if (!activePhaseFound && steps.length === 0) {
            result[0].status = 'active';
        } else if (!activePhaseFound && steps.length > 0) {
            const allSettled = steps.every((s) => s.result);
            if (allSettled) {
                result.forEach((p) => {
                    if (p.status === 'pending') p.status = 'complete';
                });
            } else {
                result[0].status = 'active';
            }
        }

        return result;
    }, [steps]);

    // Active phase index (-1 if none)
    const activeIdx = useMemo(() =>
        workflowPhases.findIndex((p) => p.status === 'active'),
        [workflowPhases],
    );

    const completedCount = workflowPhases.filter((p) => p.status === 'complete').length;

    // Phase transitions
    useEffect(() => {
        if (isComplete && phase === 'active') {
            setPhase('summary');
        }
    }, [isComplete, phase]);

    useEffect(() => {
        if (phase === 'summary') {
            const timer = setTimeout(() => setPhase('exiting'), 2500);
            return () => clearTimeout(timer);
        }
    }, [phase]);

    useEffect(() => {
        if (phase === 'exiting') {
            const timer = setTimeout(() => onExit(), 600);
            return () => clearTimeout(timer);
        }
    }, [phase, onExit]);

    // Spawn particles from the active step downward
    const spawnParticle = useCallback(() => {
        if (activeIdx < 0 || activeIdx >= workflowPhases.length - 1) return;

        const sourceEl = stepRefsMap.current[activeIdx];
        const targetEl = stepRefsMap.current[activeIdx + 1];
        if (!sourceEl || !targetEl) return;

        const containerRect = containerRef.current?.getBoundingClientRect();
        if (!containerRect) return;

        const sourceRect = sourceEl.getBoundingClientRect();
        const targetRect = targetEl.getBoundingClientRect();

        const sx = sourceRect.left - containerRect.left + sourceRect.width / 2;
        const sy = sourceRect.bottom - containerRect.top;
        const tx = targetRect.left - containerRect.left + targetRect.width / 2;
        const ty = targetRect.top - containerRect.top + targetRect.height / 2;

        const id = particleIdRef.current++;
        const color = Math.random() > 0.5 ? CYAN : VIOLET;

        return {
            id,
            x: sx + (Math.random() - 0.5) * 12,
            y: sy,
            targetX: tx + (Math.random() - 0.5) * 8,
            targetY: ty,
            progress: 0,
            speed: 0.015 + Math.random() * 0.015,
            size: 2 + Math.random() * 2,
            color,
            opacity: 0.7 + Math.random() * 0.3,
        };
    }, [activeIdx, workflowPhases]);

    // Animation loop
    useEffect(() => {
        if (phase !== 'active' || activeIdx < 0 || activeIdx >= workflowPhases.length - 1) {
            setParticles([]);
            return;
        }

        let lastSpawn = 0;
        const SPAWN_INTERVAL = 80; // ms between spawns

        const tick = (now) => {
            // Spawn new particles periodically
            if (now - lastSpawn > SPAWN_INTERVAL) {
                const p = spawnParticle();
                if (p) {
                    setParticles((prev) => {
                        const next = [...prev, p];
                        return next.length > 30 ? next.slice(-30) : next;
                    });
                }
                lastSpawn = now;
            }

            // Advance existing particles
            setParticles((prev) =>
                prev
                    .map((p) => ({
                        ...p,
                        x: p.x + (p.targetX - p.x) * p.speed * 2,
                        y: p.y + (p.targetY - p.y) * p.speed * 2,
                        progress: p.progress + p.speed,
                    }))
                    .filter((p) => p.progress < 1),
            );

            animFrameRef.current = requestAnimationFrame(tick);
        };

        animFrameRef.current = requestAnimationFrame(tick);

        return () => {
            if (animFrameRef.current) {
                cancelAnimationFrame(animFrameRef.current);
            }
        };
    }, [phase, activeIdx, workflowPhases.length, spawnParticle]);

    const getStepIcon = (status) => {
        if (status === 'complete') return ICON_CHECK;
        if (status === 'active') return ICON_SPINNER;
        if (status === 'error') return ICON_ERROR;
        if (status === 'waiting') return ICON_WAITING;
        return ICON_PENDING;
    };

    return (
        <div
            ref={containerRef}
            className={`brain${phase === 'exiting' ? ' brain--exiting' : ''}`}
        >
            {/* SVG particle overlay */}
            <svg className="brain__particles" aria-hidden="true">
                <defs>
                    <filter id="particle-glow">
                        <feGaussianBlur stdDeviation="2.5" />
                    </filter>
                </defs>
                {particles.map((p) => (
                    <circle
                        key={p.id}
                        cx={p.x}
                        cy={p.y}
                        r={p.size}
                        fill={p.color}
                        opacity={p.opacity * (1 - p.progress)}
                        filter="url(#particle-glow)"
                    />
                ))}
            </svg>

            {/* Step checklist */}
            <div className="brain__steps">
                {workflowPhases.map((wf, idx) => wf.status !== 'pending' || wf.toolCalls.length > 0 ? (
                    <div
                        key={wf.id}
                        ref={(el) => { stepRefsMap.current[idx] = el; }}
                        className={`brain__step brain__step--${wf.status}`}
                    >
                        <span className="brain__step-icon">
                            {getStepIcon(wf.status)}
                        </span>
                        <span className="brain__step-label">{wf.label}</span>
                        {wf.progressMessage && (
                            <span className="brain__step-progress">{wf.progressMessage}</span>
                        )}
                    </div>
                ) : (
                    <div
                        key={wf.id}
                        ref={(el) => { stepRefsMap.current[idx] = el; }}
                        className="brain__step brain__step--pending"
                    >
                        <span className="brain__step-icon">{ICON_PENDING}</span>
                        <span className="brain__step-label">{wf.label}</span>
                    </div>
                ))}
            </div>

            {/* Summary after all complete */}
            {phase === 'summary' && (
                <div className="brain__summary">
                    {completedCount} action{completedCount !== 1 ? 's' : ''} completed
                </div>
            )}
        </div>
    );
}

export default BrainVisualization;
