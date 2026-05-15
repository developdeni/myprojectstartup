// Main JS - Landing page
document.addEventListener('DOMContentLoaded', () => {
  // Navbar scroll
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    navbar?.classList.toggle('scrolled', window.scrollY > 50);
  });

  // Animated counters
  document.querySelectorAll('.stat-number[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target);
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    const obs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) {
        const timer = setInterval(() => {
          current = Math.min(current + step, target);
          el.textContent = Math.floor(current).toLocaleString();
          if (current >= target) clearInterval(timer);
        }, 16);
        obs.disconnect();
      }
    });
    obs.observe(el);
  });

  // Demo board
  buildDemoBoard();

  // Leaderboard tabs
  document.querySelectorAll('.lb-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.lb-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });

  // Mobile menu
  document.getElementById('navBurger')?.addEventListener('click', () => {
    document.querySelector('.nav-links')?.classList.toggle('nav-open');
    document.querySelector('.nav-actions')?.classList.toggle('nav-open');
  });
});

function buildDemoBoard() {
  const board = document.getElementById('demoBoardHero');
  if (!board) return;

  // Initial checkers position
  const initialPieces = {
    1:[1,3,5,7], 2:[0,2,4,6], 3:[1,3,5,7],  // player2 (dark)
    5:[0,2,4,6], 6:[1,3,5,7], 7:[0,2,4,6],   // player1 (light)
  };

  for (let r = 0; r < 8; r++) {
    for (let c = 0; c < 8; c++) {
      const cell = document.createElement('div');
      cell.className = `cell ${(r + c) % 2 === 0 ? 'light' : 'dark'}`;
      if ((r + c) % 2 === 1) {
        let player = 0;
        if (r <= 2 && initialPieces[r]?.includes(c)) player = 2;
        if (r >= 5 && initialPieces[r]?.includes(c)) player = 1;
        if (player) {
          const piece = document.createElement('div');
          piece.className = `piece p${player}`;
          if ((r === 1 && c === 3) || (r === 6 && c === 4)) piece.classList.add('highlight');
          cell.appendChild(piece);
        }
      }
      board.appendChild(cell);
    }
  }

  // Animate a move
  let step = 0;
  setInterval(() => {
    board.querySelectorAll('.piece').forEach(p => p.classList.remove('highlight'));
    const pieces = board.querySelectorAll('.piece');
    if (pieces.length > 0) {
      pieces[step % pieces.length].classList.add('highlight');
      step++;
    }
  }, 1500);
}
