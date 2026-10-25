/**
 * Authoritative Draft Timer Controller for KTPL Drafting
 *
 * Enforces strict single-deadline lifecycle:
 * - Exactly one running deadline (targetEndTime) per active turn.
 * - While the same turn is running, targetEndTime remains fixed.
 * - Remaining seconds strictly decrease monotonically: Math.max(0, Math.ceil((targetEndTime - Date.now()) / 1000)).
 * - Stale polling, Livewire DOM morphs, or delayed broadcasts for the SAME running turn
 *   CANNOT overwrite remaining seconds or extend targetEndTime.
 * - Established deadlines only change on true state transitions:
 *     1. New Turn (turnKey changes from previous turn)
 *     2. Explicit Resume (isPaused transitions from true -> false)
 *     3. Explicit Pause (isPaused transitions from false -> true, freezing the clock)
 * - Central diagnostic tracer [DRAFT-TIMER-TRACE] logs all state mutations and catches suspicious resets.
 */

function normalizeDetail(detail) {
    if (!detail) return {};
    if (Array.isArray(detail)) {
        return normalizeDetail(detail[0] || {});
    }
    if (detail && typeof detail === 'object' && detail.detail !== undefined) {
        return normalizeDetail(detail.detail);
    }
    return detail;
}

function logTimerTrace(action, details) {
    const prevRem = details.prevRemaining !== undefined ? details.prevRemaining : 'N/A';
    const newRem = details.newRemaining !== undefined ? details.newRemaining : 'N/A';
    const prevDead = details.prevDeadline !== undefined ? details.prevDeadline : 'N/A';
    const newDead = details.newDeadline !== undefined ? details.newDeadline : 'N/A';
    const source = details.source || 'internal';
    const turnKey = details.turnKey || 'none';
    const isPaused = Boolean(details.isPaused);
    const isSuspicious = Boolean(details.isSuspicious);

    const logPrefix = `[DRAFT-TIMER-TRACE] [${action}] (source: ${source}, turn: ${turnKey}, paused: ${isPaused})`;
    const detailMsg = `prevRemaining: ${prevRem} -> newRemaining: ${newRem} | prevDeadline: ${prevDead} -> newDeadline: ${newDead}`;

    if (isSuspicious) {
        console.warn(`${logPrefix} SUSPICIOUS INCREASE DETECTED: ${detailMsg}`, {
            stack: new Error().stack,
            ...details
        });
    } else {
        console.log(`${logPrefix} ${detailMsg}`);
    }
}

