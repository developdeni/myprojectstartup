/**
 * CheckMasters — Core Checkers Engine
 * Rules: International draughts (8x8 Russian/English variant)
 * AI: Minimax with alpha-beta pruning
 */

const EMPTY = 0, P1 = 1, P2 = 2, K1 = 3, K2 = 4;

class CheckersEngine {
    constructor() {
        this.board = this.initBoard();
        this.turn = P1; // P1 = light (bottom), P2 = dark (top)
        this.selected = null;
        this.possibleMoves = [];
        this.moveHistory = [];
        this.capturedP1 = 0;
        this.capturedP2 = 0;
        this.gameOver = false;
        this.winner = null;
        this.mustCapture = false;
    }

    initBoard() {
        const b = Array.from({length:8}, () => Array(8).fill(EMPTY));
        for (let r = 0; r < 3; r++)
            for (let c = 0; c < 8; c++)
                if ((r + c) % 2 === 1) b[r][c] = P2;
        for (let r = 5; r < 8; r++)
            for (let c = 0; c < 8; c++)
                if ((r + c) % 2 === 1) b[r][c] = P1;
        return b;
    }

    clone() {
        const e = new CheckersEngine();
        e.board = this.board.map(r => [...r]);
        e.turn = this.turn;
        e.capturedP1 = this.capturedP1;
        e.capturedP2 = this.capturedP2;
        e.gameOver = this.gameOver;
        e.winner = this.winner;
        return e;
    }

    isP1(v){ return v === P1 || v === K1; }
    isP2(v){ return v === P2 || v === K2; }
    isKing(v){ return v === K1 || v === K2; }
    isOwn(v, turn){ return turn === P1 ? this.isP1(v) : this.isP2(v); }
    isEnemy(v, turn){ return turn === P1 ? this.isP2(v) : this.isP1(v); }

    getDir(turn){ return turn === P1 ? -1 : 1; }

    /** Get all moves for a piece. Returns [{from,to,captures}] */
    getPieceMoves(r, c, forceCaptures = false) {
        const v = this.board[r][c];
        if (!v || !this.isOwn(v, this.turn)) return [];
        const captures = this.getPieceCaptures(r, c);
        if (captures.length > 0 || forceCaptures) return captures;
        return this.getPieceSimpleMoves(r, c);
    }

    getPieceSimpleMoves(r, c) {
        const v = this.board[r][c];
        const dirs = this.isKing(v)
            ? [[-1,-1],[-1,1],[1,-1],[1,1]]
            : [[this.getDir(this.turn), -1],[this.getDir(this.turn), 1]];
        const moves = [];
        for (const [dr, dc] of dirs) {
            const nr = r+dr, nc = c+dc;
            if (nr>=0&&nr<8&&nc>=0&&nc<8&&this.board[nr][nc]===EMPTY)
                moves.push({from:[r,c], to:[nr,nc], captures:[]});
        }
        return moves;
    }

    getPieceCaptures(r, c, visited = null, boardState = null) {
        const board = boardState || this.board;
        const v = board[r][c];
        const dirs = [[-1,-1],[-1,1],[1,-1],[1,1]];
        const captures = [];
        const vis = visited || new Set();

        for (const [dr, dc] of dirs) {
            const er = r+dr, ec = c+dc; // enemy pos
            const lr = r+2*dr, lc = c+2*dc; // landing pos
            if (er<0||er>=8||ec<0||ec>=8||lr<0||lr>=8||lc<0||lc>=8) continue;
            const enemyV = board[er][ec];
            if (!this.isEnemy(enemyV, this.turn)) continue;
            if (board[lr][lc] !== EMPTY) continue;
            const key = `${er},${ec}`;
            if (vis.has(key)) continue;

            // Simulate capture
            const nb = board.map(row => [...row]);
            nb[er][ec] = EMPTY;
            nb[r][c] = EMPTY;
            nb[lr][lc] = v;
            const newVis = new Set(vis);
            newVis.add(key);

            const further = this.getPieceCaptures(lr, lc, newVis, nb);
            if (further.length > 0) {
                for (const f of further) {
                    captures.push({
                        from:[r,c], to:f.to,
                        captures:[[er,ec], ...f.captures]
                    });
                }
            } else {
                captures.push({from:[r,c], to:[lr,lc], captures:[[er,ec]]});
            }
        }
        return captures;
    }

    /** All moves for current turn */
    getAllMoves() {
        let captures = [];
        let simples = [];
        for (let r = 0; r < 8; r++)
            for (let c = 0; c < 8; c++)
                if (this.isOwn(this.board[r][c], this.turn)) {
                    captures.push(...this.getPieceCaptures(r,c));
                    simples.push(...this.getPieceSimpleMoves(r,c));
                }
        this.mustCapture = captures.length > 0;
        return captures.length > 0 ? captures : simples;
    }

