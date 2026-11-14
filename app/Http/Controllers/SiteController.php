<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\PlatformAccount;
use App\Support\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * The public website: what DueBook is, how it is used, what it costs. Its words come from
 * config/site.php; prices, the trial and the ways to pay come from the live settings, so
 * the site, the structured data for search engines and /llms.txt always say the same.
 */
class SiteController extends Controller
{
    /** The pages of the site: route name => [address, title in menus]. Also the sitemap. */
    public const PAGES = [
        'site.home' => ['/', 'Home'],
        'site.features' => ['/features', 'Features'],
        'site.uses' => ['/who-it-is-for', 'Who it is for'],
        'site.pricing' => ['/pricing', 'Pricing'],
        'site.faq' => ['/questions', 'Questions'],
        'site.contact' => ['/contact', 'Contact'],
        'site.privacy' => ['/privacy', 'Privacy'],
        'site.terms' => ['/terms', 'Terms'],
    ];

    public function home(): View
    {
        return $this->page('site.home', [
            'title' => config('app.name').': '.config('site.tagline'),
            'description' => config('site.description'),
        ]);
    }

    public function features(): View
    {
        return $this->page('site.features', [
            'title' => 'Features: renewals, reminders, loans, income and expenses',
            'description' => 'What '.config('app.name').' does: a due list for every client, email reminders, contracts, payments, loans, income, expenses, several currencies and reports.',
        ]);
    }

    public function uses(): View
    {
        return $this->page('site.uses', [
            'title' => 'Who it is for: hosting resellers, software companies, agencies',
            'description' => config('app.name').' is for businesses whose customers pay again and again: hosting and domain resellers, software companies, agencies and freelancers.',
        ]);
    }

    public function pricing(): View
    {
        return $this->page('site.pricing', [
            'title' => $this->facts()['free'] ? 'Pricing and free plan' : 'Pricing and free trial',
            'description' => 'What '.config('app.name').' costs, what each plan includes, '.($this->facts()['free'] ? 'the free plan' : 'the free trial').' and the ways to pay.',
        ]);
    }

    public function faq(): View
    {
        return $this->page('site.faq', [
            'title' => 'Questions and answers',
            'description' => 'Answers about '.config('app.name').': what it is, who it is for, reminders, price, payment, refunds, phones, currencies and privacy.',
        ]);
    }

    public function contact(): View
    {
        return $this->page('site.contact', [
            'title' => 'Contact',
            'description' => 'Write to the people behind '.config('app.name').'.',
        ]);
    }

    public function privacy(): View
    {
        return $this->page('site.privacy', ['title' => 'Privacy', 'description' => 'What '.config('app.name').' keeps about you and what it does with it.']);
    }

    public function terms(): View
    {
        return $this->page('site.terms', ['title' => 'Terms of use', 'description' => 'The rules for using '.config('app.name').', including payments and refunds.']);
    }

