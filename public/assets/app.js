(() => {
  // Countdowns
  const pad = n => String(n).padStart(2, '0');
  const timers = [...document.querySelectorAll('[data-until]')];
  const tick = () => {
    const now = Date.now();
    for (const el of timers) {
      const t = Date.parse(el.dataset.until);
      if (!t) continue;
      let s = Math.max(0, Math.floor((t - now) / 1000));
      const d = Math.floor(s / 86400); s %= 86400;
      const h = Math.floor(s / 3600); s %= 3600;
      const m = Math.floor(s / 60); s %= 60;
      if (el.hasAttribute('data-short')) {
        el.textContent = t <= now ? 'Unlocked — refresh' : 'Unlocks in ' + (d ? d + 'd ' : '') + pad(h) + 'h ' + pad(m) + 'm';
      } else {
        el.querySelector('[data-d]').textContent = pad(d);
        el.querySelector('[data-h]').textContent = pad(h);
        el.querySelector('[data-m]').textContent = pad(m);
        el.querySelector('[data-s]').textContent = pad(s);
      }
    }
  };
  if (timers.length) { tick(); setInterval(tick, 1000); }

  // Active month chip while scrolling.
  // Only the chip bar is scrolled sideways — never the page — so the page's
  // momentum scroll on phones isn't interrupted when a new month comes into view.
  const nav = document.querySelector('.months');
  const links = new Map([...document.querySelectorAll('[data-month-link]')].map(a => [a.dataset.monthLink, a]));
  let current = null;
  const io = new IntersectionObserver(entries => {
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      const a = links.get(e.target.dataset.month);
      if (!a || a === current) continue;
      current?.classList.remove('on');
      a.classList.add('on');
      current = a;
      const left = a.offsetLeft, right = left + a.offsetWidth;
      if (nav && (left < nav.scrollLeft || right > nav.scrollLeft + nav.clientWidth)) {
        nav.scrollTo({ left: left - (nav.clientWidth - a.offsetWidth) / 2, behavior: 'smooth' });
      }
    }
  }, { rootMargin: '-140px 0px -60% 0px' });
  document.querySelectorAll('[data-month]').forEach(m => io.observe(m));

  // Search filter
  const q = document.querySelector('[data-search]');
  const none = document.querySelector('[data-noresults]');
  q?.addEventListener('input', () => {
    const term = q.value.trim().toLowerCase();
    let any = false;
    document.querySelectorAll('[data-month]').forEach(month => {
      let monthHit = false;
      month.querySelectorAll('.week').forEach(week => {
        let weekHit = false;
        week.querySelectorAll('.card').forEach(c => {
          const hit = !term || c.dataset.title.includes(term);
          c.hidden = !hit; weekHit ||= hit;
        });
        week.hidden = !weekHit; monthHit ||= weekHit;
      });
      month.hidden = !monthHit; any ||= monthHit;
    });
    none.hidden = any;
  });
})();
