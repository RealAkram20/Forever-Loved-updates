<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about',
                'title' => 'About Us',
                'content' => '<h2>Our Mission</h2><p>We believe every life deserves to be remembered and celebrated. Our platform provides a dignified, lasting space where families and friends can honor their loved ones, share cherished memories, and keep legacies alive for generations to come.</p><h2>What We Do</h2><p>We offer beautifully designed online memorials that bring people together to grieve, remember, and celebrate. From heartfelt tributes and photo galleries to life stories and shared memories, our platform makes it easy to create a meaningful tribute.</p><h2>Our Promise</h2><p>We are committed to providing a secure, respectful, and ad-free experience for those who choose our premium plans. Your memories are precious, and we treat them with the care they deserve.</p>',
                'meta_description' => 'Learn about our mission to help families create lasting digital memorials for their loved ones.',
                'is_published' => true,
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => '<h2>Introduction</h2><p>Your privacy is important to us. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our memorial platform.</p><h2>Information We Collect</h2><p>We collect information you provide directly, such as your name, email address, and memorial content. We also collect usage data to improve our services.</p><h2>How We Use Your Information</h2><p>We use your information to provide and maintain our services, notify you about changes, and improve user experience. We do not sell your personal information to third parties.</p><h2>Data Security</h2><p>We implement industry-standard security measures to protect your personal information. However, no method of transmission over the Internet is 100% secure.</p><h2>Contact Us</h2><p>If you have questions about this Privacy Policy, please contact us through our contact page.</p>',
                'meta_description' => 'Our privacy policy explains how we collect, use, and protect your personal information.',
                'is_published' => true,
            ],
            [
                'slug' => 'terms-of-use',
                'title' => 'Terms of Use',
                'content' => '<h2>Acceptance of Terms</h2><p>By accessing and using this platform, you accept and agree to be bound by these Terms of Use. If you do not agree, please do not use our services.</p><h2>User Accounts</h2><p>You are responsible for maintaining the confidentiality of your account credentials. You agree to accept responsibility for all activities that occur under your account.</p><h2>Memorial Content</h2><p>You retain ownership of content you post. By posting content, you grant us a non-exclusive license to display and distribute that content as part of our services. You agree not to post content that is unlawful, offensive, or infringes on others\' rights.</p><h2>Subscriptions and Payments</h2><p>Paid plans are billed according to the plan selected. Refund policies are outlined during the purchase process. We reserve the right to modify pricing with advance notice.</p><h2>Limitation of Liability</h2><p>Our platform is provided "as is" without warranties of any kind. We shall not be liable for any indirect, incidental, or consequential damages arising from your use of the platform.</p><h2>Changes to Terms</h2><p>We may update these terms from time to time. Continued use of the platform after changes constitutes acceptance of the new terms.</p>',
                'meta_description' => 'Read our terms of use governing the use of our memorial platform and services.',
                'is_published' => true,
            ],
        ];

        foreach ($pages as $page) {
            Page::firstOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
