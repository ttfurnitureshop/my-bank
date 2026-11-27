<?php
session_start();
// Optional: require 'config.php';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>About Us • MyBank</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .hero-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #2563eb 100%);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 overflow-x-hidden min-h-screen">

<!-- NAVIGATION -->
<nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-5 flex items-center justify-between relative">
        <div class="flex items-center w-12 lg:w-auto">
            <button id="mobile-menu-btn" class="lg:hidden text-xl p-2 rounded-xl hover:bg-slate-800 transition" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <a href="login.php" class="absolute left-1/2 -translate-x-1/2 lg:static lg:translate-x-0 flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-600 rounded-xl sm:rounded-2xl flex items-center justify-center text-xl sm:text-3xl font-bold">M</div>
            <span class="text-xl sm:text-3xl font-bold tracking-tighter">MyBank</span>
        </a>

        <div class="hidden lg:flex items-center gap-8 text-lg flex-1 justify-center">
            <a href="about.php" class="text-blue-400 font-semibold">About</a>
            <a href="credit.php" class="hover:text-blue-400 transition">Credit Cards</a>
            <a href="signup.php" class="hover:text-blue-400 transition">Banking</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Transfers</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Investments</a>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0 w-12 lg:w-auto justify-end">
            <a href="dashboard.php" class="hidden sm:inline-flex px-4 py-2 sm:px-6 text-sm sm:text-base font-semibold border border-slate-300 hover:border-white rounded-2xl transition whitespace-nowrap">Sign In</a>
            <a href="signup.php" class="hidden sm:inline-flex px-4 py-2 sm:px-6 text-sm sm:text-base bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition whitespace-nowrap">Open an Account</a>
            <div class="sm:hidden w-10"></div>
        </div>
    </div>
</nav>

<!-- MOBILE MENU -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>
<div id="mobile-menu" class="fixed top-0 left-0 h-full w-[85%] max-w-sm bg-slate-900 z-[70] transform -translate-x-full transition-transform duration-300 ease-out shadow-2xl lg:hidden flex flex-col">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-xl flex items-center justify-center text-xl font-bold">M</div>
            <span class="text-xl font-bold tracking-tighter">MyBank</span>
        </div>
        <button id="mobile-menu-close" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-800 transition text-xl" aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto py-4 px-3">
        <a href="about.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl bg-slate-800/80 transition">
            <div class="w-10 h-10 rounded-xl bg-blue-600/20 flex items-center justify-center text-blue-400"><i class="fa-solid fa-building"></i></div>
            <div><span class="font-semibold block">About Us</span><span class="text-xs text-slate-400">Our story & mission</span></div>
        </a>
        <a href="credit.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-amber-600/20 flex items-center justify-center text-amber-400"><i class="fa-solid fa-credit-card"></i></div>
            <div><span class="font-semibold block">Credit Cards</span><span class="text-xs text-slate-400">Rewards that matter</span></div>
        </a>
        <a href="signup.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 flex items-center justify-center text-emerald-400"><i class="fa-solid fa-user-plus"></i></div>
            <div><span class="font-semibold block">Open an Account</span><span class="text-xs text-slate-400">Start in minutes</span></div>
        </a>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-cyan-600/20 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-right-to-bracket"></i></div>
            <div><span class="font-semibold block">Sign In</span><span class="text-xs text-slate-400">Access your account</span></div>
        </a>
        <a href="support-login.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition">
            <div class="w-10 h-10 rounded-xl bg-rose-600/20 flex items-center justify-center text-rose-400"><i class="fa-solid fa-headset"></i></div>
            <div><span class="font-semibold block">Contact Support</span><span class="text-xs text-slate-400">We're here 24/7</span></div>
        </a>
    </div>
    <div class="px-5 py-4 border-t border-slate-800">
        <a href="signup.php" class="block w-full text-center py-3.5 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition">Get Started Free</a>
    </div>
</div>

<!-- HERO -->
<section class="hero-bg py-16 sm:py-24">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-blue-300 font-medium text-sm sm:text-base mb-3 tracking-wide uppercase">About MyBank</p>
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tighter leading-tight mb-5">
            Banking built for<br class="hidden sm:block"> real life
        </h1>
        <p class="text-base sm:text-xl text-slate-300 max-w-2xl mx-auto">
            We're a modern bank focused on clear products, strong security, and tools that help you move money, grow savings, and spend smarter — without the noise.
        </p>
    </div>
</section>

