{{-- The first-visit tour of the page editor (same machinery as partials/tour; remembered as users.editor_tour_seen_at). --}}
<ol id="tour-steps" data-url="/dashboard/tour" data-tour="editor" hidden>
  <li data-title="Let's build the page 💌">This is where the page for {{ $page->recipient }} comes together. A quick look at each part, in the order you will use them.</li>
  <li data-target=".section-nav" data-title="Jump between the parts">These tabs take you to the letter, photos, song, lock screen, design, sharing and plan. They stay in view as you scroll.</li>
  <li data-target="#message" data-title="Write the letter">Your words scroll slowly beside the photos. Leave an empty line between paragraphs.</li>
  <li data-target="#photos" data-title="Add the photos">They play in order, one every few seconds. Photos need a plan first: subscribe, then add as many as your plan includes.</li>
  <li data-target="#song" data-title="Pick your song">Paste a YouTube link. It starts softly the moment the page opens.</li>
  <li data-target="#lock" data-title="Lock it with a question">Ask something only they can answer. Nothing on the page is sent until they get it right.</li>
  <li data-target="#design" data-title="Choose the design">Pick an occasion, one of its designs and a colour theme. The preview shows each change.</li>
  <li data-target="#preview-frame" data-title="Preview as you go">Everything you change shows here, so you see exactly what they will see.</li>
  <li data-target="#share" data-title="Share it">Send the link in a message, or print the QR code on a card.</li>
  <li data-target="#plan" data-title="Put it online">The page is a private draft until you choose a plan. Then it goes live and the link works.</li>
</ol>
