/**
 * CheckMasters — Board UI Controller (clean rewrite)
 */
class BoardUI {
    constructor(containerId, options={}) {
        this.container = document.getElementById(containerId);
        if (!this.container) { console.error('Board container not found:', containerId); return; }

        this.engine = new CheckersEngine();
        this.options = {
            mode:         options.mode        || 'ai',
            difficulty:   options.difficulty  || 'medium',
            playerSide:   options.playerSide  ?? P1,
            skin:         options.skin        || 'classic',
            timeControl:  options.timeControl || 300,
            soundEnabled: options.soundEnabled !== false,
            onMove:       options.onMove      || null,
            onGameOver:   options.onGameOver  || null,
        };

        this.selected     = null;   // [r,c] or null
        this.possibleMoves= [];     // [{from,to,captures}]
        this.lastMove     = null;
        this.aiThinking   = false;
        this.timers       = [0, this.options.timeControl, this.options.timeControl];
        this.timerInterval= null;

        this.sounds = this._initSounds();
        this.render();

        if (this.options.mode !== 'online') this.startTimer();
    }

    /* ── Sounds ─────────────────────────────────────── */
    _initSounds() {
        try {
            const ctx = new (window.AudioContext||window.webkitAudioContext)();
            const beep=(f,d)=>{
                const o=ctx.createOscillator(), g=ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.value=f;
                g.gain.setValueAtTime(.25,ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(.001,ctx.currentTime+d);
                o.start(); o.stop(ctx.currentTime+d);
            };
            return {
                move:   ()=>this.options.soundEnabled&&beep(440,.08),
                capture:()=>this.options.soundEnabled&&beep(220,.15),
                king:   ()=>this.options.soundEnabled&&beep(660,.2),
                win:    ()=>this.options.soundEnabled&&beep(880,.4),
            };
        } catch(e) {
            return {move:()=>{},capture:()=>{},king:()=>{},win:()=>{}};
        }
    }

    /* ── Render ─────────────────────────────────────── */
    render() {
        // Build set of highlighted cells
        const selectedKey = this.selected ? `${this.selected[0]},${this.selected[1]}` : '';
        const moveDests   = new Set(this.possibleMoves.map(m=>`${m.to[0]},${m.to[1]}`));
        const capDests    = new Set(this.possibleMoves.filter(m=>m.captures.length>0).map(m=>`${m.to[0]},${m.to[1]}`));
        const lastKeys    = this.lastMove
            ? new Set([`${this.lastMove.from[0]},${this.lastMove.from[1]}`,`${this.lastMove.to[0]},${this.lastMove.to[1]}`])
            : new Set();

        this.container.innerHTML = '';
        this.container.className = `board skin-${this.options.skin}`;

        for (let r=0; r<8; r++) {
            for (let c=0; c<8; c++) {
                const cell = document.createElement('div');
                const key  = `${r},${c}`;
                const isLight = (r+c)%2===0;

                cell.className = `board-cell ${isLight?'light':'dark'}`;
                cell.id = `cell-${r}-${c}`;

                if (key===selectedKey)     cell.classList.add('selected');
                if (capDests.has(key))     cell.classList.add('possible-capture');
                else if (moveDests.has(key)) cell.classList.add('possible-move');
                if (lastKeys.has(key))     cell.classList.add('last-move');

                const v = this.engine.board[r][c];
                if (v!==EMPTY) {
                    const piece = document.createElement('div');
                    piece.className = `piece p${this.engine.isP1(v)?1:2}${this.engine.isKing(v)?' king':''}`;
                    if (key===selectedKey) piece.classList.add('selected-piece');
                    // Piece click → bubble to cell — do NOT stop propagation
                    cell.appendChild(piece);
                }

                // Hint dot (DOM element, not ::after — avoids pointer-events issues)
                if (moveDests.has(key) && !capDests.has(key)) {
                    const dot = document.createElement('div');
                    dot.style.cssText='position:absolute;width:32%;height:32%;border-radius:50%;background:rgba(0,0,0,.22);pointer-events:none;z-index:1;top:50%;left:50%;transform:translate(-50%,-50%)';
                    cell.appendChild(dot);
                }

                // Click handler — capture r,c in closure
                cell.addEventListener('click', ((row,col)=>()=>this._onClick(row,col))(r,c));
                this.container.appendChild(cell);
            }
        }
    }

    /* ── Click handling ─────────────────────────────── */
    _onClick(r, c) {
        if (this.engine.gameOver) return;
        if (this.aiThinking)      return;

        // AI mode: only let player move on their turn
        if (this.options.mode==='ai' && this.engine.turn!==this.options.playerSide) return;

        // Online mode: only let the correct player move
        if (this.options.mode==='online' && this.engine.turn!==this.options.playerSide) {
            this._toast('Подожди — ход соперника!');
            return;
        }

        const v = this.engine.board[r][c];

        /* Case 1: clicked a destination of a pending move */
        if (this.selected) {
            const move = this.possibleMoves.find(m=>m.to[0]===r&&m.to[1]===c);
            if (move) { this._doMove(move); return; }
        }

        /* Case 2: clicked own piece → select it */
        if (this.engine.owns(v)) {
            const moves = this.engine.getMovesFrom(r,c);
            if (moves.length===0 && this.engine.mustCapture) {
                this._toast('⚠️ Взятие обязательно — выбери другую шашку');
                this._flashCapturePieces();
                return;
            }
            this.selected      = [r,c];
            this.possibleMoves = moves;
            this.render();
            return;
        }

        /* Case 3: clicked empty/enemy cell with no active move → deselect */
        this.selected = null;
        this.possibleMoves = [];
        this.render();
    }

    _doMove(move) {
        const hadCapture  = move.captures.length>0;
        const fromPiece   = this.engine.board[move.from[0]][move.from[1]];

        this.engine.applyMove(move);
        this.lastMove      = move;
        this.selected      = null;
        this.possibleMoves = [];

        if (hadCapture) this.sounds.capture();
        else            this.sounds.move();

        const newPiece = this.engine.board[move.to[0]][move.to[1]];
        if (!this.engine.isKing(fromPiece)&&this.engine.isKing(newPiece)) this.sounds.king();

        this.render();
        this._updateSidebars(move);

        if (this.options.onMove) this.options.onMove(move, this.engine);

        if (this.engine.gameOver) { this._onGameOver(); return; }

        // Trigger AI
        if (this.options.mode==='ai') {
            const aiSide = this.options.playerSide===P1?P2:P1;
            if (this.engine.turn===aiSide) setTimeout(()=>this._aiMove(), 350);
        }
    }

    _aiMove() {
        this.aiThinking=true;
        this._showAI(true);
        const depths={easy:2,medium:3,hard:4,expert:5};
        const depth=depths[this.options.difficulty]||3;
        const snap=this.engine.clone();
        setTimeout(()=>{
            try {
                const move=snap.getBestMove(depth);
                this.aiThinking=false; this._showAI(false);
                if (move&&!this.engine.gameOver) this._doMove(move);
            } catch(e) {
                this.aiThinking=false; this._showAI(false);
                const moves=this.engine.getAllMoves();
                if (moves.length) this._doMove(moves[0]);
            }
        },60);
    }

    _showAI(show) {
        const el=document.getElementById('aiThinkingIndicator');
        if(el) el.style.display=show?'flex':'none';
    }

    /* ── Sidebars ───────────────────────────────────── */
    _updateSidebars(move) {
        // Move list
        const ml=document.getElementById('moveList');
        if (ml) {
            const n=this.engine.moveHistory.length;
            const m=move;
            const note=`${String.fromCharCode(97+m.from[1])}${8-m.from[0]}→${String.fromCharCode(97+m.to[1])}${8-m.to[0]}`;
            const wasP1=this.engine.moveHistory[n-1]&&this.engine.isP1(undefined); // just track parity
            if (n%2===1) { // P1 moved (odd history length)
                const row=document.createElement('div');
                row.className='move-item';
                row.innerHTML=`<span class="move-num">${Math.ceil(n/2)}.</span><span class="move-white">${note}</span><span class="move-black"></span>`;
                ml.appendChild(row);
            } else if(ml.lastElementChild) {
                ml.lastElementChild.querySelector('.move-black').textContent=note;
            }
            ml.scrollTop=ml.scrollHeight;
        }
        const cp1=document.getElementById('capturedP1');
        const cp2=document.getElementById('capturedP2');
        if(cp1) cp1.textContent=this.engine.capturedP1;
        if(cp2) cp2.textContent=this.engine.capturedP2;
        document.getElementById('panel-p1')?.classList.toggle('active',this.engine.turn===P1);
        document.getElementById('panel-p2')?.classList.toggle('active',this.engine.turn===P2);
    }

    /* ── Timer ──────────────────────────────────────── */
    startTimer() {
        if (this.timerInterval) return;
        this.timerInterval=setInterval(()=>{
            if (this.engine.gameOver||this.aiThinking) return;
            const t=this.engine.turn;
            this.timers[t]=Math.max(0,this.timers[t]-1);
            this.renderTimers();
            if (this.timers[t]===0) {
                this.engine.gameOver=true;
                this.engine.winner=t===P1?P2:P1;
                this._onGameOver();
            }
        },1000);
    }

    renderTimers() {
        const fmt=s=>`${String(Math.floor(s/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`;
        const t1=document.getElementById('timer-p1');
        const t2=document.getElementById('timer-p2');
        if(t1){t1.textContent=fmt(this.timers[P1]);t1.classList.toggle('urgent',this.timers[P1]<=30&&this.engine.turn===P1);}
        if(t2){t2.textContent=fmt(this.timers[P2]);t2.classList.toggle('urgent',this.timers[P2]<=30&&this.engine.turn===P2);}
    }

    /* ── Game over ──────────────────────────────────── */
    _onGameOver() {
        clearInterval(this.timerInterval);
        this.timerInterval=null;
        this.sounds.win();
        this.render();
        setTimeout(()=>{ if(this.options.onGameOver) this.options.onGameOver(this.engine); },500);
    }

    handleGameOver() { this._onGameOver(); }

    /* ── Toast ──────────────────────────────────────── */
    _toast(msg) {
        let t=document.getElementById('boardToast');
        if(!t){
            t=document.createElement('div');
            t.id='boardToast';
            t.style.cssText='position:fixed;bottom:130px;left:50%;transform:translateX(-50%);background:rgba(15,15,25,.97);border:1px solid rgba(124,106,247,.5);color:#e8e8f0;padding:10px 22px;border-radius:100px;font-size:.88rem;font-weight:600;z-index:300;pointer-events:none;transition:opacity .3s;white-space:nowrap';
            document.body.appendChild(t);
        }
        t.textContent=msg; t.style.opacity='1';
        clearTimeout(this._toastT);
        this._toastT=setTimeout(()=>t.style.opacity='0',2200);
    }

    _flashCapturePieces() {
        this.engine.getAllMoves().forEach(m=>{
            const cell=document.getElementById(`cell-${m.from[0]}-${m.from[1]}`);
            if(cell){
                cell.classList.add('must-capture-flash');
                setTimeout(()=>cell.classList.remove('must-capture-flash'),800);
            }
        });
    }

    /* ── Public API ─────────────────────────────────── */
    reset() {
        clearInterval(this.timerInterval); this.timerInterval=null;
        this.engine=new CheckersEngine();
        this.selected=null; this.possibleMoves=[]; this.lastMove=null;
        this.timers=[0,this.options.timeControl,this.options.timeControl];
        this.aiThinking=false;
        this.render();
        if(this.options.mode!=='online') this.startTimer();
        const ml=document.getElementById('moveList'); if(ml) ml.innerHTML='';
    }

    setSkin(s)        { this.options.skin=s; this.render(); }
    setDifficulty(d)  { this.options.difficulty=d; }
    toggleSound(v)    { this.options.soundEnabled=v; }
    toggleHints(v)    { /* hints always on */ }
    showToast(msg)    { this._toast(msg); }
}
