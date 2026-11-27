<?php
require 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyBank - Terms & Conditions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .terms-container { max-width: 920px; }
        h2 { scroll-margin-top: 80px; }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">

<div class="max-w-4xl mx-auto px-6 py-12 terms-container">
    <div class="flex items-center gap-4 mb-10">
        <div class="w-12 h-12 bg-violet-600 rounded-2xl flex items-center justify-center text-4xl font-bold">M</div>
        <div>
            <h1 class="text-4xl font-bold text-white">Terms & Conditions</h1>
            <p class="text-slate-400">MyBank • Last Updated: March 25, 2026</p>
        </div>
    </div>

    <div class="prose prose-invert max-w-none space-y-12 text-slate-300 leading-relaxed">

        <p class="text-lg">
            Welcome to MyBank. By accessing or using our website, mobile application, or any of our banking services, 
            you agree to be bound by these Terms and Conditions, including our integrated Privacy Policy. 
            Please read them carefully before using MyBank.
        </p>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">1. Acceptance of Terms</h2>
            <p>By creating an account, logging in, or using any MyBank service (including transfers, Zelle, ACH linking, 
            investments, or crypto price tracking), you confirm that you have read, understood, and agree to these 
            Terms and Conditions and our Privacy Policy. If you do not agree, you must immediately discontinue use of our services.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">2. Eligibility</h2>
            <p>You must be at least 18 years of age, a legal resident of a supported jurisdiction, and capable of forming 
            a binding contract. By using MyBank, you represent and warrant that you meet these requirements.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">3. Account Registration and Security</h2>
            <p>You are responsible for maintaining the confidentiality of your password and account information. 
            MyBank employs bank-grade encryption, multi-factor authentication where available, and continuous monitoring 
            to protect your account. You agree to notify us immediately of any unauthorized access or security breach.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">4. Services Provided</h2>
            <p>MyBank offers digital checking and savings accounts, internal/external transfers, Zelle instant payments, 
            verified ACH account linking, investment overviews, and real-time cryptocurrency price tracking. 
            All services are subject to availability and may be modified or discontinued with notice.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">5. Fees and Charges</h2>
            <p>Fees for certain services (such as wire transfers, overdrafts, or premium features) will be clearly disclosed 
            before you complete any transaction. By proceeding with a fee-based service, you agree to pay the applicable charges.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">6. Transactions and Transfers</h2>
            <p>All transactions are final once processed. MyBank is not responsible for losses resulting from incorrect 
            recipient details provided by you. External ACH transfers typically complete within 1–3 business days.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">7. User Conduct</h2>
            <p>You agree not to use MyBank for any unlawful, fraudulent, or harmful purpose. Violations may result in 
            immediate suspension or permanent termination of your account without prior notice.</p>
        </div>

        <!-- Privacy Policy Section - Expanded and Detailed -->
        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">8. Privacy Policy</h2>
            <p class="mb-6">MyBank is committed to protecting your personal information. This Privacy Policy explains how we collect, 
            use, disclose, and safeguard your data when you use our services.</p>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.1 Information We Collect</h3>
            <ul class="list-disc pl-6 space-y-2">
                <li><strong>Personal Information:</strong> Full name, email address, phone number, date of birth, government-issued ID, and address.</li>
                <li><strong>Financial Information:</strong> Bank account numbers, routing numbers, transaction history, balances, and payment details.</li>
                <li><strong>Device & Usage Data:</strong> IP address, device type, browser information, location data (with consent), and usage patterns.</li>
                <li><strong>Verification Data:</strong> Government ID scans, selfie verification, and biometric data where applicable.</li>
            </ul>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.2 How We Use Your Information</h3>
            <ul class="list-disc pl-6 space-y-2">
                <li>To provide, maintain, and improve our banking services</li>
                <li>To process transactions and send confirmations</li>
                <li>To verify your identity and prevent fraud</li>
                <li>To comply with legal and regulatory obligations (KYC/AML)</li>
                <li>To communicate with you regarding your account and security alerts</li>
                <li>To personalize your experience and offer relevant financial insights</li>
            </ul>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.3 Information Sharing</h3>
            <p>We do not sell your personal data. We may share information only in the following cases:</p>
            <ul class="list-disc pl-6 space-y-2">
                <li>With trusted service providers who assist in operating our platform (under strict confidentiality agreements)</li>
                <li>With regulatory authorities when required by law</li>
                <li>In the event of a merger, acquisition, or sale of assets</li>
                <li>With your explicit consent</li>
            </ul>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.4 Data Security</h3>
            <p>We implement industry-leading security measures including AES-256 encryption, secure servers, firewalls, 
            regular security audits, and employee training. However, no system is completely impenetrable, and we cannot 
            guarantee absolute security.</p>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.5 Your Rights</h3>
            <p>You have the right to:</p>
            <ul class="list-disc pl-6 space-y-2">
                <li>Access, update, or delete your personal information</li>
                <li>Opt out of certain data processing activities</li>
                <li>Withdraw consent where applicable</li>
                <li>Request data portability</li>
            </ul>
            <p class="mt-4">To exercise these rights, please contact us through the Support page or mybank@gmail.com.</p>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.6 Cookies and Tracking</h3>
            <p>We use essential cookies to keep you logged in and provide core functionality. 
            You can manage cookie preferences through your browser settings. Third-party analytics tools may be used 
            to improve our services (anonymized data only).</p>

            <h3 class="text-xl font-medium text-white mt-8 mb-3">8.7 Data Retention</h3>
            <p>We retain your information only as long as necessary to provide our services, comply with legal obligations, 
            resolve disputes, and enforce our agreements. Financial records are typically retained for 7 years as required by law.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">9. Limitation of Liability</h2>
            <p>To the maximum extent permitted by law, MyBank shall not be liable for any indirect, incidental, special, 
            consequential, or punitive damages arising from your use of our services. Our total liability shall not exceed 
            the fees paid by you to MyBank in the twelve (12) months preceding the claim.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">10. Termination</h2>
            <p>MyBank reserves the right to suspend or terminate your account at any time, with or without notice, 
            for violation of these terms, suspected fraudulent activity, or regulatory requirements.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">11. Governing Law</h2>
            <p>These Terms and Conditions shall be governed by the laws of the United States. Any disputes shall be resolved 
            exclusively in the competent courts of your jurisdiction.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">12. Changes to Terms</h2>
            <p>We may update these Terms and Conditions (including the Privacy Policy) from time to time. 
            We will notify you of material changes via email or in-app notification. Continued use of MyBank after such changes 
            constitutes your acceptance of the updated terms.</p>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-white mb-4">13. Contact Us</h2>
            <p>If you have any questions about these Terms & Conditions or our Privacy Policy, please contact our support team:</p>
            <div class="bg-slate-900 border border-slate-700 rounded-2xl p-6 mt-4">
                <p class="font-medium">MyBank Support</p>
                <p>Email: <a href="mailto:mybank@gmail.com" class="text-violet-400 hover:underline">mybank@gmail.com</a></p>
                <p class="mt-2">Or use the <a href="support.php" class="text-violet-400 hover:underline">Contact Support</a> page inside the app.</p>
            </div>
        </div>

        <div class="pt-12 border-t border-slate-700 text-center text-sm text-slate-500">
            © 2026 MyBank. All Rights Reserved.<br>
            Your security, privacy, and trust are our highest priorities.
        </div>
    </div>
</div>

<!-- Bottom navigation for consistency -->
<div class="fixed bottom-0 left-0 right-0 bg-slate-900 border-t border-slate-800 py-4 text-center text-xs text-slate-500">
    <a href="login.php" class="hover:text-white transition">Back to Login</a>
</div>

</body>
</html>