<!-- STATS -->
<section class="py-12 border-b border-slate-800 bg-slate-900/50">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
            <div>
                <p class="text-3xl sm:text-4xl font-bold text-blue-400">24/7</p>
                <p class="text-sm text-slate-400 mt-1">Customer support</p>
            </div>
            <div>
                <p class="text-3xl sm:text-4xl font-bold text-emerald-400">FDIC</p>
                <p class="text-sm text-slate-400 mt-1">Insured deposits*</p>
            </div>
            <div>
                <p class="text-3xl sm:text-4xl font-bold text-violet-400">$0</p>
                <p class="text-sm text-slate-400 mt-1">Monthly account fees†</p>
            </div>
            <div>
                <p class="text-3xl sm:text-4xl font-bold text-amber-400">Bank-grade</p>
                <p class="text-sm text-slate-400 mt-1">Security & encryption</p>
            </div>
        </div>
    </div>
</section>

<!-- OUR STORY -->
<section class="py-14 sm:py-20">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div>
                <p class="text-blue-400 text-sm font-semibold uppercase tracking-wider mb-2">Our story</p>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-5">Why MyBank exists</h2>
                <p class="text-slate-300 leading-relaxed mb-4">
                    Traditional banking often means hidden fees, slow transfers, and apps that feel stuck in the past. MyBank was built to change that — starting with checking and savings that are simple to open, transparent to use, and protected with modern security.
                </p>
                <p class="text-slate-300 leading-relaxed mb-4">
                    From everyday spending and Zelle-style transfers to credit cards, crypto tools, and investments, we bring the products you need into one clear experience — so you spend less time managing money and more time living your life.
                </p>
                <p class="text-slate-400 text-sm leading-relaxed">
                    *Deposits are FDIC insured up to applicable limits when held at our partner bank. †Fee structure may vary by product; see account disclosures for details.
                </p>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10">
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-blue-600/20 flex items-center justify-center text-blue-400 flex-shrink-0">
                            <i class="fa-solid fa-bullseye text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Our mission</h3>
                            <p class="text-sm text-slate-400">Make everyday banking faster, safer, and easier to understand for everyone.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-600/20 flex items-center justify-center text-emerald-400 flex-shrink-0">
                            <i class="fa-solid fa-eye text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Our vision</h3>
                            <p class="text-sm text-slate-400">A world where managing money feels as simple as messaging a friend.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-violet-600/20 flex items-center justify-center text-violet-400 flex-shrink-0">
                            <i class="fa-solid fa-handshake text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-lg mb-1">Our promise</h3>
                            <p class="text-sm text-slate-400">Clear terms, responsive support, and tools that put you in control.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- VALUES -->
<section class="py-14 sm:py-20 bg-slate-900">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <p class="text-blue-400 text-sm font-semibold uppercase tracking-wider mb-2">What we stand for</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Our values</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-slate-950/60 border border-slate-800 rounded-3xl p-6 hover:border-blue-500/40 transition">
                <div class="w-12 h-12 rounded-2xl bg-blue-600/20 flex items-center justify-center text-blue-400 text-xl mb-4">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Security first</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Encryption, monitoring, and controls designed to protect your accounts and data every day.</p>
            </div>
            <div class="bg-slate-950/60 border border-slate-800 rounded-3xl p-6 hover:border-emerald-500/40 transition">
                <div class="w-12 h-12 rounded-2xl bg-emerald-600/20 flex items-center justify-center text-emerald-400 text-xl mb-4">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Transparency</h3>
                <p class="text-sm text-slate-400 leading-relaxed">No surprises. We explain fees, limits, and terms in plain language before you commit.</p>
            </div>
            <div class="bg-slate-950/60 border border-slate-800 rounded-3xl p-6 hover:border-violet-500/40 transition">
                <div class="w-12 h-12 rounded-2xl bg-violet-600/20 flex items-center justify-center text-violet-400 text-xl mb-4">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Speed</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Open accounts quickly, transfer funds efficiently, and get answers when you need them.</p>
            </div>
            <div class="bg-slate-950/60 border border-slate-800 rounded-3xl p-6 hover:border-amber-500/40 transition">
                <div class="w-12 h-12 rounded-2xl bg-amber-600/20 flex items-center justify-center text-amber-400 text-xl mb-4">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">People-first</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Real support, thoughtful product design, and features shaped by how customers actually bank.</p>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCTS SNAPSHOT -->
