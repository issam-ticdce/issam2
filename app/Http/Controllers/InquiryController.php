<?php

namespace App\Http\Controllers;

use App\Enums\InquiryType;
use App\Enums\InvestmentRange;
use App\Models\Inquiry;
use App\Models\Startup;
use App\Notifications\NewInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    public function store(Request $request, Startup $startup)
    {
        abort_unless($startup->is_published, 404);

        $data = $request->validate([
            'type' => ['required', Rule::enum(InquiryType::class)],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('startup_id', $startup->id)->where('is_published', true)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:160'],
            'investment_range' => ['nullable', Rule::enum(InvestmentRange::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'], // piège anti-robots (champ caché)
        ]);

        unset($data['website']);

        $inquiry = $startup->inquiries()->create($data + ['locale' => app()->getLocale()]);

        $this->notify($inquiry);

        return back()->with('inquiry_sent', true);
    }

    protected function notify(Inquiry $inquiry): void
    {
        $startup = $inquiry->startup;
        $recipients = collect([$startup->email, config('ticdce.admin_email')])
            ->merge($startup->users->pluck('email'))
            ->filter()->unique();

        try {
            foreach ($recipients as $email) {
                Notification::route('mail', $email)->notify(new NewInquiry($inquiry));
            }
        } catch (\Throwable $e) {
            // La demande est enregistrée : elle reste consultable dans les espaces même si l'email échoue.
            Log::error('Envoi email demande #'.$inquiry->id.' impossible : '.$e->getMessage());
        }
    }
}