// Global Authoritative Timer State
window.__ktplDraftTimer = window.__ktplDraftTimer || {
    intervalId: null,
    targetEndTime: null,
    remaining: 0,
    total: 60,
    isPaused: false,
    turnKey: 'none',
    hasActiveRound: false,
    expiredTriggered: false,
    listeners: new Set(),

    subscribe(callback) {
        this.listeners.add(callback);
        // Immediately notify with current state
        callback(this.getState());
        return () => this.listeners.delete(callback);
    },

    notify() {
        const state = this.getState();
        this.listeners.forEach((callback) => {
            try {
                callback(state);
            } catch (err) {
                console.error('[DRAFT-TIMER-TRACE] Error in listener callback:', err);
            }
        });
    },

    getState() {
        return {
            remaining: this.remaining,
            total: this.total,
            isPaused: this.isPaused,
            turnKey: this.turnKey,
            hasActiveRound: this.hasActiveRound,
        };
    },

    stopTicker() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },

    startTicker(preserveDeadline = false) {
        this.stopTicker();

        if (!this.hasActiveRound || this.isPaused || this.remaining <= 0) {
            return;
        }

        if (!preserveDeadline || !this.targetEndTime) {
            this.targetEndTime = Date.now() + (this.remaining * 1000);
        }

        // High precision 250ms interval against Date.now()
        this.intervalId = setInterval(() => {
            if (!this.hasActiveRound || this.isPaused) {
                this.stopTicker();
                this.notify();
                return;
            }

            const now = Date.now();
            const diffMs = this.targetEndTime - now;
            const newRemaining = Math.max(0, Math.ceil(diffMs / 1000));

            if (newRemaining !== this.remaining) {
                // Strict Monotonicity Guard: while running same turn, remaining MUST NOT increase
                if (newRemaining > this.remaining) {
                    logTimerTrace('ticker-anomaly', {
                        prevRemaining: this.remaining,
                        newRemaining,
                        prevDeadline: this.targetEndTime,
                        newDeadline: this.targetEndTime,
                        turnKey: this.turnKey,
                        isPaused: false,
                        isSuspicious: true
                    });
                    return;
                }

                this.remaining = newRemaining;
                this.notify();
            }

            if (newRemaining <= 0) {
                this.stopTicker();
                this.remaining = 0;
                this.notify();
                if (!this.expiredTriggered) {
                    this.expiredTriggered = true;
                    logTimerTrace('timer-expired', {
                        prevRemaining: 1,
                        newRemaining: 0,
                        turnKey: this.turnKey,
                        isPaused: false
                    });
                    this.triggerTurnExpiration();
                }
            }
        }, 250);
    },

    triggerTurnExpiration() {
        if (typeof window !== 'undefined' && window.Livewire) {
            const el = document.querySelector('[wire\\:id]');
            if (el) {
                const component = window.Livewire.find(el.getAttribute('wire:id'));
                if (component && typeof component.pollActiveRound === 'function') {
                    component.pollActiveRound();
                }
            }
        }
    },

    sync(rawPayload, source = 'unknown') {
        const data = normalizeDetail(rawPayload);
        if (!data || Object.keys(data).length === 0) {
            return;
        }

        const incomingHasRound = Boolean(data.hasActiveRound);
        const incomingIsPaused = Boolean(data.isPaused);
        const incomingTotal = Math.max(1, parseInt(data.total, 10) || 60);
        const incomingRemaining = Math.max(0, parseInt(data.remaining, 10) || 0);
        const incomingTurnKey = String(data.turnKey || 'none');

        const prevRemaining = this.remaining;
        const prevDeadline = this.targetEndTime;
        const prevTurnKey = this.turnKey;
        const prevPaused = this.isPaused;

        // No active round
        if (!incomingHasRound || incomingTurnKey === 'none') {
            if (this.hasActiveRound) {
                this.hasActiveRound = false;
                this.isPaused = false;
                this.remaining = 0;
                this.turnKey = 'none';
                this.expiredTriggered = false;
                this.stopTicker();
                this.targetEndTime = null;
                this.notify();
                logTimerTrace('round-closed', {
                    source,
                    prevRemaining,
                    newRemaining: 0,
                    prevDeadline,
                    newDeadline: null,
                    turnKey: 'none',
                    isPaused: false
                });
            }
            return;
        }

        this.hasActiveRound = true;
        this.total = incomingTotal;

        // Case 1: NEW TURN (turnKey changed)
        const isNewTurn = (incomingTurnKey !== this.turnKey);
        if (isNewTurn) {
            this.turnKey = incomingTurnKey;
            this.isPaused = incomingIsPaused;
            this.remaining = incomingRemaining;
            this.expiredTriggered = false;

            if (incomingIsPaused) {
                this.stopTicker();
                this.targetEndTime = null;
            } else {
                this.targetEndTime = Date.now() + (incomingRemaining * 1000);
                this.startTicker();
            }

            logTimerTrace('new-turn', {
                source,
                prevRemaining,
                newRemaining: this.remaining,
                prevDeadline,
                newDeadline: this.targetEndTime,
                turnKey: this.turnKey,
                isPaused: this.isPaused
            });

            this.notify();
            return;
        }

        // Case 2: PAUSE STATE TRANSITION (Running -> Paused OR Paused -> Running)
        if (prevPaused !== incomingIsPaused) {
            this.isPaused = incomingIsPaused;

            if (incomingIsPaused) {
                // Transition: Running -> PAUSED
                this.stopTicker();
                this.targetEndTime = null;
                this.remaining = incomingRemaining;

                logTimerTrace('pause-transition', {
                    source,
                    prevRemaining,
                    newRemaining: this.remaining,
                    prevDeadline,
                    newDeadline: null,
                    turnKey: this.turnKey,
                    isPaused: true
                });
            } else {
                // Transition: Paused -> RESUMED
                // Establish deadline from the saved remaining seconds exactly once!
                this.remaining = incomingRemaining;
                this.targetEndTime = Date.now() + (incomingRemaining * 1000);
                this.startTicker();

                logTimerTrace('resume-transition', {
                    source,
                    prevRemaining,
                    newRemaining: this.remaining,
                    prevDeadline,
                    newDeadline: this.targetEndTime,
                    turnKey: this.turnKey,
                    isPaused: false
                });
            }

            this.notify();
            return;
        }

        // Case 3: CURRENTLY PAUSED (No transition, still paused for same turn)
        if (this.isPaused) {
            // Keep timer frozen. If incoming remaining is valid, sync it without starting ticker
            this.remaining = incomingRemaining;
            this.stopTicker();
            this.targetEndTime = null;
            this.notify();
            return;
        }

        // Case 4: CURRENTLY RUNNING SAME TURN
        // CRITICAL RULE: A running turn's deadline is FIXED.
        // Stale polls, Livewire morphs, or delayed broadcasts MUST NOT increase remaining time or extend deadline!
        const currentCalculatedRemaining = this.targetEndTime
            ? Math.max(0, Math.ceil((this.targetEndTime - Date.now()) / 1000))
            : this.remaining;

        if (incomingRemaining > currentCalculatedRemaining) {
            // Stale update attempted to increase running countdown!
            // REJECT IT AND LOG!
            logTimerTrace('stale-increase-rejected', {
                source,
                prevRemaining: currentCalculatedRemaining,
                newRemaining: incomingRemaining,
                prevDeadline: this.targetEndTime,
                newDeadline: this.targetEndTime,
                turnKey: this.turnKey,
                isPaused: false,
                isSuspicious: true
            });
            return;
        }

        // If ticker somehow stopped while running with remaining > 0, resume it with existing deadline
        if (!this.intervalId && this.remaining > 0 && this.targetEndTime) {
            this.startTicker(true);
        }
    }
};

