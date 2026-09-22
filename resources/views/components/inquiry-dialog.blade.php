@props(['startup', 'product' => null])
{{-- Formulaire unique (contact, devis, investissement) affiché dans une fenêtre. --}}
<dialog class="dialog" id="inquiry-dialog" aria-labelledby="inquiry-title">
    <form method="POST" action="{{ route('inquiries.store', $startup) }}" class="dialog-form">
        @csrf
        <input type="hidden" name="type" value="{{ old('type', 'contact') }}" data-inquiry-type>
        @if ($product)<input type="hidden" name="product_id" value="{{ $product->id }}">@endif

        <div class="dialog-head">
            <h2 id="inquiry-title" data-title-contact="{{ __('site.form.title_contact', ['startup' => $startup->name]) }}" data-title-quote="{{ __('site.form.title_quote') }}{{ $product ? ' · ' . $product->tr('name') : '' }}" data-title-investment="{{ __('site.form.title_investment') }}">{{ __('site.form.title_contact', ['startup' => $startup->name]) }}</h2>
            <button type="button" class="icon-btn" data-dialog-close aria-label="{{ __('site.form.cancel') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <p class="muted small" data-only="investment" hidden>{{ __('site.form.intro_investment') }}</p>

        @if ($errors->any())
            <div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="form-grid">
            <label class="field">
                <span>{{ __('site.form.name') }} *</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
            </label>
            <label class="field">
                <span>{{ __('site.form.email') }} *</span>
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" dir="ltr">
            </label>
            <label class="field">
                <span>{{ __('site.form.phone') }} <em>({{ __('site.form.optional') }})</em></span>
                <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" dir="ltr">
            </label>
            <label class="field">
                <span>{{ __('site.form.organization') }} <em>({{ __('site.form.optional') }})</em></span>
                <input type="text" name="organization" value="{{ old('organization') }}" maxlength="160" autocomplete="organization">
            </label>
            <label class="field field-full" data-only="investment" hidden>
                <span>{{ __('site.form.investment_range') }}</span>
                <select name="investment_range">
                    <option value=""></option>
                    @foreach (\App\Enums\InvestmentRange::options() as $value => $label)
                        <option value="{{ $value }}" @selected(old('investment_range') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field field-full">
                <span>{{ __('site.form.message') }} *</span>
                <textarea name="message" rows="5" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
            </label>
            <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <p class="muted small">{{ __('site.form.privacy') }}</p>

        <div class="dialog-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>{{ __('site.form.cancel') }}</button>
            <button type="submit" class="btn btn-primary">{{ __('site.form.send') }}</button>
        </div>
    </form>
</dialog>
@if ($errors->any())
    <div data-open-inquiry="{{ old('type', 'contact') }}" hidden></div>
@endif
