<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class ContactFormWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'contact_form';
    }

    public static function label(): string
    {
        return 'Contact form';
    }

    public static function category(): string
    {
        return 'Forms';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => 'Get in Touch',
            'title' => 'Contact Us',
            'intro' => 'Have a question or need assistance? We\'d love to hear from you.',
            'sidebar_title' => 'Reach Out',
            'response_blurb' => 'We typically respond within 24 hours.',
            'button_label' => 'Send Message',
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'nullable|string|max:200',
            'intro' => 'nullable|string|max:1000',
            'sidebar_title' => 'nullable|string|max:120',
            'response_blurb' => 'nullable|string|max:500',
            'button_label' => 'nullable|string|max:80',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.contact-form';
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow'],
            ['name' => 'title', 'kind' => 'text', 'label' => 'Title'],
            ['name' => 'intro', 'kind' => 'textarea', 'label' => 'Intro'],
            ['name' => 'sidebar_title', 'kind' => 'text', 'label' => 'Sidebar title'],
            ['name' => 'response_blurb', 'kind' => 'text', 'label' => 'Response time text'],
            ['name' => 'button_label', 'kind' => 'text', 'label' => 'Submit button label'],
        ];
    }

    public static function previewFields(): array
    {
        return ['title'];
    }
}
