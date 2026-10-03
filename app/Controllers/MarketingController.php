<?php

declare(strict_types=1);

/**
 * MarketingController — public-facing marketing website for MediCore HMS.
 * Separate from the authenticated hospital admin panel. No auth required.
 */

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

final class MarketingController extends Controller
{
    /** Marketing home page — the full landing page. */
    public function home(Request $request): string
    {
        $hospitalName = (string) setting('hospital_name', 'MediCore General Hospital');

        return Response::html($this->renderMarketing('home', [
            'hospitalName' => $hospitalName,
        ]));
    }

    /** Features page — detailed feature breakdown. */
    public function features(Request $request): string
    {
        return Response::html($this->renderMarketing('features', []));
    }

    /** Pricing page — editable placeholder pricing. */
    public function pricing(Request $request): string
    {
        return Response::html($this->renderMarketing('pricing', []));
    }

    /** Contact / demo request page. */
    public function contact(Request $request): string
    {
        return Response::html($this->renderMarketing('contact', [
            'errors' => Session::get('_marketing_errors', []),
            'old'    => Session::pull('_marketing_old', []),
        ]));
    }

    /** Handle demo request form submission. */
    public function submitDemo(Request $request): string
    {
        $data = [
            'full_name'         => trim((string) $request->input('full_name', '')),
            'organization'      => trim((string) $request->input('organization', '')),
            'email'             => trim((string) $request->input('email', '')),
            'phone'             => trim((string) $request->input('phone', '')),
            'hospital_size'     => trim((string) $request->input('hospital_size', '')),
            'message'           => trim((string) $request->input('message', '')),
        ];

        $errors = [];
        if ($data['full_name'] === '') $errors['full_name'] = 'Full name is required.';
        if ($data['organization'] === '') $errors['organization'] = 'Hospital or organization name is required.';
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid work email is required.';
        if ($data['phone'] !== '' && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $data['phone'])) $errors['phone'] = 'Phone number format is invalid.';
        if ($data['message'] !== '' && mb_strlen($data['message']) > 2000) $errors['message'] = 'Message must be under 2000 characters.';
        // Basic spam protection — honeypot field must be empty.
        if (!empty($request->input('website', ''))) {
            $errors['spam'] = 'Spam detected.';
        }

        if ($errors !== []) {
            Session::put('_marketing_errors', $errors);
            Session::flashInput($request->all());
            return Response::redirect(url('/contact'));
        }

        // Store in DB if the table exists.
        if (Database::tableExists('demo_requests')) {
            Database::execute(
                'INSERT INTO demo_requests (full_name, organization, email, phone, hospital_size, message, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())',
                [$data['full_name'], $data['organization'], $data['email'], $data['phone'], $data['hospital_size'], $data['message']]
            );
            AuditService::log('demo.requested', 'marketing', 'create', "Demo request from {$data['full_name']} ({$data['organization']}).", [
                'email' => $data['email'],
            ], $request);
        }

        Session::flash('success', 'Thank you! We have received your demo request and will contact you shortly.');
        return Response::redirect(url('/contact'));
    }

    /**
     * Render a marketing page using the marketing layout.
     * Marketing pages live in resources/views/marketing/ and extend 'layouts/marketing'.
     * Uses View::make() (the public factory) — not new View() (private constructor).
     */
    private function renderMarketing(string $page, array $data = []): string
    {
        return \App\Core\View::make('marketing/' . $page, $data)->render();
    }
}
