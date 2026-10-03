/* ==========================================================================
   MediCore Marketing — JavaScript interactions
   - Navbar scroll effect
   - Mobile menu toggle
   - Intersection Observer reveal animations
   - Showcase tab switching
   - FAQ accordion
   - Animated counters
   Respects prefers-reduced-motion.
   ========================================================================== */
(function () {
  'use strict';

  // --- Navbar scroll effect ---
  const navbar = document.getElementById('navbar');
  if (navbar) {
    const onScroll = function () {
      if (window.scrollY > 40) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // --- Mobile menu toggle ---
  const menuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');
    });
  }

  // --- Intersection Observer for reveal animations ---
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    document.querySelectorAll('.reveal').forEach(function (el) {
      el.style.opacity = '1';
      el.style.transform = 'none';
    });
  } else {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' });
    document.querySelectorAll('.reveal').forEach(function (el) {
      observer.observe(el);
    });
  }

  // --- Showcase tab switching ---
  const showcaseContent = document.getElementById('showcase-content');
  const showcaseData = {
    dashboard: '<div class="grid gap-4 sm:grid-cols-3"><div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">12</p><p class="text-xs text-slate-400">Patients Today</p></div><div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">8</p><p class="text-xs text-slate-400">Appointments</p></div><div class="rounded-xl bg-slate-800 p-4"><p class="text-2xl font-bold text-teal-400">3</p><p class="text-xs text-slate-400">In Queue</p></div></div><div class="mt-4 space-y-2"><div class="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-2"><span class="text-sm font-medium">Sarah Chen · Cardiology</span><span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">Completed</span></div><div class="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-2"><span class="text-sm font-medium">Rahim Uddin · General</span><span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-400">In Consultation</span></div></div>',
    patients: '<div class="rounded-xl bg-slate-800 p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-navy-600 text-sm font-semibold text-white">KH</span><div><p class="text-sm font-medium text-white">Kamal Hossain</p><p class="text-xs text-slate-400">MCP-2026-00001 · 47 yrs · Male</p></div><span class="ml-auto rounded-full bg-teal-500/20 px-2 py-0.5 text-xs text-teal-400">Active</span></div></div><div class="mt-2 rounded-xl bg-slate-800 p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-rose-500/80 text-sm font-semibold text-white">NA</span><div><p class="text-sm font-medium text-white">Nasrin Akter</p><p class="text-xs text-slate-400">MCP-2026-00002 · 33 yrs · Female</p></div><span class="ml-auto rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-400">On Leave</span></div></div><div class="mt-2 rounded-xl bg-slate-800 p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-500/80 text-sm font-semibold text-white">AM</span><div><p class="text-sm font-medium text-white">Abdullah Mamun</p><p class="text-xs text-slate-400">MCP-2026-00003 · 61 yrs · Male</p></div><span class="ml-auto rounded-full bg-slate-500/20 px-2 py-0.5 text-xs text-slate-400">Archived</span></div></div>',
    calendar: '<div class="grid grid-cols-7 gap-px bg-slate-700 rounded-lg overflow-hidden"><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Sun</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Mon</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Tue</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Wed</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Thu</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Fri</p></div><div class="bg-slate-900 p-2 text-center"><p class="text-[10px] text-slate-400">Sat</p></div><div class="bg-slate-800 p-3 min-h-[60px]"><div class="rounded bg-teal-500/20 px-1 py-0.5 text-[9px] text-teal-400">09:00 Sarah</div></div><div class="bg-slate-800 p-3 min-h-[60px]"><div class="rounded bg-teal-500/20 px-1 py-0.5 text-[9px] text-teal-400">10:00 Kamal</div></div><div class="bg-slate-800 p-3 min-h-[60px]"></div><div class="bg-slate-800 p-3 min-h-[60px]"><div class="rounded bg-amber-500/20 px-1 py-0.5 text-[9px] text-amber-400">11:00 Rahim</div></div><div class="bg-slate-800 p-3 min-h-[60px]"><div class="rounded bg-teal-500/20 px-1 py-0.5 text-[9px] text-teal-400">14:00 Farhana</div></div><div class="bg-slate-800 p-3 min-h-[60px]"></div><div class="bg-slate-800 p-3 min-h-[60px]"></div></div>',
    billing: '<div class="rounded-xl bg-slate-800 p-4"><div class="flex items-center justify-between"><span class="text-sm font-medium text-white">INV-2026-00001</span><span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">Paid</span></div><p class="mt-1 text-xs text-slate-400">Kamal Hossain · ৳1,500 · Cash</p></div><div class="mt-2 rounded-xl bg-slate-800 p-4"><div class="flex items-center justify-between"><span class="text-sm font-medium text-white">INV-2026-00002</span><span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-400">Partial</span></div><p class="mt-1 text-xs text-slate-400">Nasrin Akter · ৳2,000 / ৳800 paid</p></div><div class="mt-2 rounded-xl bg-slate-800 p-4"><div class="flex items-center justify-between"><span class="text-sm font-medium text-white">INV-2026-00003</span><span class="rounded-full bg-slate-500/20 px-2 py-0.5 text-xs text-slate-400">Draft</span></div><p class="mt-1 text-xs text-slate-400">Abdullah Mamun · ৳3,500 · Pending</p></div>',
  };
  window.switchTab = function (tab, evt) {
    if (!showcaseContent) return;
    // Update active tab first
    var tabs = document.querySelectorAll('#showcase-tabs button');
    tabs.forEach(function (t) { t.classList.remove('showcase-tab-active'); });
    if (evt && evt.currentTarget) { evt.currentTarget.classList.add('showcase-tab-active'); }

    showcaseContent.style.opacity = '0';
    showcaseContent.style.transform = 'translateY(8px)';
    setTimeout(function () {
      showcaseContent.innerHTML = showcaseData[tab] || showcaseData.dashboard;
      showcaseContent.style.opacity = '1';
      showcaseContent.style.transform = 'translateY(0)';
      if (window.lucide) window.lucide.createIcons();
    }, 200);
  };

  // --- FAQ accordion ---
  window.toggleFAQ = function (index) {
    var answer = document.querySelector('.faq-answer-' + index);
    var icon = document.querySelector('.faq-icon-' + index);
    if (!answer) return;
    var isOpen = answer.classList.toggle('open');
    if (icon) {
      icon.classList.toggle('open', isOpen);
      // For Lucide-rendered icons, rotate the parent <i>
      var svg = icon.querySelector('svg');
      if (svg) {
        svg.style.transform = isOpen ? 'rotate(180deg)' : 'rotate(0)';
        svg.style.transition = 'transform 320ms cubic-bezier(.16,1,.3,1)';
      }
    }
  };

  // --- Animated counters ---
  if (!prefersReducedMotion) {
    var counters = document.querySelectorAll('[data-counter]');
    var counterObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var target = parseInt(el.dataset.counter, 10);
        var current = 0;
        var step = Math.max(1, Math.ceil(target / 40));
        var interval = setInterval(function () {
          current += step;
          if (current >= target) {
            el.textContent = target;
            clearInterval(interval);
          } else {
            el.textContent = current;
          }
        }, 30);
        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });
    counters.forEach(function (c) { counterObserver.observe(c); });
  }

  // --- Smooth anchor scrolling ---
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var href = this.getAttribute('href');
      if (href === '#' || href.length < 2) return;
      var target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // --- Lucide icons ---
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
})();