    /** A message from the contact form, emailed to the support address (or to the administrators). */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        // a box people never see: only programs fill it in, and their message is dropped quietly
        if (! $request->filled('website')) {
            $to = array_values(array_filter([Platform::get('support_email')])) ?: Admin::where('is_active', true)->pluck('email')->all();
            if ($to) {
                try {
                    Mail::to($to)->send(new ContactMessage($data['name'], $data['email'], $data['message']));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()->route('site.contact')->with('sent', true);
    }

    /** For search engines: which addresses exist. */
    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (self::PAGES as $name => [$path]) {
            $view = resource_path('views/'.str_replace('.', '/', $name).'.blade.php');
            $changed = date('Y-m-d', max(is_file($view) ? filemtime($view) : 0, filemtime(config_path('site.php'))));
            $xml .= '  <url><loc>'.e(url($path)).'</loc><lastmod>'.$changed.'</lastmod><priority>'.($path === '/' ? '1.0' : ($name === 'site.privacy' || $name === 'site.terms' ? '0.3' : '0.8')).'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** For search engines and AI crawlers: the website is open, the system itself is not for them. */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /subscription',
            'Disallow: /clients',
            'Disallow: /contracts',
            'Disallow: /dues',
            'Disallow: /money',
            'Disallow: /people',
            'Disallow: /reports',
            'Disallow: /settings',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** For AI assistants (the llms.txt convention): what DueBook is, in plain Markdown, with the facts they are asked about. */
    public function llms(): Response
    {
        $name = config('app.name');
        $facts = $this->facts();
        $text = "# $name\n\n> ".config('site.summary')."\n\n";
        $text .= "- Website: ".url('/')."\n- Made by: Fida Technologies (https://fida.af)\n- Kind: web application (software as a service), used in the browser on phones, tablets and computers\n- Language: English\n";
        $text .= '- Price: '.$facts['price']."\n- ".($facts['free'] ? 'Free plan: yes, with no time limit and no card needed' : 'Free trial: '.$facts['trial'].' days, no card needed')."\n- Payment: ".$facts['payments']."\n- Refunds: payments are not refunded\n\n";

        $text .= "## What it does\n\n";
        foreach (config('site.features') as [, $title, $body]) {
            $text .= "- **$title**: $body\n";
        }
        $text .= "\n## How it is used\n\n";
        foreach (config('site.steps') as $i => [$title, $body]) {
            $text .= ($i + 1).". **$title.** $body\n";
        }
        $text .= "\n## Who it is for\n\n";
        foreach (config('site.audiences') as [, $title, $body]) {
            $text .= "- **$title**: $body\n";
        }
        $text .= "\n## Questions and answers\n\n";
        foreach ($this->faqs() as [$question, $answer]) {
            $text .= "### $question\n\n$answer\n\n";
        }
        $text .= "## Pages\n\n";
        foreach (self::PAGES as [$path, $title]) {
            $text .= "- [$title](".url($path).")\n";
        }

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function page(string $view, array $meta): View
    {
        return view($view, $meta + [
            'plans' => $this->plans(),
            'accounts' => $this->accounts(),
            'faqs' => $this->faqs(),
            'facts' => $this->facts(),
            'trial' => (int) Platform::get('trial_days'),
            // with a free plan on offer a new business starts on it, and the site says so instead of "free trial"
            'free' => $this->plans()->contains(fn (Plan $plan) => $plan->isFree()),
            'signup' => Platform::bool('registration'),
        ]);
    }

    private function plans(): Collection
    {
        try {
            return Plan::where('is_active', true)->orderBy('sort')->orderBy('price')->orderBy('id')->get();
        } catch (Throwable) {
            return collect(); // the site still opens before the database is set up
        }
    }

    private function accounts(): Collection
    {
        try {
            return PlatformAccount::where('is_active', true)->orderBy('name')->get(['name', 'type', 'currency']);
        } catch (Throwable) {
            return collect();
        }
    }

    /** Price, trial and ways to pay as sentences, from the live settings. */
    private function facts(): array
    {
        $plans = $this->plans();
        $per = ['monthly' => 'a month', 'yearly' => 'a year', 'lifetime' => 'once'];
        // one sentence part for each plan name: "Pro is 4.99 USD a month or 47.90 USD a year"
        $priced = $plans->groupBy('name')->map(fn (Collection $same, string $name) => $name.' is '.$same
            ->map(fn (Plan $plan) => $plan->isFree() ? 'free' : $plan->priceText().' '.($per[$plan->interval] ?? $plan->interval))->unique()->join(' or '))->values();
        $free = $plans->contains(fn (Plan $plan) => $plan->isFree());
        $trial = (int) Platform::get('trial_days');
        $note = trim((string) Platform::get('pay_note'));

        $names = $this->accounts()->pluck('name');

        return [
            'trial' => $trial,
            'free' => $free,
            'start' => $free ? 'You can start on the free plan and stay on it as long as you like, and no card is needed.' : 'You can try everything free for '.$trial.' days, and no card is needed to start.',
            'price' => $priced->isEmpty() ? 'The price is shown when you sign up.' : ($priced->count() === 1 ? $priced->first() : 'There are '.$priced->count().' plans: '.$priced->join(', ', ' and ')).($note !== '' ? ' ('.$note.')' : '').'.',
            'payments' => $names->isEmpty() ? 'The ways to pay are shown on your Subscription page.' : 'You can pay by '.$names->join(', ', ' or ').'.',
        ];
    }

    /** The questions with their placeholders filled in. */
    private function faqs(): array
    {
        $facts = $this->facts();

        return array_map(fn (array $faq) => [
            $faq[0],
            strtr($faq[1], ['{trial}' => (string) $facts['trial'], '{start}' => $facts['start'], '{price}' => $facts['price'], '{payments}' => $facts['payments']]),
        ], config('site.faqs'));
    }
}
