@extends('layouts.product')
@section('title', 'Privacy Policy · ' . $brand)
@section('description', 'What ' . $brand . ' collects, why, who sees it, how long we keep it and how to use your rights under the Data Privacy Act.')
@section('robots', 'index, follow')
@section('content')
<header class="container" style="padding-block:22px"><a href="/" aria-label="{{ $brand }}">@include('partials.brand')</a></header>
<main class="narrow legal">
  <p class="eyebrow">Legal</p>
  <h1 class="display">Privacy Policy</h1>
  <p class="muted">Version {{ $legal['version'] }} · last updated {{ $legal['updated'] }}. This is how we handle personal information under the Data Privacy Act of 2012 (Republic Act No. 10173) and its rules. Please read it with our <a href="/terms">Terms and Conditions</a>.</p>

  <h2>1. Who is responsible</h2>
  <p><strong>{{ $legal['operator'] }}</strong>, {{ $legal['address'] }}, runs {{ $brand }} and is the “personal information controller” for the information described here. @if ($legal['dpo'] !== '')Our data protection officer is <strong>{{ $legal['dpo'] }}</strong>. @endif Write to <strong>{{ $legal['email'] }}</strong> for anything about your information.</p>

  <h2>2. What we collect, and why</h2>
  <div class="legal-table" role="region" aria-label="What we collect" tabindex="0">
    <table>
      <thead><tr><th>What</th><th>Where it comes from</th><th>What we use it for</th></tr></thead>
      <tbody>
        <tr><td>Your name, e-mail address and password (stored only as a one-way Argon2id hash)</td><td>You, when you sign up</td><td>Your account, signing you in, contacting you about your account and orders, security</td></tr>
        <tr><td>Your Google or Facebook name, e-mail address and account ID</td><td>Google or Facebook, when you choose to sign in with them</td><td>Creating and signing in to your account. We do not receive your password with them or post to your account.</td></tr>
        <tr><td>Your content: photos, letters, names of the people you write to, the song you choose (a YouTube link), the lock question and a hash of its answer, the template and colours you pick</td><td>You</td><td>Building and showing your pages to the people you share them with. This can include information about other people (see section 9).</td></tr>
        <tr><td>Orders and payments: plan, amount, date, method (GCash, Maya, card), status and the payment provider’s reference. Not your full card number or e-wallet login.</td><td>You and our payment providers</td><td>Taking payment, renewals, receipts, refunds, fraud checks, accounting and tax. Payment references are stored encrypted.</td></tr>
        <tr><td>Technical data: IP address, browser and device type, pages requested, times, errors, and the number of recent attempts (for example wrong lock answers) used to apply rate limits</td><td>Your browser</td><td>Running and securing the service, limiting abuse (for example repeated sign-in or unlock attempts), fixing faults</td></tr>
        <tr><td>Cookies and similar data (section 6)</td><td>Your browser</td><td>Keeping you signed in, protecting forms, understanding how the website is used</td></tr>
        <tr><td>Messages and reports you send us, and what we need to answer them</td><td>You</td><td>Support, requests about your information, takedown reports</td></tr>
      </tbody>
    </table>
  </div>
  <p>We do not ask for sensitive personal information (such as health, religion, or government IDs). If you choose to put it in a letter or photo, it is held like the rest of your content, and you are responsible for having the right to share it. We do not sell personal information, do not show advertising, and do not use your content to train AI models.</p>

  <h2>3. Why we may process it (our legal grounds)</h2>
  <ul>
    <li><strong>To carry out our contract with you</strong> (your account, your pages, your orders).</li>
    <li><strong>Your consent</strong>, for example for analytics cookies and optional sign-in with Google or Facebook, which you can withdraw at any time (section 8).</li>
    <li><strong>A legal obligation</strong>, such as keeping records of payments, or answering lawful orders.</li>
    <li><strong>Our legitimate interests</strong> in keeping the service secure, preventing fraud and abuse, and improving it, which we weigh against your rights.</li>
  </ul>

  <h2>4. Who we share it with</h2>
  <p>Only as needed, and never for their own advertising:</p>
  <ul>
    <li><strong>Hosting and file storage</strong> (currently DigitalOcean) holds our servers, database and your uploaded photos.</li>
    <li><strong>Payment providers</strong> (currently PayMongo and, where offered, Xendit, and the e-wallets and banks behind them) process payments. Card details go from your browser straight to them.</li>
    <li><strong>Google</strong> provides sign-in, analytics (Google Analytics) and fonts; <strong>Meta</strong> provides Facebook sign-in; <strong>YouTube</strong> plays the song on a page, so a visitor’s browser contacts YouTube when the song loads.</li>
    <li><strong>People you share a page with</strong> see what you put on that page. Anyone with the link (and the answer, if you set one) can see it.</li>
    <li><strong>Authorities and advisers</strong> when the law requires it, to protect someone from serious harm, or to establish or defend a legal claim, and professional advisers bound by confidentiality.</li>
    <li><strong>A successor</strong> if the service is transferred to another operator, who will be bound by this policy.</li>
  </ul>

  <h2>5. Where it goes</h2>
  <p>Our providers run data centres in and outside the Philippines, so your information may be processed in other countries. We use providers that protect it by contract and security measures, and we stay accountable for it under the Data Privacy Act.</p>

  <h2>6. Cookies and analytics</h2>
  <ul>
    <li><strong>Needed to work:</strong> a session cookie (<code>naluz_session</code>, lasts up to two hours of inactivity) that keeps you signed in, and a security token on forms. These cannot be switched off if you want to sign in.</li>
    <li><strong>Analytics:</strong> on the landing page, sign-in, sign-up and dashboard we use Google Analytics 4, which sets cookies (<code>_ga</code>, <code>_ga_*</code>) and tells us in aggregate how the website is used. It is <strong>not</strong> on the pages people make for each other, nor on the admin area.</li>
    <li><strong>YouTube:</strong> if a page has a song, YouTube may set its own cookies when the player loads.</li>
    <li><strong>Fonts:</strong> text fonts are loaded from Google Fonts, which sees your IP address.</li>
  </ul>
  <p>You can block or delete cookies in your browser, or opt out of Google Analytics with Google’s browser add-on (tools.google.com/dlpage/gaoptout). Blocking analytics does not affect the service.</p>

  <h2>7. How long we keep it</h2>
  <ul>
    <li><strong>Your account and content:</strong> until you delete them or ask us to, or until we close an inactive free account after reasonable notice.</li>
    <li><strong>Deleted photos and pages:</strong> removed from the service straight away; the files are deleted from storage shortly after, and may remain in backups until those expire in the ordinary course.</li>
    <li><strong>Expired plans:</strong> your content stays so you can renew; it is not shown to visitors.</li>
    <li><strong>Payment and order records:</strong> as long as Philippine tax, accounting and anti-fraud rules require. If you have paid, we deactivate rather than erase your account and remove everything else.</li>
    <li><strong>Technical logs:</strong> short-lived (days to a few weeks), except what we need to investigate abuse or a security incident.</li>
    <li><strong>Reports and requests:</strong> as long as needed to handle them and show we did.</li>
  </ul>

  <h2 id="rights">8. Your rights</h2>
  <p>Under the Data Privacy Act you have the right to: be informed; access your personal information; object to its processing or withdraw consent; have it corrected; have it erased or blocked in the cases the law allows; receive a copy in a common format (data portability); claim compensation for damage from unlawful processing; and complain to the National Privacy Commission.</p>
  <p>To use any of them, write to <strong>{{ $legal['email'] }}</strong> from the e-mail address on your account, say what you want, and, for a page, give its link. We may ask for proof it is you. We aim to reply within 30 days. We may refuse or limit a request where the law allows (for example to protect another person’s privacy, or because we must keep payment records), and we will tell you why.</p>
  <p>You can also correct your own content in the dashboard, delete a page or photo yourself at any time, cancel a subscription, and sign out of Google or Facebook access in those services’ settings.</p>

  <h2>9. Other people in your content</h2>
  <p>A page often shows another person: their name, face, voice-like details in a letter. By putting it there you promise you have their permission, and you take responsibility for it (Terms, section 5). If a photo of you is on a page without your agreement, or someone shared something private about you, tell us at {{ $legal['email'] }} with the page link and we will act on it (Terms, section 7). People who only open a page do not need an account; we process their IP address and the fact that they opened or tried to unlock a page, to keep it private and prevent abuse, and nothing about them is shared with the sender.</p>

  <h2>10. Children</h2>
  <p>The service is for people 16 and over. If you are under 18 you need the permission of a parent or guardian to use it and they must make any purchase. We do not knowingly collect information from anyone under 16. If you think a child has given us information, or that a photo of a child was shared without a parent’s permission, write to us and we will remove it. We never allow sexual content involving anyone under 18 and report it to the authorities.</p>

  <h2>11. How we protect it</h2>
  <p>Pages are hidden from search engines unless the owner turns on search. A lock answer is stored as a hash and checked on our server; the photos and letter of a locked page are not sent to the browser until it is right. Traffic uses HTTPS. Passwords are hashed with Argon2id; payment references are encrypted. Sign-in, unlock and form attempts are rate-limited. Access to our systems is limited to people who need it. No system is perfectly secure, so please use a strong, unique password and keep the link and answer of a private page to yourself.</p>
  <p>If a breach seriously affects your information, we will tell the National Privacy Commission and the people affected within the time the law requires (72 hours from knowing of it for breaches that need notice), and say what happened and what to do.</p>

  <h2>12. Changes</h2>
  <p>When we change this policy we will post the new version here with a new date, and tell you in the service or by e-mail if the change matters. Changes to how we use your information in ways you have not agreed to will need your consent.</p>

  <h2>13. Complaints</h2>
  <p>Please tell us first: we want to put it right. You may also complain to the National Privacy Commission of the Philippines (privacy.gov.ph).</p>
</main>
<footer class="footer">
  <div class="container">
    @include('partials.brand')
    <span><a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · Made with love in the Philippines.</span>
  </div>
</footer>
@endsection
