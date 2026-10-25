/* AlloPrint — scripts partagés (file d'animation, défilement, effets, navigation) */
/* motion-queue */
(() => {
  'use strict';
  const fonts = document.fonts ? document.fonts.ready : Promise.resolve();
  const parsed = document.readyState === 'loading' ? new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve, { once: true })) : Promise.resolve();
  const ready = () => Promise.all([fonts, parsed]).then(() => firsts);
  const nextTask = () => window.scheduler?.yield?.() ?? new Promise(resolve => setTimeout(resolve));
  const refresh = () => {
    if (typeof window.refreshScroll === 'function') return window.refreshScroll();
    window.ScrollTrigger?.sort(); window.ScrollTrigger?.refresh();
  };
  const run = (setup, name) => {
    for (const trigger of window.ScrollTrigger?.getAll() || []) if (!trigger.end && window.ScrollTrigger.getAll().includes(trigger)) trigger.refresh(0, 1);
    const context = window.gsap.context(() => {});
    const log = new MutationObserver(() => {});
    log.observe(document.documentElement, { attributes: true, attributeOldValue: true, subtree: true });
    let failure = null, records;
    try { context.add(() => { try { setup(); } catch (error) { failure = error; } }); }
    finally { records = log.takeRecords(); log.disconnect(); }
    if (!failure) return true;
    try { context.revert(); } catch (error) { console.error(name, 'revert failed', error); }
    for (const { target, attributeName, oldValue } of records.reverse()) {
      const now = target.getAttribute(attributeName);
      if (oldValue == null) { if (now != null) target.removeAttribute(attributeName); }
      else if (now !== oldValue) target.setAttribute(attributeName, oldValue);
    }
    console.error(name, failure);
    return false;
  };
  let firsts = Promise.resolve(), tail = null, pending = 0, lazy = null, building = 0, firstCount = 0;
  window.motionReady = ready();
  parsed.then(() => { if (firstCount && !tail) firsts.then(refresh); });
  window.queueMotion = (setup, { first = false, near, id } = {}) => {
    if (!window.gsap || typeof setup !== 'function') return;
    const name = id || document.currentScript?.getAttribute('data-8b-part') || 'motion';
    if (near instanceof Element && window.IntersectionObserver) {
      const watch = new IntersectionObserver(entries => {
        if (!entries.some(entry => entry.isIntersecting)) return;
        watch.disconnect();
        building++;
        lazy = (lazy || ready()).then(nextTask).then(() => run(setup, name)).finally(() => { if (!--building) refresh(); });
      }, { rootMargin: '100% 0px' });
      watch.observe(near);
      return;
    }
    if (first) { firstCount++; firsts = firsts.then(() => fonts).then(() => { if (!run(setup, name)) refresh(); }); }
    else {
      pending++;
      tail = (tail || ready()).then(nextTask).then(() => run(setup, name))
        .finally(() => { if (!--pending) refresh(); });
    }
    window.motionReady = Promise.all([firsts, tail]);
  };
})();

/* page-init */
gsap.registerPlugin(ScrollTrigger);
function navOffset() {
  const shell = document.querySelector('#nav .nav__shell');
  return shell ? shell.offsetHeight : 0;
}
function refreshScroll() {
  ScrollTrigger.sort();
  ScrollTrigger.refresh();
}
gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
  const lenis = window.lenis = new Lenis({ lerp: 0.09, anchors: { offset: -navOffset() } });
  const tick = time => lenis.raf(time * 1000);
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add(tick);
  gsap.ticker.lagSmoothing(0);
  return () => { gsap.ticker.remove(tick); gsap.ticker.lagSmoothing(500, 33); lenis.destroy(); window.lenis = null; };
});

/* motion */
const effects = {
  register(element, vars = {}) {
    return gsap.fromTo(element,
      { '--register-x': '0.11em', '--register-y': '0.06em', '--register-a': 1 },
      { '--register-x': '0em', '--register-y': '0em', '--register-a': 0, ease: 'power2.out', ...vars });
  },
  screen(element, vars = {}) {
    return gsap.fromTo(element, { '--screen-dot': '0%' }, { '--screen-dot': '150%', duration: 1.2, ease: 'power1.in', ...vars });
  }
};
function registerEnd(element) {
  const top = element.getBoundingClientRect().top + window.scrollY;
  return Math.min(top - innerHeight * 0.55, ScrollTrigger.maxScroll(window) - 1);
}
document.querySelectorAll('[data-8b-section]').forEach(section => {
  const plates = section.querySelectorAll('[data-8b-motion~="register"]');
  if (plates.length) {
    const id = `effect:register:${section.id}`;
    queueMotion(() => gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
      plates.forEach((element, index) => ScrollTrigger.create({
        id: `${id}:${index}`, trigger: element, start: 'top 95%', end: () => registerEnd(element),
        scrub: 0.5, animation: effects.register(element)
      }));
    }), { first: section.getBoundingClientRect().top < innerHeight, id });
  }
  const screens = section.querySelectorAll('[data-8b-motion~="screen"]');
  if (screens.length) {
    const id = `effect:screen:${section.id}`;
    queueMotion(() => gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
      screens.forEach((element, index) => {
        element.classList.add('is-screening');
        const tween = effects.screen(element, { paused: true, onComplete: () => element.classList.remove('is-screening') });
        ScrollTrigger.create({ id: `${id}:${index}`, trigger: element, start: 'top 88%', once: true, onEnter: () => tween.play() });
      });
      return () => screens.forEach(element => element.classList.remove('is-screening'));
    }), { first: section.getBoundingClientRect().top < innerHeight, id });
  }
});

/* section-script:nav */
(() => {
  const nav = document.getElementById('nav');
  const toggle = nav.querySelector('.nav__toggle');
  const close = nav.querySelector('.nav__close');
  const menu = document.getElementById('menu');
  let last = window.scrollY;
  const onScroll = () => {
    const y = window.scrollY;
    if (Math.abs(y - last) > 2) {
      nav.toggleAttribute('data-hidden', y > last && y > 160 && menu.hidden);
      last = y;
    }
    nav.toggleAttribute('data-scrolled', y > 24);
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  const setOpen = open => {
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (open) { window.lenis?.stop(); close.focus(); } else { window.lenis?.start(); }
  };
  toggle.addEventListener('click', () => setOpen(true));
  close.addEventListener('click', () => { setOpen(false); toggle.focus(); });
  menu.addEventListener('click', event => { if (event.target.closest('a')) setOpen(false); });
  addEventListener('keydown', event => { if (event.key === 'Escape' && !menu.hidden) { setOpen(false); toggle.focus(); } });
})();
/* section-script:request — formulaires devis et contact : ouvrent la messagerie avec la demande remplie */
(() => {
  const form = document.querySelector('.request__form');
  if (!form) return;
  const status = form.querySelector('[data-sent]');
  const service = form.querySelector('select[name="service"]');
  const wanted = new URLSearchParams(location.search).get('service');
  if (service && wanted && service.querySelector(`option[value="${CSS.escape(wanted)}"]`)) service.value = wanted;
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const lines = [...form.querySelectorAll('[data-line]')]
      .filter(field => field.value.trim())
      .map(field => `${field.dataset.line} : ${field.tagName === 'SELECT' ? field.selectedOptions[0].textContent : field.value.trim()}`);
    const subject = `${form.dataset.subject} — ${form.elements.name.value.trim()}`;
    location.href = `${form.getAttribute('action')}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`;
    status.hidden = false;
  });
})();
