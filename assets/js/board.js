/**
 * CheckMasters — Board UI Controller
 * Handles rendering, drag-drop, animations, timers
 */

class BoardUI {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.engine = new CheckersEngine();
        this.options = {
            mode: options.mode || 'ai',       // 'ai' | 'pvp' | 'online'
            difficulty: options.difficulty || 'medium',
            playerSide: options.playerSide || P1,
            skin: options.skin || 'classic',
            timeControl: options.timeControl || 300,
            onMove: options.onMove || null,
            onGameOver: options.onGameOver || null,
            soundEnabled: options.soundEnabled !== false,
            hintsEnabled: options.hintsEnabled !== false,
        };
        this.timers = [0, options.timeControl || 300, options.timeControl || 300];
        this.timerInterval = null;
        this.aiThinking = false;
        this.selected = null;
        this.possibleMoves = [];
        this.lastMove = null;

        this.sounds = this.initSounds();
        this.render();
        this.startTimer();
    }

    initSounds() {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const beep = (freq, dur) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.frequency.value = freq;
            g.gain.setValueAtTime(.3, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + dur);
            o.start(); o.stop(ctx.currentTime + dur);
        };
        return {
            move: () => this.options.soundEnabled && beep(440, .08),
            capture: () => this.options.soundEnabled && beep(220, .15),
            king: () => this.options.soundEnabled && beep(660, .2),
            win: () => this.options.soundEnabled && beep(880, .4),
        };
    }

    render() {
        this.container.innerHTML = '';
        this.container.className = `board skin-${this.options.skin}`;
        for (let r = 0; r < 8; r++)
            for (let c = 0; c < 8; c++)
                this.container.appendChild(this.createCell(r, c));
    }

    createCell(r, c) {
        const cell = document.createElement('div');
        cell.className = `board-cell ${(r+c)%2===0 ? 'light' : 'dark'}`;
        cell.dataset.r = r; cell.dataset.c = c;
        cell.id = `cell-${r}-${c}`;

        const v = this.engine.board[r][c];
        if (v !== EMPTY) {
            const piece = this.createPiece(v, r, c);
            cell.appendChild(piece);
        }

        // Highlight states
        if (this.selected && this.selected[0]===r && this.selected[1]===c)
            cell.classList.add('selected');
        if (this.possibleMoves.some(m => m.to[0]===r && m.to[1]===c)) {
            const isCapture = this.possibleMoves.some(m => m.to[0]===r && m.to[1]===c && m.captures.length > 0);
            cell.classList.add(isCapture ? 'possible-capture' : 'possible-move');
        }
        if (this.lastMove) {
            const {from, to} = this.lastMove;
            if ((from[0]===r&&from[1]===c)||(to[0]===r&&to[1]===c))
                cell.classList.add('last-move');
        }

        cell.addEventListener('click', () => this.handleCellClick(r, c));
        return cell;
    }

    createPiece(v, r, c) {
        const piece = document.createElement('div');
        piece.className = `piece p${this.engine.isP1(v) ? 1 : 2}${this.engine.isKing(v) ? ' king' : ''}`;
        piece.dataset.r = r; piece.dataset.c = c;
        if (this.selected && this.selected[0]===r && this.selected[1]===c)
            piece.classList.add('selected-piece');
        return piece;
    }

    handleCellClick(r, c) {
        if (this.engine.gameOver || this.aiThinking) return;
        if (this.options.mode === 'ai' && this.engine.turn !== this.options.playerSide) return;
        if (this.options.mode === 'online') return; // handled by WS

        const v = this.engine.board[r][c];

        // Clicked own piece → select
        if (this.engine.isOwn(v, this.engine.turn)) {
            this.selected = [r, c];
            this.possibleMoves = this.engine.getMovesFrom(r, c);
            this.render();
            return;
        }

        // Clicked possible move destination
        if (this.selected) {
            const move = this.possibleMoves.find(m => m.to[0]===r && m.to[1]===c);
            if (move) {
                this.executeMove(move);
                return;
            }
        }

        // Deselect
        this.selected = null;
        this.possibleMoves = [];
        this.render();
    }

    executeMove(move) {
        const hadCaptures = move.captures.length > 0;
        const fromPiece = this.engine.board[move.from[0]][move.from[1]];
        const movedBy = this.engine.turn; // who is moving NOW

        this.engine.applyMove(move);
        this.lastMove = move;
        this.selected = null;
        this.possibleMoves = [];

        // Sounds
        if (hadCaptures) this.sounds.capture();
        else this.sounds.move();

        // King sound
        const newV = this.engine.board[move.to[0]][move.to[1]];
        if (!this.engine.isKing(fromPiece) && this.engine.isKing(newV)) this.sounds.king();

        this.render();
        this.updateSidebars(move);

        if (this.options.onMove) this.options.onMove(move, this.engine);

        if (this.engine.gameOver) {
            this.handleGameOver();
            return;
        }

        // AI turn: fire when it's the AI's side to move
        const aiSide = this.options.playerSide === P1 ? P2 : P1;
        if (this.options.mode === 'ai' && this.engine.turn === aiSide) {
            setTimeout(() => this.doAIMove(), 350);
        }
    }

    doAIMove() {
        this.aiThinking = true;
        this.showAIThinking(true);

        // Reduce depths to keep UI responsive
        const depths = { easy:2, medium:3, hard:4, expert:5 };
        const depth = depths[this.options.difficulty] || 3;

        // Run in a micro-task to let UI update first
        const engineSnapshot = this.engine.clone();
        setTimeout(() => {
            try {
                const move = engineSnapshot.getBestMove(depth);
                this.aiThinking = false;
                this.showAIThinking(false);
                if (move && !this.engine.gameOver) {
                    this.executeMove(move);
                }
            } catch(e) {
                console.error('AI error:', e);
                // Fallback: pick random move
                const moves = this.engine.getAllMoves();
                this.aiThinking = false;
                this.showAIThinking(false);
                if (moves.length > 0) this.executeMove(moves[0]);
            }
        }, 80);
    }

    showAIThinking(show) {
        const el = document.getElementById('aiThinkingIndicator');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    updateSidebars(move) {
        // Update move list
        const ml = document.getElementById('moveList');
        if (ml) {
            const n = this.engine.moveHistory.length;
            const m = move;
            const notation = `${String.fromCharCode(97+m.from[1])}${8-m.from[0]}→${String.fromCharCode(97+m.to[1])}${8-m.to[0]}`;
            const isP1Move = this.engine.moveHistory[n-1]?.turn === P1;

            if (isP1Move || ml.children.length === 0) {
                const row = document.createElement('div');
                row.className = 'move-item';
                row.innerHTML = `<span class="move-num">${Math.ceil(n/2)}.</span><span class="move-white">${notation}</span><span class="move-black"></span>`;
                ml.appendChild(row);
            } else {
                const last = ml.lastElementChild;
                if (last) last.querySelector('.move-black').textContent = notation;
            }
            ml.scrollTop = ml.scrollHeight;
        }

        // Captured counts
        const cp1 = document.getElementById('capturedP1');
        const cp2 = document.getElementById('capturedP2');
        if (cp1) cp1.textContent = this.engine.capturedP1;
        if (cp2) cp2.textContent = this.engine.capturedP2;

        // Active player highlight
        document.getElementById('panel-p1')?.classList.toggle('active', this.engine.turn === P1);
        document.getElementById('panel-p2')?.classList.toggle('active', this.engine.turn === P2);
    }

    startTimer() {
        this.timerInterval = setInterval(() => {
            if (this.engine.gameOver || this.aiThinking) return;
            const t = this.engine.turn;
            this.timers[t] = Math.max(0, this.timers[t] - 1);
            this.renderTimers();
            if (this.timers[t] === 0) {
                this.engine.gameOver = true;
                this.engine.winner = t === P1 ? P2 : P1;
                this.handleGameOver();
            }
        }, 1000);
    }

    renderTimers() {
        const fmt = s => `${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;
        const t1 = document.getElementById('timer-p1');
        const t2 = document.getElementById('timer-p2');
        if (t1) { t1.textContent = fmt(this.timers[P1]); t1.classList.toggle('urgent', this.timers[P1] <= 30 && this.engine.turn === P1); }
        if (t2) { t2.textContent = fmt(this.timers[P2]); t2.classList.toggle('urgent', this.timers[P2] <= 30 && this.engine.turn === P2); }
    }

    handleGameOver() {
        clearInterval(this.timerInterval);
        this.sounds.win();
        this.render();
        setTimeout(() => {
            if (this.options.onGameOver) this.options.onGameOver(this.engine);
        }, 500);
    }

    getHints() {
        return this.engine.getMovesFrom(...(this.selected || [0,0]));
    }

    setDifficulty(d) { this.options.difficulty = d; }
    setSkin(s) { this.options.skin = s; this.render(); }
    toggleHints(v) { this.options.hintsEnabled = v; }
    toggleSound(v) { this.options.soundEnabled = v; }

    reset() {
        clearInterval(this.timerInterval);
        this.engine = new CheckersEngine();
        this.selected = null;
        this.possibleMoves = [];
        this.lastMove = null;
        this.timers = [0, this.options.timeControl, this.options.timeControl];
        this.aiThinking = false;
        this.render();
        this.startTimer();
        const ml = document.getElementById('moveList');
        if (ml) ml.innerHTML = '';
    }
}
