const CACHE_NAME = 'mybank-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/login.php',
    '/settings.php',
    '/signup.php',
    '/forgot-password.php',
    // Add more important pages if needed
    '/assets/css/style.css',   // if you have external CSS
    '/assets/js/main.js'       // if you have JS files
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            })
    );
});