    getMovesFrom(r, c) {
        const all = this.getAllMoves();
        return all.filter(m => m.from[0]===r && m.from[1]===c);
    }

    applyMove(move) {
        const {from, to, captures} = move;
        const [fr, fc] = from;
        const [tr, tc] = to;
        const v = this.board[fr][fc];

        this.board[fr][fc] = EMPTY;
        this.board[tr][tc] = v;

        for (const [cr, cc] of captures) {
            const cv = this.board[cr][cc];
            this.board[cr][cc] = EMPTY;
            if (this.isP1(cv)) this.capturedP1++;
            else this.capturedP2++;
        }

        // King promotion
        if (v === P1 && tr === 0) this.board[tr][tc] = K1;
        if (v === P2 && tr === 7) this.board[tr][tc] = K2;

        this.moveHistory.push({...move, piece:v, turn:this.turn});
        this.turn = this.turn === P1 ? P2 : P1;
        this.checkGameOver();
        return this;
    }

    checkGameOver() {
        const moves = this.getAllMoves();
        if (moves.length === 0) {
            this.gameOver = true;
            this.winner = this.turn === P1 ? P2 : P1;
        }
        // Count pieces
        let p1 = 0, p2 = 0;
        for (const row of this.board)
            for (const v of row) {
                if (this.isP1(v)) p1++;
                if (this.isP2(v)) p2++;
            }
        if (p1 === 0) { this.gameOver = true; this.winner = P2; }
        if (p2 === 0) { this.gameOver = true; this.winner = P1; }
    }

    // =============================================
    // AI - Minimax with Alpha-Beta
    // =============================================
    evaluate() {
        let score = 0;
        for (let r = 0; r < 8; r++)
            for (let c = 0; c < 8; c++) {
                const v = this.board[r][c];
                if (v === P1) score -= (10 + (7-r)*0.5);
                else if (v === P2) score += (10 + r*0.5);
                else if (v === K1) score -= 18;
                else if (v === K2) score += 18;
            }
        // bonus for center control
        const center = [[3,3],[3,4],[4,3],[4,4]];
        for (const [r,c] of center) {
            const v = this.board[r][c];
            if (this.isP2(v)) score += 2;
            else if (this.isP1(v)) score -= 2;
        }
        return score;
    }

    minimax(depth, alpha, beta, maximizing) {
        if (depth === 0 || this.gameOver)
            return { score: this.evaluate(), move: null };
        const moves = this.getAllMoves();
        if (moves.length === 0)
            return { score: maximizing ? -Infinity : Infinity, move: null };

        let best = maximizing ? -Infinity : Infinity;
        let bestMove = moves[0];

        for (const move of moves) {
            const child = this.clone();
            child.applyMove(move);
            const { score } = child.minimax(depth - 1, alpha, beta, !maximizing);
            if (maximizing) {
                if (score > best) { best = score; bestMove = move; }
                alpha = Math.max(alpha, best);
            } else {
                if (score < best) { best = score; bestMove = move; }
                beta = Math.min(beta, best);
            }
            if (beta <= alpha) break; // Pruning
        }
        return { score: best, move: bestMove };
    }

    getBestMove(depth = 4) {
        return this.minimax(depth, -Infinity, Infinity, this.turn === P2).move;
    }

    // =============================================
    // Analysis for AI Coach
    // =============================================
    analyzeGame() {
        const insights = [];
        const tempEngine = new CheckersEngine();

        for (let i = 0; i < this.moveHistory.length; i++) {
            const move = this.moveHistory[i];
            // Check if player missed a capture
            if (move.captures.length === 0 && tempEngine.mustCapture) {
                insights.push({
                    move: i + 1,
                    type: 'missed_capture',
                    msg: `Ход ${i+1}: Вы пропустили обязательное взятие!`
                });
            }
            // Check if better move existed (compare with AI)
            const aiDepth = 2;
            const best = tempEngine.getBestMove(aiDepth);
            if (best && (best.to[0] !== move.to[0] || best.to[1] !== move.to[1])) {
                const notation = (m) => `${String.fromCharCode(97+m.from[1])}${8-m.from[0]}→${String.fromCharCode(97+m.to[1])}${8-m.to[0]}`;
                insights.push({
                    move: i + 1,
                    type: 'suboptimal',
                    msg: `Ход ${i+1}: Лучше было ${notation(best)} (твой ход: ${notation(move)})`
                });
            }
            tempEngine.applyMove(move);
        }
        return insights;
    }
}

// Export for Node (WebSocket server) or browser
if (typeof module !== 'undefined') module.exports = { CheckersEngine, EMPTY, P1, P2, K1, K2 };
