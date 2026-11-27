
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MyBank • Banking Reimagined</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .hero-bg {
            background: linear-gradient(90deg, #1e2937, #2563eb);
        }
        .section-title {
            @apply text-4xl font-semibold tracking-tight;
        }
        .btn-blue {
            @apply bg-blue-600 hover:bg-blue-700 transition;
        }

        /* FIXED ANIMATION STYLES - Seamless Loop */
        @keyframes featureSlide {
            0%, 20% { transform: translateY(0); } /* Slide 1 */
            25%, 45% { transform: translateY(-20%); } /* Slide 2 */
            50%, 70% { transform: translateY(-40%); } /* Slide 3 */
            75%, 95% { transform: translateY(-60%); } /* Slide 4 */
            100% { transform: translateY(-80%); } /* To Duplicate Slide 1 */
        }
        .animate-scroll {
            /* 40s total = approx 10s per image for a slow, steady display */
            animation: featureSlide 40s infinite ease-in-out;
        }
        .slide-container {
            height: 400px;
            overflow: hidden;
            position: relative;
        }
        .slide-item {
            height: 400px;
            width: 100%;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        .slide-item > img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.4;
            z-index: 0;
            pointer-events: none;
        }
        .slide-item > .slide-caption {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0 2.5rem;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 overflow-x-hidden min-h-screen">

<!-- NAVIGATION -->
<nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-5 flex items-center justify-between relative">
        
        <!-- Left: Mobile hamburger -->
        <div class="flex items-center w-12 lg:w-auto">
            <button id="mobile-menu-btn" class="lg:hidden text-xl p-2 rounded-xl hover:bg-slate-800 transition" aria-label="Open menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <!-- Center logo (mobile) / Normal logo position (desktop) -->
        <a href="dashboard.php" class="absolute left-1/2 -translate-x-1/2 lg:static lg:translate-x-0 flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-600 rounded-xl sm:rounded-2xl flex items-center justify-center text-xl sm:text-3xl font-bold">M</div>
            <span class="text-xl sm:text-3xl font-bold tracking-tighter">MyBank</span>
        </a>

        <!-- Desktop Menu -->
        <div class="hidden lg:flex items-center gap-8 text-lg flex-1 justify-center">
            <a href="credit.php" class="hover:text-blue-400 transition">Credit Cards</a>
            <a href="signup.php" class="hover:text-blue-400 transition">Banking</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Transfers</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Investments</a>
            <a href="dashboard.php" class="hover:text-blue-400 transition">Business</a>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0 w-12 lg:w-auto justify-end">
            <a href="dashboard.php" 
               class="hidden sm:inline-flex px-4 py-2 sm:px-6 md:px-8 sm:py-3 text-sm sm:text-base font-semibold border border-slate-300 hover:border-white rounded-2xl sm:rounded-3xl transition whitespace-nowrap">
                Sign In
            </a>
            <a href="signup.php" 
               class="hidden sm:inline-flex px-4 py-2 sm:px-6 md:px-8 sm:py-3 text-sm sm:text-base bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl sm:rounded-3xl transition whitespace-nowrap">
                Open an Account
            </a>
            <!-- Spacer so logo stays centered on pure mobile -->
            <div class="sm:hidden w-10"></div>
        </div>
    </div>
</nav>

<!-- MOBILE MENU OVERLAY -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300 lg:hidden"></div>

<!-- MOBILE MENU DRAWER (opens from LEFT) -->
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
        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Account</p>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-blue-600/20 flex items-center justify-center text-blue-400 group-hover:bg-blue-600/30">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <div>
                <span class="font-semibold block">Sign In</span>
                <span class="text-xs text-slate-400">Access your account</span>
            </div>
        </a>
        <a href="signup.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 flex items-center justify-center text-emerald-400 group-hover:bg-emerald-600/30">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div>
                <span class="font-semibold block">Open an Account</span>
                <span class="text-xs text-slate-400">Start banking in minutes</span>
            </div>
        </a>

        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-6 mb-2">Products</p>
        <a href="credit.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-amber-600/20 flex items-center justify-center text-amber-400 group-hover:bg-amber-600/30">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <div>
                <span class="font-semibold block">Credit Cards</span>
                <span class="text-xs text-slate-400">Rewards that actually matter</span>
            </div>
        </a>
        <a href="signup.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-cyan-600/20 flex items-center justify-center text-cyan-400 group-hover:bg-cyan-600/30">
                <i class="fa-solid fa-piggy-bank"></i>
            </div>
            <div>
                <span class="font-semibold block">Checking &amp; Savings</span>
                <span class="text-xs text-slate-400">No fees. No minimums.</span>
            </div>
        </a>
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-violet-600/20 flex items-center justify-center text-violet-400 group-hover:bg-violet-600/30">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div>
                <span class="font-semibold block">Instant Transfers</span>
                <span class="text-xs text-slate-400">Zelle &amp; more</span>
            </div>
        </a>

        <p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-6 mb-2">Help</p>
        <a href="support-login.php" class="flex items-center gap-3 px-4 py-3.5 rounded-xl hover:bg-slate-800 transition group">
            <div class="w-10 h-10 rounded-xl bg-rose-600/20 flex items-center justify-center text-rose-400 group-hover:bg-rose-600/30">
                <i class="fa-solid fa-headset"></i>
            </div>
            <div>
                <span class="font-semibold block">Contact Support</span>
                <span class="text-xs text-slate-400">We're here 24/7</span>
            </div>
        </a>
    </div>

    <div class="px-5 py-4 border-t border-slate-800">
        <a href="signup.php" class="block w-full text-center py-3.5 bg-blue-600 hover:bg-blue-700 font-semibold rounded-2xl transition">
            Get Started Free
        </a>
        <p class="text-center text-xs text-slate-500 mt-3">FDIC Insured • No hidden fees</p>
    </div>
</div>

<!-- HERO SECTION -->
<section class="hero-bg py-24">
    <div class="max-w-screen-2xl mx-auto px-8 text-center">
        <h1 class="text-6xl md:text-7xl font-bold tracking-tighter leading-none mb-6">
            Meet MyBank
        </h1>
        <p class="text-2xl text-slate-300 max-w-2xl mx-auto mb-10">
            Modern banking with no fees, instant transfers, and rewards that actually matter.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="dashboard.php" 
               class="px-10 py-5 bg-white text-slate-900 font-semibold text-xl rounded-3xl hover:scale-105 transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-right"></i>
                Sign In
            </a>
            <a href="credit.php" 
               class="px-10 py-5 border-2 border-white font-semibold text-xl rounded-3xl hover:bg-white/10 transition">
                See if you're pre-approved
            </a>
            <a href="signup.php" 
               class="px-10 py-5 border-2 border-white font-semibold text-xl rounded-3xl hover:bg-white/10 transition">
                Start Banking
            </a>
        </div>
        <div class="mt-8 flex flex-col items-center gap-2">
            <p class="text-sm text-slate-400 flex items-center gap-2">No minimums • No hidden fees • FDIC insured <i class="fa-solid fa-house-chimney text-xs" title="Equal Housing Lender"></i></p>
            <p class="text-blue-400 font-medium bg-blue-900/30 px-6 py-2 rounded-full text-lg">
                <i class="fa-solid fa-users mr-2"></i>Join <span id="user-count" class="text-2xl font-bold text-white ml-1">2,842,109</span> users trusting MyBank
            </p>
        </div>

        <!-- Trust badges -->
        <div class="mt-10 flex flex-wrap items-center justify-center gap-3 sm:gap-5">
            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-slate-300">
                <i class="fa-solid fa-shield-halved text-emerald-400"></i>
                <span>FDIC Insured</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-slate-300">
                <i class="fa-solid fa-lock text-blue-400"></i>
                <span>Bank-grade security</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-slate-300">
                <i class="fa-solid fa-house-chimney text-amber-400"></i>
                <span>Equal Housing Lender</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-slate-300">
                <i class="fa-solid fa-bolt text-violet-400"></i>
                <span>Instant transfers</span>
            </div>
        </div>
    </div>
</section>

<!-- LONG DETAILS ABOUT THE BANK -->
<section class="py-20 bg-slate-900">
    <div class="max-w-screen-2xl mx-auto px-8">
        <div class="grid md:grid-cols-12 gap-16 items-center">
            <div class="md:col-span-7">
                <h2 class="section-title mb-8 text-blue-400">Why millions choose MyBank</h2>
                <div class="prose prose-invert text-lg leading-relaxed space-y-8">
                    <p>
                        MyBank was founded with one simple mission: make banking effortless, transparent, and rewarding. 
                        Unlike traditional banks, we built everything from the ground up with you in mind. 
                        Whether you're sending money instantly with Zelle, growing your savings, or exploring crypto, 
                        every feature is designed to put you in control.
                    </p>
                    <p>
                        Our checking and savings accounts come with zero monthly fees, zero minimum balances, 
                        and industry-leading interest rates. Transfer money to anyone instantly using just an email or phone number. 
                        Deposit checks with your phone camera in seconds. Track every dollar with real-time notifications and beautiful dashboards.
                    </p>
                    <p>
                        Security is non-negotiable. We use bank-grade encryption, biometric login, and real-time fraud monitoring. 
                        Plus, every account is FDIC insured up to the maximum allowed by law. 
                        We've helped over 2.8 million customers move $47 billion safely — and we're just getting started.
                    </p>
                    <div class="grid grid-cols-3 gap-8 mt-12">
                        <div>
                            <div class="text-5xl font-mono font-bold text-blue-400">4.9s</div>
                            <p class="text-slate-400">Average time to open an account</p>
                        </div>
                        <div>
                            <div class="text-5xl font-mono font-bold text-blue-400">24/7</div>
                            <p class="text-slate-400">Mobile app support</p>
                        </div>
                        <div>
                            <div class="text-5xl font-mono font-bold text-blue-400">0</div>
                            <p class="text-slate-400">Hidden fees ever</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-5 bg-slate-800/50 rounded-3xl p-10 border border-slate-700">
                <h3 class="text-2xl font-semibold mb-6">Built for the way you live</h3>
                <ul class="space-y-6 text-slate-300">
                    <li class="flex gap-4"><i class="fa-solid fa-check text-emerald-400 mt-1"></i> Instant Zelle transfers</li>
                    <li class="flex gap-4"><i class="fa-solid fa-check text-emerald-400 mt-1"></i> Free credit score monitoring</li>
                    <li class="flex gap-4"><i class="fa-solid fa-check text-emerald-400 mt-1"></i> Crypto buying &amp; selling</li>
                    <li class="flex gap-4"><i class="fa-solid fa-check text-emerald-400 mt-1"></i> Auto-deposit matching up to $300/year</li>
                    <li class="flex gap-4"><i class="fa-solid fa-check text-emerald-400 mt-1"></i> 50,000+ fee-free ATMs nationwide</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCT SECTIONS -->
<div class="max-w-screen-2xl mx-auto px-8 py-16 space-y-16">

    <!-- Cards -->
    <div class="grid md:grid-cols-2 gap-8 items-center">
        <div>
            <h2 class="text-4xl font-semibold mb-4">Find the right card for you</h2>
            <p class="text-slate-400 text-xl">More built-in benefits. Rewards that fit your lifestyle.</p>
            <a href="credit.php" 
               class="inline-block mt-8 px-8 py-4 btn-blue text-white font-semibold rounded-3xl">
                Compare cards →
            </a>
        </div>
        <div class="bg-slate-800 rounded-3xl p-8 text-center">
            <p class="text-5xl font-mono">5% cash back</p>
            <p class="text-slate-400 mt-2">on everything you buy with MyBank Cash Rewards</p>
        </div>
    </div>

    <!-- Banking -->
    <div class="bg-slate-900 rounded-3xl p-12">
        <h2 class="text-4xl font-semibold mb-4">Bank with confidence</h2>
        <p class="text-2xl text-slate-400">No fees. No minimums. Just better banking.</p>
        <div class="grid md:grid-cols-3 gap-8 mt-12">
            <a href="index.php" class="block bg-slate-800/70 p-8 rounded-2xl border border-transparent hover:bg-slate-700 hover:border-blue-500/40 hover:shadow-lg hover:shadow-blue-500/10 hover:-translate-y-1 transition-all duration-300">
                <i class="fa-solid fa-wallet text-4xl text-blue-400 mb-6"></i>
                <h4 class="text-2xl font-semibold">Checking</h4>
                <p class="text-slate-400 mt-3">Unlimited transactions • Early direct deposit</p>
            </a>
            <a href="index.php" class="block bg-slate-800/70 p-8 rounded-2xl border border-transparent hover:bg-slate-700 hover:border-emerald-500/40 hover:shadow-lg hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300">
                <i class="fa-solid fa-piggy-bank text-4xl text-emerald-400 mb-6"></i>
                <h4 class="text-2xl font-semibold">Savings</h4>
                <p class="text-slate-400 mt-3">4.25% APY • No monthly fees</p>
            </a>
            <a href="credit.php" class="block bg-slate-800/70 p-8 rounded-2xl border border-transparent hover:bg-slate-700 hover:border-amber-500/40 hover:shadow-lg hover:shadow-amber-500/10 hover:-translate-y-1 transition-all duration-300">
                <i class="fa-solid fa-credit-card text-4xl text-amber-400 mb-6"></i>
                <h4 class="text-2xl font-semibold">Debit Card</h4>
                <p class="text-slate-400 mt-3">Instant virtual card • Contactless payments</p>
            </a>
        </div>
    </div>

    <!-- More than a bank -->
    <div>
        <h2 class="text-4xl font-semibold mb-10 text-center">We're more than a bank</h2>
        <div class="grid md:grid-cols-3 gap-8">
            <a href="dashboard.php" class="text-center block p-6 rounded-2xl hover:bg-slate-800/50 hover:scale-105 hover:shadow-lg hover:shadow-blue-500/10 transition-all duration-300">
                <div class="text-6xl mb-4">📱</div>
                <h4 class="font-semibold text-2xl">Mobile App</h4>
                <p class="text-slate-400">Top-rated. Deposit checks. Pay bills. Transfer instantly.</p>
            </a>
            <a href="dadhboard.php" class="text-center block p-6 rounded-2xl hover:bg-slate-800/50 hover:scale-105 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-300">
                <div class="text-6xl mb-4">₿</div>
                <h4 class="font-semibold text-2xl">Crypto</h4>
                <p class="text-slate-400">Buy, sell, and hold Bitcoin right inside your account.</p>
            </a>
            <a href="signup.php" class="text-center block p-6 rounded-2xl hover:bg-slate-800/50 hover:scale-105 hover:shadow-lg hover:shadow-emerald-500/10 transition-all duration-300">
                <div class="text-6xl mb-4">🏦</div>
                <h4 class="font-semibold text-2xl">Zelle®</h4>
                <p class="text-slate-400">Send money to anyone — instantly, for free.</p>
            </a>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="bg-slate-900 border-t border-slate-800 py-16">
    <div class="max-w-screen-2xl mx-auto px-8 text-slate-400 text-sm">
        
        <!-- UNIQUE BANKING ADVERTISEMENTS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            <div class="relative group overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 aspect-[4/3]">
                <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&q=80&w=600" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 transition duration-500" alt="Mortgages">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent flex flex-col justify-end p-6">
                    <h5 class="text-white text-xl font-bold">Dream Home Mortgages</h5>
                    <p class="text-slate-300 text-xs mt-1">Rates as low as 3.25% APR. Fixed & Variable options available.</p>
                </div>
            </div>
            <div class="relative group overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 aspect-[4/3]">
                <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=600" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 transition duration-500" alt="Personal Loans">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent flex flex-col justify-end p-6">
                    <h5 class="text-white text-xl font-bold">Personal Loans</h5>
                    <p class="text-slate-300 text-xs mt-1">Instant approval for up to $50k. No collateral required.</p>
                </div>
            </div>
            <div class="relative group overflow-hidden rounded-2xl bg-slate-800 border border-slate-700 aspect-[4/3]">
                <img src="https://images.unsplash.com/photo-1664575602554-2087b04935a5?auto=format&fit=crop&q=80&w=600" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-110 transition duration-500" alt="Business Banking">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent flex flex-col justify-end p-6">
                    <h5 class="text-white text-xl font-bold">Scale Your Business</h5>
                    <p class="text-slate-300 text-xs mt-1">Specialized lending for startups and growing enterprises.</p>
                </div>
            </div>
        </div>

        <!-- REPAIRED ANIMATED FEATURE DISPLAY -->
        <div class="grid lg:grid-cols-3 gap-8 mb-16">
            <div class="lg:col-span-2 slide-container rounded-3xl bg-blue-600/5 border border-slate-800">
                <div class="animate-scroll flex flex-col">
                    <!-- Slide 1 -->
                    <div class="slide-item">
                        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&q=80&w=1200" alt="Mortgages">
                        <div class="slide-caption">
                            <h3 class="text-4xl font-bold text-white uppercase tracking-widest mb-2">Dream Home Mortgages</h3>
                            <p class="text-blue-300 text-xl font-medium">Your path to home ownership starts here.</p>
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="slide-item">
                        <img src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=1200" alt="Personal Loans">
                        <div class="slide-caption">
                            <h3 class="text-4xl font-bold text-white uppercase tracking-widest mb-2">Personal Loans</h3>
                            <p class="text-blue-300 text-xl font-medium">Fast funding for whatever life throws your way.</p>
                        </div>
                    </div>
                    <!-- Slide 3 -->
                    <div class="slide-item">
                        <img src="https://images.unsplash.com/photo-1664575602554-2087b04935a5?auto=format&fit=crop&q=80&w=1200" alt="Business">
                        <div class="slide-caption">
                            <h3 class="text-4xl font-bold text-white uppercase tracking-widest mb-2">Scale Your Business</h3>
                            <p class="text-blue-300 text-xl font-medium">Capital solutions designed for entrepreneurs.</p>
                        </div>
                    </div>
                    <!-- Slide 4 -->
                    <div class="slide-item bg-slate-900">
                        <div class="slide-caption">
                            <img src="https://img.icons8.com/fluency/144/iphone-x.png" class="mb-4 relative z-10" alt="App">
                            <h3 class="text-4xl font-bold text-white uppercase tracking-widest mb-2">Banking in your pocket</h3>
                            <p class="text-blue-300 text-xl font-medium">Total financial control, anywhere in the world.</p>
                        </div>
                    </div>
                    <!-- SEAMLESS DUPLICATE (Copy of Slide 1) -->
                    <div class="slide-item">
                        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&q=80&w=1200" alt="Mortgages">
                        <div class="slide-caption">
                            <h3 class="text-4xl font-bold text-white uppercase tracking-widest mb-2">Dream Home Mortgages</h3>
                            <p class="text-blue-300 text-xl font-medium">Your path to home ownership starts here.</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Side Image Grid -->
            <div class="grid grid-cols-2 gap-4 h-[400px]">
                <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&q=80&w=400" class="w-full h-full object-cover rounded-2xl border border-slate-800 hover:scale-105 transition" alt="Digital Banking">
                <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&q=80&w=400" class="w-full h-full object-cover rounded-2xl border border-slate-800 hover:scale-105 transition" alt="Customer Service">
                <img src="https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?auto=format&fit=crop&q=80&w=400" class="w-full h-full object-cover rounded-2xl border border-slate-800 hover:scale-105 transition" alt="Savings Growth">
                <img src="https://images.unsplash.com/photo-1501167786227-4cba60f6d58f?auto=format&fit=crop&q=80&w=400" class="w-full h-full object-cover rounded-2xl border border-slate-800 hover:scale-105 transition" alt="Modern Banking">
            </div>
        </div>

        <!-- ADVERTISING BANNER (below animation — not overlapped) -->
        <div class="mb-16 mt-4 flex flex-col gap-6 bg-blue-600/10 p-8 rounded-3xl border border-blue-500/20">
            <div class="flex flex-col sm:flex-row items-center gap-6 text-center sm:text-left">
                <div class="flex-shrink-0">
                    <img src="https://img.icons8.com/fluency/96/iphone-x.png" alt="App Preview" class="w-24 mx-auto sm:mx-0">
                </div>
                <div class="flex-1">
                    <h4 class="text-xl font-bold text-white mb-2">Banking in your pocket</h4>
                    <p class="text-slate-300 mb-4">Get the MyBank app for faster transfers and exclusive 5% cash back rewards.</p>
                    <div class="flex flex-wrap gap-3 justify-center sm:justify-start">
                        <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/3/3c/Download_on_the_App_Store_Badge.svg" class="h-8" alt="App Store"></a>
                        <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" class="h-8" alt="Google Play"></a>
                    </div>
                </div>
                <div class="text-center sm:text-right flex-shrink-0">
                    <span class="text-4xl font-bold text-blue-400 block">$200 Bonus</span>
                    <span class="text-slate-400 italic text-sm">When you open a qualifying checking account</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap justify-between gap-y-12">
            <div>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-8 h-8 bg-blue-600 rounded-2xl flex items-center justify-center text-2xl font-bold">M</div>
                    <span class="text-2xl font-bold tracking-tighter text-white">MyBank</span>
                </div>
                <p>© 2026 MyBank. All rights reserved.</p>
                <p class="mt-2 flex items-center gap-2">FDIC Insured <i class="fa-solid fa-house-chimney text-xs" title="Equal Housing Lender"></i> • Member of Zelle Network</p>
            </div>
            <div class="grid grid-cols-3 gap-x-16">
                <div>
                    <strong class="block mb-4 text-white">Products</strong>
                    <a href="credit.php" class="block hover:text-white py-1">Credit Cards</a>
                    <a href="index.php" class="block hover:text-white py-1">Checking &amp; Savings</a>
                    <a href="transfer.php" class="block hover:text-white py-1">Transfers</a>
                </div>
                <div>
                    <strong class="block mb-4 text-white">Company</strong>
                    <a href="terms.php" class="block hover:text-white py-1">terms&condition</a>
                    <a href="about.php" class="block hover:text-white py-1">about us</a>
                    <a href="#" class="block hover:text-white py-1">Press</a>
                </div>
                <div>
                    <strong class="block mb-4 text-white">Support</strong>
                    <a href="support.php" class="block hover:text-white py-1">Contact Us</a>
                    <a href="" class="block hover:text-white py-1">Help Center</a>
                    <a href="login.php" class="block hover:text-white py-1">Sign In</a>
                </div>
            </div>
        </div>
        
        <!-- SOCIAL MEDIA ICONS -->
        <div class="mt-12 pt-8 border-t border-slate-800 flex justify-center gap-8 text-2xl">
            <a href="#" class="hover:text-blue-500 transition"><i class="fa-brands fa-facebook"></i></a>
            <a href="#" class="hover:text-pink-500 transition"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" class="hover:text-white transition"><i class="fa-brands fa-x-twitter"></i></a>
        </div>
    </div>
</footer>

<!-- Sticky Open Account bar (appears after scrolling) -->
<div id="sticky-cta" class="fixed bottom-0 inset-x-0 z-50 translate-y-full transition-transform duration-300 ease-out">
    <div class="bg-slate-900/95 backdrop-blur-md border-t border-slate-700 px-4 py-3 sm:py-4">
        <div class="max-w-screen-2xl mx-auto flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="font-semibold text-white text-sm sm:text-base truncate">Ready to bank smarter?</p>
                <p class="text-xs sm:text-sm text-slate-400 truncate">No fees • FDIC insured • Open in minutes</p>
            </div>
            <a href="signup.php" class="flex-shrink-0 px-5 py-2.5 sm:px-8 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm sm:text-base rounded-2xl transition shadow-lg shadow-blue-600/25">
                Open Account
            </a>
        </div>
    </div>
</div>

<script>
// User count animation
let count = 2842109;
const counterElement = document.getElementById('user-count');
if (counterElement) {
    setInterval(() => {
        count += Math.floor(Math.random() * 25) + 10;
        counterElement.innerText = count.toLocaleString();
    }, 50000);
}

// Mobile menu (opens from LEFT)
(function () {
    const btn = document.getElementById('mobile-menu-btn');
    const closeBtn = document.getElementById('mobile-menu-close');
    const menu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');

    if (!btn || !menu || !overlay) return;

    function openMenu() {
        menu.classList.remove('-translate-x-full');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        menu.classList.add('-translate-x-full');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMenu();
    });

    menu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeMenu);
    });
})();

// Sticky CTA bar – show after scrolling past hero
(function () {
    const bar = document.getElementById('sticky-cta');
    if (!bar) return;

    const showAfter = 400;

    function update() {
        if (window.scrollY > showAfter) {
            bar.classList.remove('translate-y-full');
        } else {
            bar.classList.add('translate-y-full');
        }
    }

    window.addEventListener('scroll', update, { passive: true });
    update();
})();
</script>
</body>
</html>