<section class="py-14 sm:py-20">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <p class="text-blue-400 text-sm font-semibold uppercase tracking-wider mb-2">What we offer</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Banking products in one place</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="signup.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-blue-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-solid fa-piggy-bank text-2xl text-cyan-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Checking & Savings</h3>
                <p class="text-sm text-slate-400">Everyday accounts with clear balances and easy transfers.</p>
            </a>
            <a href="credit.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-violet-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-solid fa-credit-card text-2xl text-violet-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Credit Cards</h3>
                <p class="text-sm text-slate-400">Rewards, travel, and credit-builder options with soft pre-approval.</p>
            </a>
            <a href="dashboard.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-emerald-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-solid fa-arrow-right-arrow-left text-2xl text-emerald-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Transfers & ACH</h3>
                <p class="text-sm text-slate-400">Move money between accounts and linked external banks.</p>
            </a>
            <a href="crypto.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-amber-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-brands fa-bitcoin text-2xl text-amber-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Crypto tools</h3>
                <p class="text-sm text-slate-400">Buy, sell, and track digital assets alongside your fiat balance.</p>
            </a>
            <a href="dashboard.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-rose-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-solid fa-chart-line text-2xl text-rose-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Investments</h3>
                <p class="text-sm text-slate-400">Grow wealth with investment options from your dashboard.</p>
            </a>
            <a href="support-login.php" class="block bg-slate-900 border border-slate-800 rounded-3xl p-6 hover:border-blue-500/40 hover:-translate-y-0.5 transition">
                <i class="fa-solid fa-headset text-2xl text-blue-400 mb-3"></i>
                <h3 class="font-semibold text-lg mb-1">Support</h3>
                <p class="text-sm text-slate-400">Help when you need it — day or night.</p>
            </a>
        </div>
    </div>
</section>

<!-- TRUST / SECURITY -->
<section class="py-14 sm:py-20 bg-slate-900">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-emerald-600/20 flex items-center justify-center text-3xl text-emerald-400">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">Trust & security</h2>
        <p class="text-slate-300 leading-relaxed mb-6">
            MyBank uses industry-standard encryption, secure sessions, and careful account monitoring. Eligible deposits are FDIC insured up to applicable limits through our banking partners. We never ask for your password by email or text.
        </p>
        <div class="flex flex-wrap justify-center gap-3 text-sm text-slate-400">
            <span class="px-4 py-2 rounded-full bg-slate-800 border border-slate-700">FDIC insured*</span>
            <span class="px-4 py-2 rounded-full bg-slate-800 border border-slate-700">Bank-grade security</span>
            <span class="px-4 py-2 rounded-full bg-slate-800 border border-slate-700">24/7 support</span>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-14 sm:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">Ready to bank with MyBank?</h2>
        <p class="text-slate-400 mb-8">Open an account in minutes or explore our credit cards and products.</p>
        <div class="flex flex-col sm:flex-row flex-wrap justify-center gap-3">
            <a href="signup.php" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition">Open an Account</a>
            <a href="credit.php" class="px-8 py-4 border border-slate-600 hover:border-white font-semibold rounded-2xl transition">See Credit Cards</a>
            <a href="dashboard.php" class="px-8 py-4 border border-slate-600 hover:border-white font-semibold rounded-2xl transition">Sign In</a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="bg-slate-900 border-t border-slate-800 py-10">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 text-slate-400 text-sm">
        <div class="flex flex-col sm:flex-row justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-7 h-7 bg-blue-600 rounded-xl flex items-center justify-center text-lg font-bold text-white">M</div>
                    <span class="text-lg font-bold text-white tracking-tighter">MyBank</span>
                </div>
                <p class="text-xs">© 2026 MyBank. All rights reserved.</p>
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <a href="login.php" class="hover:text-white">Home</a>
                <a href="about.php" class="hover:text-white">About</a>
                <a href="credit.php" class="hover:text-white">Credit Cards</a>
                <a href="signup.php" class="hover:text-white">Open Account</a>
                <a href="support-login.php" class="hover:text-white">Support</a>
            </div>
        </div>
        <p class="mt-6 text-xs text-slate-500 leading-relaxed max-w-3xl">
            MyBank is a digital banking brand. Banking services and FDIC insurance, where applicable, are provided through partner institutions. Products, rates, and terms are subject to change. This page is for general information and does not constitute a binding offer.
        </p>
    </div>
</footer>

<script>
(function () {
    const btn = document.getElementById('mobile-menu-btn');
    const closeBtn = document.getElementById('mobile-menu-close');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    if (!btn || !menu || !overlay) return;

    function open() {
        menu.classList.remove('-translate-x-full');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        menu.classList.add('-translate-x-full');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
    }
    btn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', close));
})();
</script>
</body>
</html>
