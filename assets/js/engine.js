/**
 * CheckMasters — Checkers Engine (clean rewrite)
 */
const EMPTY = 0, P1 = 1, P2 = 2, K1 = 3, K2 = 4;

class CheckersEngine {
    constructor() {
        this.board = this._initBoard();
        this.turn = P1;          // P1 = white/light (bottom), P2 = black/dark (top)
        this.gameOver = false;
        this.winner = null;
        this.mustCapture = false;
        this.capturedP1 = 0;
        this.capturedP2 = 0;
        this.moveHistory = [];
    }

    _initBoard() {
        const b = Array.from({length:8}, () => Array(8).fill(EMPTY));
        for (let r = 0; r < 3; r++)
            for (let c = 0; c < 8; c++)
                if ((r+c)%2===1) b[r][c] = P2;
        for (let r = 5; r < 8; r++)
            for (let c = 0; c < 8; c++)
                if ((r+c)%2===1) b[r][c] = P1;
        return b;
    }

    isP1(v)    { return v===P1||v===K1; }
    isP2(v)    { return v===P2||v===K2; }
    isKing(v)  { return v===K1||v===K2; }
    owns(v, t) { return (t??this.turn)===P1 ? this.isP1(v) : this.isP2(v); }
    enemy(v,t) { return (t??this.turn)===P1 ? this.isP2(v) : this.isP1(v); }

    /* ── Move generation ─────────────────────────────── */

    getAllMoves() {
        const caps = this._allCaptures(this.turn);
        this.mustCapture = caps.length > 0;
        return caps.length > 0 ? caps : this._allSimple(this.turn);
    }

    getMovesFrom(r, c) {
        return this.getAllMoves().filter(m => m.from[0]===r && m.from[1]===c);
    }

    _allCaptures(turn) {
        const res = [];
        for (let r=0;r<8;r++) for (let c=0;c<8;c++)
            if (this.owns(this.board[r][c], turn))
                res.push(...this._captures(r,c,this.board,turn,new Set()));
        return res;
    }

    _captures(r, c, board, turn, vis) {
        const piece = board[r][c];
        if (!piece) return [];
        const res = [];
        for (const [dr,dc] of [[-1,-1],[-1,1],[1,-1],[1,1]]) {
            const er=r+dr, ec=c+dc, lr=r+2*dr, lc=c+2*dc;
            if (er<0||er>7||ec<0||ec>7||lr<0||lr>7||lc<0||lc>7) continue;
            if (!this.enemy(board[er][ec], turn)) continue;
            if (board[lr][lc]!==EMPTY) continue;
            const key=`${er},${ec}`;
            if (vis.has(key)) continue;

            const nb=board.map(row=>[...row]);
            nb[er][ec]=EMPTY; nb[r][c]=EMPTY; nb[lr][lc]=piece;
            const nv=new Set(vis); nv.add(key);

            const further=this._captures(lr,lc,nb,turn,nv);
            if (further.length>0) {
                for (const f of further)
                    res.push({from:[r,c], to:f.to, captures:[[er,ec],...f.captures]});
            } else {
                res.push({from:[r,c], to:[lr,lc], captures:[[er,ec]]});
            }
        }
        return res;
    }

    _allSimple(turn) {
        const res = [];
        for (let r=0;r<8;r++) for (let c=0;c<8;c++)
            if (this.owns(this.board[r][c], turn))
                res.push(...this._simple(r,c,turn));
        return res;
    }

    _simple(r, c, turn) {
        const piece=this.board[r][c]; if(!piece) return [];
        const fwd=turn===P1?-1:1;
        const dirs=this.isKing(piece)?[[-1,-1],[-1,1],[1,-1],[1,1]]:[[fwd,-1],[fwd,1]];
        return dirs
            .map(([dr,dc])=>[r+dr,c+dc])
            .filter(([nr,nc])=>nr>=0&&nr<=7&&nc>=0&&nc<=7&&this.board[nr][nc]===EMPTY)
            .map(([nr,nc])=>({from:[r,c],to:[nr,nc],captures:[]}));
    }

    /* ── Apply move ──────────────────────────────────── */

    applyMove(move) {
        const {from,to,captures}=move;
        const piece=this.board[from[0]][from[1]];
        this.board[from[0]][from[1]]=EMPTY;
        for (const [cr,cc] of captures) {
            const cv=this.board[cr][cc];
            this.board[cr][cc]=EMPTY;
            if (this.isP1(cv)) this.capturedP1++;
            else this.capturedP2++;
        }
        this.board[to[0]][to[1]]=piece;
        // King promotion
        if (piece===P1&&to[0]===0) this.board[to[0]][to[1]]=K1;
        if (piece===P2&&to[0]===7) this.board[to[0]][to[1]]=K2;

        this.moveHistory.push({from:[...from],to:[...to],captures:[...captures]});
        this.turn=this.turn===P1?P2:P1;
        this._checkGameOver();
        return this;
    }

    _checkGameOver() {
        const moves=this.getAllMoves();
        if (moves.length===0){this.gameOver=true;this.winner=this.turn===P1?P2:P1;return;}
        let p1=0,p2=0;
        for (const row of this.board) for (const v of row){
            if(this.isP1(v))p1++; if(this.isP2(v))p2++;
        }
        if(p1===0){this.gameOver=true;this.winner=P2;}
        if(p2===0){this.gameOver=true;this.winner=P1;}
    }

    clone() {
        const e=new CheckersEngine();
        e.board=this.board.map(r=>[...r]);
        e.turn=this.turn; e.gameOver=this.gameOver; e.winner=this.winner;
        e.mustCapture=this.mustCapture;
        e.capturedP1=this.capturedP1; e.capturedP2=this.capturedP2;
        e.moveHistory=this.moveHistory.map(m=>({...m}));
        return e;
    }

    /* ── AI ──────────────────────────────────────────── */

    evaluate() {
        let s=0;
        for (let r=0;r<8;r++) for (let c=0;c<8;c++){
            const v=this.board[r][c];
            if(v===P1) s-=(10+(7-r));
            else if(v===P2) s+=(10+r);
            else if(v===K1) s-=18;
            else if(v===K2) s+=18;
        }
        return s;
    }

    minimax(depth,alpha,beta,max){
        if(depth===0||this.gameOver) return {score:this.evaluate(),move:null};
        const moves=this.getAllMoves();
        if(!moves.length) return {score:max?-9999:9999,move:null};
        let best=max?-Infinity:Infinity, bestMove=moves[0];
        for (const mv of moves){
            const {score}=this.clone().applyMove(mv).minimax(depth-1,alpha,beta,!max);
            if(max){if(score>best){best=score;bestMove=mv;}alpha=Math.max(alpha,best);}
            else   {if(score<best){best=score;bestMove=mv;}beta=Math.min(beta,best);}
            if(beta<=alpha) break;
        }
        return {score:best,move:bestMove};
    }

    getBestMove(depth=3){
        return this.minimax(depth,-Infinity,Infinity,this.turn===P2).move;
    }

    analyzeGame(){ return []; }
}

if(typeof module!=='undefined') module.exports={CheckersEngine,EMPTY,P1,P2,K1,K2};
