/* ==========================================================================
   MediCore admin chrome — Alpine components, toasts, theme, search,
   notifications, Chart.js wiring and icon refresh.
   Loaded with `defer` on every page.
   ========================================================================== */
(function () {
    'use strict';

    // Absolute base for fetch() calls; layouts inject the real value.
    window.MEDICORE_BASE = window.MEDICORE_BASE || '';

    var Medicore = {
        /** Fire an info toast (used for "module coming soon" actions). */
        toast: function (type, title, message) {
            document.dispatchEvent(new CustomEvent('medicore:toast', {
                detail: { type: type, title: title, message: message }
            }));
        },

        /** Re-render lucide icons (safe to call repeatedly). */
        refreshIcons: function () {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        },

        /** CSRF token from the meta tag (for fetch calls). */
        csrf: function () {
            var tag = document.querySelector('meta[name="csrf-token"]');
            return tag ? tag.getAttribute('content') : '';
        }
    };
    window.MedicCore = window.Medicore = Medicore; // both spellings safe

    /* ------------------------------------------------------------------
       Server-side flash messages -> toasts
       (layout injects window.__flashes = [{type, message}])
    ------------------------------------------------------------------ */
    document.addEventListener('alpine:init', function () {
        Alpine.data('layout', function () {
            return {
                sidebarOpen: false,
                collapsed: localStorage.getItem('medicore-sidebar') === '1',

                toggleCollapse: function () {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('medicore-sidebar', this.collapsed ? '1' : '0');
                },

                toggleTheme: function () {
                    var isDark = document.documentElement.classList.toggle('dark');
                    localStorage.setItem('medicore-theme', isDark ? 'dark' : 'light');
                    if (window.MediCoreCharts) window.MediCoreCharts.retheme();
                },

                soon: function (name) {
                    Medicore.toast('info', name, 'Planned module — arriving in an upcoming development phase.');
                }
            };
        });

        Alpine.data('globalSearch', function () {
            return {
                query: '',
                open: false,
                loading: false,
                results: [],
                modules: [],

                search: function () {
                    var self = this;
                    if (self.query.trim().length < 2) {
                        self.results = [];
                        self.modules = [];
                        return;
                    }
                    self.loading = true;
                    fetch(MEDICORE_BASE + '/api/search?q=' + encodeURIComponent(self.query), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            self.results = (json.data && json.data.groups) || [];
                            self.modules = (json.data && json.data.modules) || [];
                            self.open = true;
                            self.loading = false;
                            Medicore.refreshIcons();
                        })
                        .catch(function () { self.loading = false; });
                }
            };
        });

        Alpine.data('notifications', function () {
            return {
                open: false,
                unread: 0,
                items: [],

                load: function () {
                    var self = this;
                    fetch(MEDICORE_BASE + '/api/notifications', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            self.items = (json.data && json.data.items) || [];
                            self.unread = (json.data && json.data.unread) || 0;
                            Medicore.refreshIcons();
                        })
                        .catch(function () { /* silent */ });
                },

                toggle: function () {
                    this.open = !this.open;
                    if (this.open) {
                        this.unread = 0; // viewed -> seen
                        Medicore.refreshIcons();
                    }
                }
            };
        });

        Alpine.data('confirmDialog', function () {
            return {
                open: false,
                title: '',
                message: '',
                danger: false,
                _resolve: null,

                ask: function (title, message, danger) {
                    var self = this;
                    this.title = title;
                    this.message = message;
                    this.danger = !!danger;
                    this.open = true;
                    Medicore.refreshIcons();
                    return new Promise(function (resolve) {
                        self._resolve = resolve;
                    });
                },

                resolve: function (value) {
                    this.open = false;
                    if (this._resolve) {
                        this._resolve(value);
                        this._resolve = null;
                    }
                }
            };
        });

        Alpine.data('patientDupes', function (opts) {
            return {
                first_name: '', last_name: '', date_of_birth: '', phone: '', national_id: '',
                duplicates: [], timer: null, editId: (opts && opts.editId) || 0,

                init: function () {
                    var self = this;
                    ['first_name', 'last_name', 'date_of_birth', 'phone', 'national_id'].forEach(function (key) {
                        self.$watch(key, function () { self.schedule(); });
                    });
                },

                schedule: function () {
                    var self = this;
                    clearTimeout(this.timer);
                    // Only check once enough identity signal exists.
                    var signals = [this.phone, this.national_id,
                        (this.first_name && this.last_name && this.date_of_birth) ? 'x' : ''].filter(Boolean).length;
                    if (signals === 0) { this.duplicates = []; return; }
                    this.timer = setTimeout(function () { self.check(); }, 450);
                },

                check: function () {
                    var self = this;
                    fetch(MEDICORE_BASE + '/api/patients/duplicates', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                        },
                        body: JSON.stringify({
                            first_name: this.first_name,
                            last_name: this.last_name,
                            date_of_birth: this.date_of_birth,
                            phone: this.phone,
                            national_id: this.national_id,
                            ignore_id: this.editId
                        })
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            self.duplicates = (json.data && json.data.duplicates) || [];
                        })
                        .catch(function () { /* silent */ });
                }
            };
        });

        Alpine.data('toasts', function () {
            return {
                list: [],
                nextId: 1,

                init: function () {
                    var self = this;
                    document.addEventListener('medicore:toast', function (e) {
                        self.push(e.detail.type, e.detail.title, e.detail.message);
                    });
                    (window.__flashes || []).forEach(function (f) {
                        self.push(f.type === 'error' ? 'error' : (f.type === 'success' ? 'success' : 'info'), null, f.message);
                    });
                },

                push: function (type, title, message) {
                    var id = this.nextId++;
                    var toast = { id: id, type: type || 'info', title: title || this.defaultTitle(type), message: message || '', visible: false };
                    this.list.push(toast);
                    var self = this;
                    requestAnimationFrame(function () {
                        var t = self.list.find(function (x) { return x.id === id; });
                        if (t) t.visible = true;
                    });
                    setTimeout(function () { self.dismiss(id); }, 5200);
                    Medicore.refreshIcons();
                },

                defaultTitle: function (type) {
                    return { success: 'Success', error: 'Error', info: 'Heads up' }[type] || 'Notice';
                },

                dismiss: function (id) {
                    this.list = this.list.filter(function (t) { return t.id !== id; });
                }
            };
        });
    });

    /* ------------------------------------------------------------------
       On ready
    ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        Medicore.refreshIcons();

        // Keep icons fresh as Alpine injects/removes DOM (dropdowns, toasts).
        if ('MutationObserver' in window) {
            var pending = false;
            var observer = new MutationObserver(function () {
                if (pending) return;
                pending = true;
                requestAnimationFrame(function () {
                    pending = false;
                    Medicore.refreshIcons();
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        // Ctrl/Cmd + K focuses the global search.
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                var input = document.getElementById('global-search');
                if (input) {
                    e.preventDefault();
                    input.focus();
                }
            }
            // "[" toggles the sidebar (desktop).
            if (e.key === '[' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
                var toggle = document.querySelector('[aria-label="Toggle sidebar"]');
                if (toggle) toggle.click();
            }
        });

        // Password visibility toggles (login).
        document.querySelectorAll('.password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show
                    ? '<i data-lucide="eye-off" class="h-4 w-4"></i>'
                    : '<i data-lucide="eye" class="h-4 w-4"></i>';
                Medicore.refreshIcons();
            });
        });

        // Confirmation dialogs — any <form data-confirm="Title|Message[|danger]">
        // opens the modal and only submits after explicit confirmation.
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') {
                    return; // user already confirmed this submission
                }
                e.preventDefault();
                var parts = form.dataset.confirm.split('|');
                var host = document.querySelector('[x-data^="confirmDialog"]');
                if (!host || !window.Alpine) {
                    form.submit(); // graceful fallback: submit directly
                    return;
                }
                var dialog = window.Alpine.$data(host);
                dialog.ask(parts[0] || 'Please confirm', parts.slice(1).join('|') || 'Are you sure?', parts.includes('danger'))
                    .then(function (ok) {
                        if (ok) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
            });
        });

        // Role-matrix helpers: toggle-all (scope = whole matrix or module) & clear.
        document.querySelectorAll('.select-all').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var scope = document.getElementById(btn.dataset.scope);
                if (!scope) return;
                var boxes = scope.querySelectorAll('input[type="checkbox"]');
                var allChecked = Array.prototype.every.call(boxes, function (cb) { return cb.checked; });
                boxes.forEach(function (cb) {
                    cb.checked = !allChecked;
                });
            });
        });
        document.querySelectorAll('.select-none-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                if (!form) return;
                form.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                    cb.checked = false;
                });
            });
        });

        initCharts();
    });

    /* ------------------------------------------------------------------
       Chart.js — only renders canvases that exist (real data only)
       Stores chart instances for theme re-rendering on dark mode toggle.
    ------------------------------------------------------------------ */
    window.MediCoreCharts = {
        instances: [],
        retheme: function () {
            this.instances.forEach(function (c) { c.destroy(); });
            this.instances = [];
            initCharts();
        }
    };

    function initCharts() {
        if (typeof window.Chart === 'undefined') return;

        var isDark = document.documentElement.classList.contains('dark');
        var gridColor = isDark ? 'rgba(148,163,184,.14)' : 'rgba(100,116,139,.14)';
        var tickColor = isDark ? '#94a3b8' : '#64748b';
        var tooltipBg = isDark ? 'rgba(10, 20, 35, .95)' : 'rgba(11, 31, 58, .94)';

        var appointments = document.getElementById('appointmentsChart');
        if (appointments) {
            var labels = JSON.parse(appointments.dataset.labels || '[]');
            var values = JSON.parse(appointments.dataset.values || '[]');

            // Build gradient
            var ctx = appointments.getContext('2d');
            var gradient = ctx.createLinearGradient(0, 0, 0, 180);
            gradient.addColorStop(0, 'rgba(20, 184, 166, 0.25)');
            gradient.addColorStop(1, 'rgba(20, 184, 166, 0.02)');

            var inst = new window.Chart(appointments, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Appointments',
                        data: values,
                        borderColor: '#14b8a6',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#14b8a6',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }]
                },
                options: chartOptions(gridColor, tickColor, tooltipBg, {
                    stepSize: 1,
                    animation: { duration: 800, easing: 'easeOutQuart' },
                    interaction: { mode: 'index', intersect: false }
                })
            });
            window.MediCoreCharts.instances.push(inst);
        }

        var revenue = document.getElementById('revenueChart');
        if (revenue) {
            var rLabels = JSON.parse(revenue.dataset.labels || '[]');
            var rValues = JSON.parse(revenue.dataset.values || '[]');

            var rCtx = revenue.getContext('2d');
            var rGradient = rCtx.createLinearGradient(0, 0, 0, 180);
            rGradient.addColorStop(0, 'rgba(58, 115, 166, 0.9)');
            rGradient.addColorStop(1, 'rgba(58, 115, 166, 0.5)');

            var rInst = new window.Chart(revenue, {
                type: 'bar',
                data: {
                    labels: rLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: rValues,
                        backgroundColor: rGradient,
                        hoverBackgroundColor: '#2a5a88',
                        borderRadius: 8,
                        maxBarThickness: 38,
                        borderWidth: 0
                    }]
                },
                options: chartOptions(gridColor, tickColor, tooltipBg, {
                    animation: { duration: 900, easing: 'easeOutQuart' }
                })
            });
            window.MediCoreCharts.instances.push(rInst);
        }
    }

    function chartOptions(gridColor, tickColor, tooltipBg, extra) {
        return Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: tooltipBg,
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    borderColor: 'rgba(45, 212, 191, .25)',
                    borderWidth: 1,
                    displayColors: false,
                    titleColor: '#fff',
                    bodyColor: 'rgba(255, 255, 255, .85)'
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: tickColor, font: { size: 11 } },
                    border: { display: false }
                },
                y: {
                    grid: { color: gridColor, drawTicks: false },
                    ticks: { color: tickColor, font: { size: 11 }, padding: 8 },
                    border: { display: false }
                }
            }
        }, extra);
    }
})();
