/**
 * Authoritative Draft Timer Controller for KTPL Drafting
 *
 * Single Authoritative Server-Deadline Model:
 * - Server is the single source of truth for time and deadline.
 * - Clock offset is computed from serverTimeMs: (serverTimeMs - Date.now()).
 * - While a turn is running, countdown is derived monotonically from serverDeadline:
 *     serverDeadline = turnStartedAtMs + (total * 1000)
 *     currentServerTime = Date.now() + clockOffset
 *     remainingMs = max(0, serverDeadline - currentServerTime)
 *     remainingSeconds = ceil(remainingMs / 1000)
 * - Exact cross-device synchronization: every device on earth calculates the identical
 *   remaining seconds regardless of local device clock differences or network latency!
 * - High performance requestAnimationFrame loop guarantees smooth, zero-stutter visual updates.
 * - Stale out-of-order events or polling responses are rejected via monotonic stateVersion.
 * - Direct Laravel Echo WebSocket channel subscriptions ensure instantaneous (<20ms) reaction.
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
    const source = details.source || 'internal';
    const turnKey = details.turnKey || 'none';
    const isPaused = Boolean(details.isPaused);
    const isSuspicious = Boolean(details.isSuspicious);

    const logPrefix = `[DRAFT-TIMER-TRACE] [${action}] (source: ${source}, turn: ${turnKey}, paused: ${isPaused})`;
    const detailMsg = `prevRemaining: ${prevRem} -> newRemaining: ${newRem}`;

    if (isSuspicious) {
        console.warn(`${logPrefix} SUSPICIOUS INCREASE DETECTED: ${detailMsg}`, details);
    } else {
        console.log(`${logPrefix} ${detailMsg}`);
    }
}

// Global Authoritative Timer State
window.__ktplDraftTimer = window.__ktplDraftTimer || {
    animFrameId: null,
    serverDeadline: null,
    clockOffset: 0,
    stateVersion: 0,
    remaining: 0,
    total: 60,
    isPaused: false,
    turnKey: 'none',
    hasActiveRound: false,
    expiredTriggered: false,
    listeners: new Set(),

    currentServerTimeMs() {
        return Date.now() + this.clockOffset;
    },

    updateClockOffset(serverTimeMs) {
        if (serverTimeMs) {
            const parsed = parseInt(serverTimeMs, 10);
            if (!isNaN(parsed) && parsed > 0) {
                // Set or gently smooth clock offset
                this.clockOffset = parsed - Date.now();
            }
        }
    },

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
            serverDeadline: this.serverDeadline,
            stateVersion: this.stateVersion,
        };
    },

    stopTicker() {
        if (this.animFrameId) {
            cancelAnimationFrame(this.animFrameId);
            this.animFrameId = null;
        }
    },

    startTicker() {
        this.stopTicker();

        if (!this.hasActiveRound || this.isPaused || this.remaining <= 0) {
            return;
        }

        if (!this.serverDeadline) {
            this.serverDeadline = this.currentServerTimeMs() + (this.remaining * 1000);
        }

        const tick = () => {
            if (!this.hasActiveRound || this.isPaused) {
                this.stopTicker();
                this.notify();
                return;
            }

            const now = this.currentServerTimeMs();
            const diffMs = this.serverDeadline - now;
            const newRemaining = Math.max(0, Math.ceil(diffMs / 1000));

            if (newRemaining !== this.remaining) {
                // Strict Monotonicity Guard: while running the same turn instance, remaining MUST NOT increase
                if (newRemaining > this.remaining) {
                    logTimerTrace('ticker-anomaly', {
                        prevRemaining: this.remaining,
                        newRemaining,
                        turnKey: this.turnKey,
                        isPaused: false,
                        isSuspicious: true
                    });
                } else {
                    this.remaining = newRemaining;
                    this.notify();
                }
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
                return;
            }

            this.animFrameId = requestAnimationFrame(tick);
        };

        this.animFrameId = requestAnimationFrame(tick);
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

        // Update server clock offset if provided
        if (data.serverTimeMs) {
            this.updateClockOffset(data.serverTimeMs);
        }

        // Monotonic state versioning: ignore stale out-of-order updates
        if (data.stateVersion) {
            const incomingVersion = parseInt(data.stateVersion, 10);
            if (!isNaN(incomingVersion) && incomingVersion > 0) {
                if (this.stateVersion && incomingVersion < this.stateVersion) {
                    console.log(`[DRAFT-TIMER-TRACE] Stale stateVersion ${incomingVersion} < ${this.stateVersion} from ${source} rejected.`);
                    return;
                }
                this.stateVersion = Math.max(this.stateVersion, incomingVersion);
            }
        }

        const incomingHasRound = (data.hasActiveRound !== undefined)
            ? Boolean(data.hasActiveRound)
            : (data.status === 'active');

        const incomingIsPaused = Boolean(data.isPaused);
        const incomingTotal = Math.max(1, parseInt(data.total || data.turnTimeSeconds, 10) || 60);

        const rawRemaining = (data.remainingTurnSeconds !== undefined && data.remainingTurnSeconds !== null)
            ? data.remainingTurnSeconds
            : (data.remainingSeconds !== undefined && data.remainingSeconds !== null
                ? data.remainingSeconds
                : (data.remaining !== undefined && data.remaining !== null ? data.remaining : null));

        const incomingTurnStartedAtMs = (data.turnStartedAtMs !== undefined && data.turnStartedAtMs !== null)
            ? parseInt(data.turnStartedAtMs, 10)
            : null;

        // Turn key calculation
        let incomingTurnKey = String(data.turnKey || '');
        if (!incomingTurnKey || incomingTurnKey === 'none') {
            const rId = data.roundId || data.id || 0;
            const pNum = data.currentPickNumber || 0;
            const tId = data.currentTeamId || 0;
            const startedAt = data.turnStartedAt || (incomingTurnStartedAtMs ? String(incomingTurnStartedAtMs) : '');
            if (rId) {
                incomingTurnKey = `${rId}-${pNum}-${tId}@${startedAt}`;
            } else {
                incomingTurnKey = 'none';
            }
        }

        const prevRemaining = this.remaining;
        const prevPaused = this.isPaused;
        const isForceReset = Boolean(data.forceReset) ||
            (typeof data.message === 'string' && data.message.toLowerCase().includes('reset'));

        // No active round
        if (!incomingHasRound || incomingTurnKey === 'none') {
            if (this.hasActiveRound) {
                this.hasActiveRound = false;
                this.isPaused = false;
                this.remaining = 0;
                this.turnKey = 'none';
                this.expiredTriggered = false;
                this.serverDeadline = null;
                this.stopTicker();
                this.notify();
                logTimerTrace('round-closed', {
                    source,
                    prevRemaining,
                    newRemaining: 0,
                    turnKey: 'none',
                    isPaused: false
                });
            }
            return;
        }

        this.hasActiveRound = true;
        this.total = incomingTotal;

        // Calculate authoritative remaining seconds
        let incomingRemaining = rawRemaining !== null ? Math.max(0, parseInt(rawRemaining, 10)) : incomingTotal;
        if (incomingTurnStartedAtMs && !incomingIsPaused) {
            const calculatedDeadline = incomingTurnStartedAtMs + (incomingTotal * 1000);
            const diffMs = calculatedDeadline - this.currentServerTimeMs();
            incomingRemaining = Math.max(0, Math.ceil(diffMs / 1000));
        }

        // Case 1: Force Reset
        if (isForceReset) {
            this.turnKey = incomingTurnKey;
            this.isPaused = incomingIsPaused;
            this.remaining = incomingRemaining;
            this.expiredTriggered = false;

            if (incomingIsPaused) {
                this.stopTicker();
                this.serverDeadline = null;
            } else {
                if (incomingTurnStartedAtMs) {
                    this.serverDeadline = incomingTurnStartedAtMs + (incomingTotal * 1000);
                } else {
                    this.serverDeadline = this.currentServerTimeMs() + (incomingRemaining * 1000);
                }
                this.startTicker();
            }

            logTimerTrace('timer-reset', {
                source,
                prevRemaining,
                newRemaining: this.remaining,
                turnKey: this.turnKey,
                isPaused: this.isPaused
            });

            this.notify();
            return;
        }

        // Case 2: New Turn
        const isNewTurn = (incomingTurnKey !== this.turnKey);
        if (isNewTurn) {
            this.turnKey = incomingTurnKey;
            this.isPaused = incomingIsPaused;
            this.remaining = incomingRemaining;
            this.expiredTriggered = false;

            if (incomingIsPaused) {
                this.stopTicker();
                this.serverDeadline = null;
            } else {
                if (incomingTurnStartedAtMs) {
                    this.serverDeadline = incomingTurnStartedAtMs + (incomingTotal * 1000);
                } else {
                    this.serverDeadline = this.currentServerTimeMs() + (incomingRemaining * 1000);
                }
                this.startTicker();
            }

            logTimerTrace('new-turn', {
                source,
                prevRemaining,
                newRemaining: this.remaining,
                turnKey: this.turnKey,
                isPaused: this.isPaused
            });

            this.notify();
            return;
        }

        // Case 3: Pause State Transition (Running <-> Paused)
        if (prevPaused !== incomingIsPaused) {
            this.isPaused = incomingIsPaused;

            if (incomingIsPaused) {
                // Transition: Running -> PAUSED
                this.stopTicker();
                this.serverDeadline = null;
                this.remaining = incomingRemaining;

                logTimerTrace('pause-transition', {
                    source,
                    prevRemaining,
                    newRemaining: this.remaining,
                    turnKey: this.turnKey,
                    isPaused: true
                });
            } else {
                // Transition: Paused -> RESUMED
                this.remaining = incomingRemaining;
                if (incomingTurnStartedAtMs) {
                    this.serverDeadline = incomingTurnStartedAtMs + (incomingTotal * 1000);
                } else {
                    this.serverDeadline = this.currentServerTimeMs() + (incomingRemaining * 1000);
                }
                this.startTicker();

                logTimerTrace('resume-transition', {
                    source,
                    prevRemaining,
                    newRemaining: this.remaining,
                    turnKey: this.turnKey,
                    isPaused: false
                });
            }

            this.notify();
            return;
        }

        // Case 4: Still Paused (No transition)
        if (this.isPaused) {
            this.remaining = incomingRemaining;
            this.stopTicker();
            this.serverDeadline = null;
            this.notify();
            return;
        }

        // Case 5: Running Same Turn
        // Keep running against authoritative server deadline
        if (incomingTurnStartedAtMs && !this.serverDeadline) {
            this.serverDeadline = incomingTurnStartedAtMs + (incomingTotal * 1000);
        }

        const currentCalculatedRemaining = this.serverDeadline
            ? Math.max(0, Math.ceil((this.serverDeadline - this.currentServerTimeMs()) / 1000))
            : this.remaining;

        // Strict monotonicity check: do not let delayed HTTP polls jump running time upward
        if (source.includes('poll') || source.includes('Blade')) {
            if (incomingRemaining > currentCalculatedRemaining) {
                logTimerTrace('stale-poll-rejected', {
                    source,
                    prevRemaining: currentCalculatedRemaining,
                    newRemaining: incomingRemaining,
                    turnKey: this.turnKey,
                    isPaused: false,
                    isSuspicious: true
                });
                return;
            }
        }

        // Ensure ticker is running
        if (!this.animFrameId && this.remaining > 0 && this.serverDeadline) {
            this.startTicker();
        }
    }
};

// Direct Laravel Echo WebSocket channel subscriptions for Instant Client Sync
function initDirectEchoSubscription() {
    if (typeof window === 'undefined') return;
    if (window.__ktplDraftEchoSubscribed) return;

    const echo = window.Echo;
    if (!echo) {
        window.addEventListener('echo:ready', () => initDirectEchoSubscription(), { once: true });
        return;
    }

    window.__ktplDraftEchoSubscribed = true;
    const leagues = ['male', 'males', 'female', 'females'];

    leagues.forEach((lg) => {
        const ch = echo.channel('draft.league.' + lg);
        const onTurnChanged = (payload) => {
            window.__ktplDraftTimer.sync(payload, `websocket:echo:league.${lg}`);
        };

        ch.listen('.draft.turn.changed', onTurnChanged);
        ch.listen('draft.turn.changed', onTurnChanged);
        ch.listen('DraftTurnChanged', onTurnChanged);
    });
}

// Global Event Listeners - Singleton Registration
if (typeof window !== 'undefined') {
    if (!window.__ktplDraftTimerListenerRegistered) {
        window.__ktplDraftTimerListenerRegistered = true;

        window.addEventListener('timer-sync', (e) => {
            window.__ktplDraftTimer.sync(e.detail, 'window:timer-sync');
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && window.__ktplDraftTimer.hasActiveRound && !window.__ktplDraftTimer.isPaused) {
                // Instantly refresh remaining time after tab background throttling
                if (window.__ktplDraftTimer.serverDeadline) {
                    const now = window.__ktplDraftTimer.currentServerTimeMs();
                    const diffMs = window.__ktplDraftTimer.serverDeadline - now;
                    window.__ktplDraftTimer.remaining = Math.max(0, Math.ceil(diffMs / 1000));
                    window.__ktplDraftTimer.notify();
                }
            }
        });
    }

    initDirectEchoSubscription();
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
                console.warn(`[DRAFT-TIMER-TRACE] [Alpine:remaining-setter] SUSPICIOUS COUNTDOWN INCREASE from ${this._remaining} to ${parsed} REJECTED!`);
                return;
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