// Global Event Listeners - Singleton Registration
if (typeof window !== 'undefined') {
    // Only register ONCE on window to prevent duplicate listeners
    if (!window.__ktplDraftTimerListenerRegistered) {
        window.__ktplDraftTimerListenerRegistered = true;
        window.addEventListener('timer-sync', (e) => {
            window.__ktplDraftTimer.sync(e.detail, 'window:timer-sync');
        });
    }
}

// Alpine Component Definition
export function ktplDraftTimerComponent() {
    const initialState = window.__ktplDraftTimer ? window.__ktplDraftTimer.getState() : {
        remaining: 0,
        total: 60,
        isPaused: false,
        turnKey: 'none',
        hasActiveRound: false
    };

    return {
        _remaining: initialState.remaining,
        total: initialState.total,
        isPaused: initialState.isPaused,
        turnKey: initialState.turnKey,
        hasActiveRound: initialState.hasActiveRound,
        unsubscribe: null,

        get remaining() {
            return this._remaining !== undefined ? this._remaining : 0;
        },
        set remaining(val) {
            const parsed = Math.max(0, parseInt(val, 10) || 0);
            if (!this.isPaused && this._remaining > 0 && parsed > this._remaining) {
                console.warn(`[DRAFT-TIMER-TRACE] [Alpine:remaining-setter] SUSPICIOUS COUNTDOWN INCREASE from ${this._remaining} to ${parsed} REJECTED!`, {
                    stack: new Error().stack
                });
                return; // Guard against external morph increase!
            }
            this._remaining = parsed;
        },

        initTimer(initialConfig = {}) {
            // Subscribe this Alpine component to global authoritative timer
            this.unsubscribe = window.__ktplDraftTimer.subscribe((state) => {
                this._remaining = state.remaining;
                this.total = state.total;
                this.isPaused = state.isPaused;
                this.turnKey = state.turnKey;
                this.hasActiveRound = state.hasActiveRound;
            });

            // Feed initial config from Blade render ONLY if global timer is uninitialized or turn changed
            if (initialConfig && initialConfig.hasActiveRound && initialConfig.turnKey && initialConfig.turnKey !== 'none') {
                if (window.__ktplDraftTimer.turnKey !== String(initialConfig.turnKey)) {
                    window.__ktplDraftTimer.sync(initialConfig, 'Blade:x-init');
                }
            }
        },

        destroyTimer() {
            if (typeof this.unsubscribe === 'function') {
                this.unsubscribe();
                this.unsubscribe = null;
            }
        },

        formatTime(sec) {
            const safe = Math.max(0, parseInt(sec, 10) || 0);
            const m = Math.floor(safe / 60).toString().padStart(2, '0');
            const s = (safe % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }
    };
}

if (typeof window !== 'undefined') {
    window.ktplDraftTimerComponent = ktplDraftTimerComponent;

    if (window.Alpine) {
        window.Alpine.data('ktplDraftTimerComponent', ktplDraftTimerComponent);
    } else if (typeof document !== 'undefined') {
        document.addEventListener('alpine:init', () => {
            if (window.Alpine) {
                window.Alpine.data('ktplDraftTimerComponent', ktplDraftTimerComponent);
            }
        });
    }
}
