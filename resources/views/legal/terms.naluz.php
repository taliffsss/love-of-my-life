@extends('layouts.product')
@section('title', 'Terms and Conditions · ' . $brand)
@section('description', 'The rules for using ' . $brand . ': accounts, plans and payments, what you may put on a page, and what to expect from us.')
@section('robots', 'index, follow')
@section('content')
<header class="container" style="padding-block:22px"><a href="/" aria-label="{{ $brand }}">@include('partials.brand')</a></header>
<main class="narrow legal">
  <p class="eyebrow">Legal</p>
  <h1 class="display">Terms and Conditions</h1>
  <p class="muted">Version {{ $legal['version'] }} · last updated {{ $legal['updated'] }}. Please read this together with our <a href="/privacy">Privacy Policy</a>.</p>

  <h2>1. Who we are and what you agree to</h2>
  <p>{{ $brand }} (the website at {{ config('app.url') }}, “the service”) is run by <strong>{{ $legal['operator'] }}</strong>, {{ $legal['address'] }} (“we”, “us”). You can reach us at <strong>{{ $legal['email'] }}</strong>.</p>
  <p>By creating an account, signing in, buying a plan or otherwise using the service you agree to these Terms. If you do not agree, please do not use it. Opening a page that someone sent you does not need an account, and the parts of these Terms that apply to you are sections 6, 7 and 14.</p>

  <h2>2. What the service is</h2>
  <p>The service lets you build a private web page (“a page”) from your own photos, a letter, a song and a question, choose how it opens, and share it with someone by link or QR code. You can build and preview everything for free; a page is visible to other people only after you buy a plan. Plans, prices, photo allowances and the templates each plan includes are shown on the website and at checkout, and they may change for future purchases.</p>
  <p>Features may be added, changed or removed, and some may be switched off or not yet available; we do not promise a feature that is not shown to you when you buy.</p>

  <h2>3. Who may use it</h2>
  <ul>
    <li><strong>Age.</strong> You must be at least 16. If you are under 18 (the age of majority in the Philippines) you may build and preview pages only with the knowledge and permission of a parent or legal guardian, and a parent or guardian must make any purchase and accept these Terms for you.</li>
    <li><strong>Your account.</strong> Give true information and keep it current. You are responsible for your password and for what happens under your account; tell us promptly if you think someone else has used it. One person, one account.</li>
    <li><strong>Sign-in with Google or Facebook</strong> uses the details they send us (see the Privacy Policy). Their own terms apply to your account with them.</li>
  </ul>

  <h2>4. Plans, payment and renewal</h2>
  <ul>
    <li><strong>Prices</strong> are in Philippine pesos and are the ones shown at checkout. If a tax applies we show it before you pay.</li>
    <li><strong>Paying.</strong> Payment is handled by our payment providers (currently PayMongo and, where offered, Xendit) by GCash, Maya or card, or by another method we show you. We do not receive or store your full card number or e-wallet login. Some payments are confirmed by us by hand and may take time to appear.</li>
    <li><strong>What a plan gives you.</strong> Monthly and yearly plans keep a page online for that period (a year is twelve months, with no discount) and add to the time you have left. A one-time plan (Forever) keeps the page online for as long as the service runs, subject to section 10. Photo packs add photo space for good.</li>
    <li><strong>Automatic renewal.</strong> Where you choose a card or Maya subscription, it renews until you cancel; we charge the price shown when you subscribed unless we told you of a change before the renewal. GCash payments renew only when you approve each payment. You can cancel a subscription in your dashboard at any time; it then stops at the end of the period you have paid for.</li>
    <li id="refunds"><strong>No refunds.</strong> Every payment is final and non-refundable: this applies to all plans and subscriptions (monthly, yearly and one-time; card, Maya, GCash or any other method), to renewals, to photo packs, and to unused time. You can build and preview for free before you pay, so please do so. To avoid the next charge, cancel a subscription in your dashboard before its renewal date; you keep the plan until the end of the period you paid for. This does not take away any right you have under Philippine law that cannot be waived.</li>
    <li><strong>When a plan ends.</strong> The page stops being shown to visitors and uploads are blocked; nothing is deleted. Renewing brings it back with the same link. If you do not renew we may delete the page after a reasonable period and a reminder, but we will not delete content of a paid page without notice.</li>
    <li><strong>Changing plans.</strong> You can upgrade at any time from your dashboard. Moving to a plan with fewer photos or templates may hide content that no longer fits.</li>
  </ul>

  <h2>5. Your content</h2>
  <p>“Your content” is everything you put into the service: photos, letters, names, songs you choose, questions and answers.</p>
  <ul>
    <li><strong>It stays yours.</strong> We do not claim ownership.</li>
    <li><strong>What you allow us to do.</strong> You give us a worldwide, non-exclusive licence to store, copy, resize, format and display your content only as needed to run the service for you: to build your page, show it to the people you share it with, make backups and keep the service secure. It ends when your content is deleted, apart from backups that expire in the ordinary course.</li>
    <li><strong>What you promise.</strong> That you made it or have the right to use it, that you have the permission of everyone who can be recognised in your photos or named in your letter (in particular the person the page is for), and that it follows section 6. Songs are played from YouTube; we do not host music, and you must not upload recordings you have no right to share.</li>
    <li><strong>Private by design, not by magic.</strong> A page is shown only to people who have its link and, if you set one, the answer to its question. The answer is stored hashed and checked on our server before your photos and letter are sent. Pick an answer that is not easy to guess, share the link only with the person you mean, and remember that anyone who has both can open the page and copy what they see. We are not responsible for what a person does with a page you shared with them.</li>
  </ul>

  <h2>6. Rules</h2>
  <p>You must not use the service to, or put on a page, anything that:</p>
  <ul>
    <li>is unlawful, or infringes anyone’s copyright, trademark, privacy or other rights;</li>
    <li>is sexually explicit or nude, or shows or sexualises anyone under 18 in any way (we report these to the authorities);</li>
    <li>shows or shares intimate images or videos of a person without that person’s consent, or was taken in a place where they expected privacy;</li>
    <li>harasses, stalks, threatens, shames, blackmails or impersonates someone, sends unwanted contact to a person who has asked you to stop, or discloses their private information (doxxing);</li>
    <li>is hateful, violent, defamatory or promotes self-harm;</li>
    <li>is spam, a scam, a phishing page, or malware, or tries to get money or personal data by deceit;</li>
    <li>is used to commit fraud or collect payments of any kind (the service is not a shop or a payment page).</li>
  </ul>
  <p>You also must not: probe, scan or attack the service; bypass limits, plans, rate limits or the lock of any page; use bots or scrapers on it; resell or sublicense it; or use it to build a competing product from our templates, artwork or code.</p>

  <h2>7. Reports and takedowns</h2>
  <p>If you think a page or content breaks these Terms or your rights (including a photo of you shared without consent, a copyright infringement, or a page used to harass someone), write to <strong>{{ $legal['email'] }}</strong> with: the page link; what is wrong and why; who you are and how to reach you; and, for a copyright claim, the work and proof you own it. You do not need to be a user. If a page is locked, tell us anyway: we can act on a link.</p>
  <p>We may remove or hide content, or suspend a page or account, straight away where a rule has been broken or where there is a risk of harm, and we tell the account owner unless the law or the safety of a person prevents it. The owner may reply and ask us to review the decision. We may keep information needed to deal with a report and give it to the authorities when the law requires it or to protect someone from serious harm. We do not routinely read your pages; we look at content only to answer a report, keep the service secure, give support you ask for or follow the law.</p>

  <h2>8. Third-party services</h2>
  <p>The service relies on others: hosting and file storage providers, payment providers (PayMongo, Xendit), Google (sign-in, analytics, fonts), Meta (Facebook sign-in) and YouTube (songs). Their terms and privacy policies apply to what they do, and we are not responsible for their services. Links to other sites are not endorsements.</p>

  <h2>9. Our rights</h2>
  <p>The service, its software, designs, templates, artwork, the Lettie character and the Love of My Life name and logo belong to us or our licensors. You get a personal, non-transferable right to use the service under these Terms. If you send us feedback, we may use it without owing you anything.</p>

  <h2>10. Suspension and ending</h2>
  <ul>
    <li><strong>By you.</strong> You can stop using the service at any time and delete your pages in the dashboard. To delete your account, write to us (section 17). An account with paid orders is deactivated, not erased, because we must keep records of payments; we remove everything else.</li>
    <li><strong>By us.</strong> We may deactivate, suspend or close an account or take down a page if you break these Terms, if we must by law, if your payment is reversed or charged back, or if you put others or the service at risk. Where we can, we will warn you first. As stated in section 4, payments are not refunded when we suspend or close an account for a breach.</li>
    <li><strong>Closing the service.</strong> If we stop offering the service, we will tell you at least 60 days before, and let you download your photos and letter. Pages stay online until the end of the notice period, and recurring plans are not charged again after the day we tell you the service will end.</li>
  </ul>

  <h2>11. Availability and changes to the service</h2>
  <p>We work to keep the service running but do not promise it will never be interrupted, slow or lose data. Keep your own copy of your photos and letters. We may change or discontinue features; we will tell you about changes that matter to something you have paid for.</p>

  <h2>12. No warranty</h2>
  <p>To the fullest extent the law allows, the service is provided “as is” and “as available”, without promises that it will be error-free, uninterrupted, secure against every attack, or fit for a particular purpose. Nothing here limits rights you have under Philippine consumer law that cannot be waived.</p>

  <h2>13. Limit of our liability</h2>
  <p>To the extent the law allows, we are not liable for indirect or consequential loss, lost profits, loss of data or loss of a relationship or opportunity, or for anything a person does with a page that you shared. Our total liability to you for any claim about the service is limited to the greater of (a) what you paid us in the twelve months before the claim and (b) ₱1,000. This does not limit liability that cannot be limited by law, such as for fraud or wilful misconduct, or for death or injury caused by negligence.</p>

  <h2>14. If you are the person a page was made for</h2>
  <p>You may leave a page, ask us to take it down (section 7), or ask the person who made it to remove you from it. We do not give the sender your details, and we do not require you to sign up to see a page.</p>

  <h2>15. If something goes wrong between you and someone else</h2>
  <p>Disputes between users, or between you and a person on a page, are yours to resolve; we are not a party. You agree to compensate us for loss and claims (including reasonable legal costs) caused by your content or by your breach of section 5 or 6, to the extent the law allows.</p>

  <h2>16. Changes to these Terms</h2>
  <p>We may update these Terms. We will post the new version here with a new date and, for changes that matter, tell you in the service or by e-mail at least 14 days before they apply to you. If you keep using the service after that date you accept the new Terms; if you do not agree, you may stop using the service and cancel any subscription before its next renewal.</p>

  <h2>17. Contact</h2>
  <p>{{ $legal['operator'] }} · {{ $legal['address'] }} · <strong>{{ $legal['email'] }}</strong>. For privacy requests see the <a href="/privacy">Privacy Policy</a>.</p>

  <h2>18. Governing law and disputes</h2>
  <p>These Terms are governed by the laws of the Republic of the Philippines. Please write to us first and give us 30 days to put things right; most problems can be settled that way. If not, you and we submit to the proper courts of the Philippines, without limiting any right you have under law to bring a complaint to a government agency such as the Department of Trade and Industry or the National Privacy Commission.</p>

  <h2>19. The rest</h2>
  <p>If a part of these Terms is found unenforceable, the rest stays in force. Our not enforcing a right is not a waiver of it. You may not transfer your account or these Terms without our consent; we may transfer ours to a successor of the service. These Terms (with the Privacy Policy and what you see at checkout) are the whole agreement between you and us about the service. They are provided in English; a translation is for convenience only.</p>

  <p class="small muted" style="margin-top:32px">This is a plain-language document written for this service. It is not a substitute for advice from your own lawyer.</p>
</main>
<footer class="footer">
  <div class="container">
    @include('partials.brand')
    <span><a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · Made with love in the Philippines.</span>
  </div>
</footer>
@endsection
