{{-- The first-visit tour of the dashboard, shown once: a popover that steps over the page (public/assets/ui.js, "tour"). Finishing
     or skipping it POSTs to /dashboard/tour, which remembers it on the account. A step without a target is centred. --}}
<ol id="tour-steps" data-url="/dashboard/tour" data-tour="dashboard" hidden>
  <li data-title="Welcome to {{ $brand }} 💌">A page is a love letter for one person: your photos, your song and your words, opened with a question only they can answer. This 1-minute tour shows you how.</li>
  <li data-target="#recipient" data-title="Who is it for?">Start with a name, or what you call them. Every page is for one person.</li>
  <li data-target=".create-form .btn-primary" data-title="Create the page">This opens the editor, with tabs along the top for the letter, photos, song, lock question, design and sharing.</li>
  <li data-target=".occasions" data-title="Start from an occasion">Pick an occasion and a ready-made design for a letter, question and colours. You can change all of it afterwards.</li>
  <li data-target=".usage-card" data-title="Photos come with a plan">The letter, song and design are free to build and preview. Subscribe to a plan to add photos and put the page online. Photos are shared by all your pages.</li>
  <li data-title="You are ready ✨">Share the link or print the QR code on a card. They answer your question, the blur lifts, and your words begin to scroll.</li>
</ol>
