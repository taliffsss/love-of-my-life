# Response templates

Ready replies for the requests this service will get. Fill in the `{{variables}}`, check the escalation triggers first, have a second
person read anything that goes to a regulator, a lawyer or a minor, and log every request (date received, what, who answered, date answered).
Reply from `LEGAL_CONTACT_EMAIL`. **Drafts, not legal advice.**

## Escalate instead of using a template when

- the sender is a lawyer, police, a court or a regulator (including the National Privacy Commission), or the matter may become a case;
- a **minor** is the requester or the subject, or the report mentions sexual content or any threat to a person (stop, preserve evidence,
  report to the authorities and call counsel; do not reply with a template);
- the data subject is in a dispute with us, or the request is unusually broad;
- the request would reveal another person’s information (a page’s content belongs to its owner and the people in it);
- a breach is suspected (use the breach procedure and the notice at the end).

## How the requests are handled today (what the app can and cannot do)

| Request | How | Note |
|---|---|---|
| Access / copy | Collect the account’s name, e-mail, pages (letter, song, lock question, plan, dates), photo files, orders. Send by secure link or encrypted attachment to the account’s e-mail only | No automated export yet |
| Correction | The user edits in the dashboard; for the name or e-mail an admin edits the account | |
| Deletion | An admin deletes the account with `App\Services\AccountRemoval`: pages, photos (files to the storage trash then deleted), social sign-ins, unpaid orders. **Refused when the account has paid orders** (records must be kept): deactivate it instead and delete everything else | No self-service deletion yet |
| Take a page down | An admin deactivates or suspends the owner’s account (a page of an inactive account is not shown). There is no per-page takedown yet | Keep a copy of the report and what was done |
| Stop analytics | Point to Google’s opt-out add-on and browser settings (Policy §6) | |

Deadlines to aim for: acknowledge within 3 days; answer within 30 days; takedown reports with a risk to a person: within 24 hours.

---

## T1. Acknowledge a privacy request

**Subject**: Your request about your information · Ref {{ref}}

Hi {{name}},

Thank you for writing. We received your request on {{date_received}} to {{request_type: see a copy of / correct / delete / stop using}} your personal information. Your reference is {{ref}}.

To make sure we only share information with its owner, please reply from the e-mail address on your account ({{account_email}}) and tell us {{what_we_need: the link of the page you mean, or what to correct}}.

We will reply by {{deadline}} (within 30 days). If we need more time or cannot do part of it, we will tell you why.

{{operator}} · {{legal_email}}

## T2. Send a copy (access)

**Subject**: Your copy of your information · Ref {{ref}}

Hi {{name}},

As requested, here is a copy of the information we hold about your account: {{link_or_attachment}} (valid for {{days}} days; it contains your private content, so please keep it safe). It includes your account details, your pages with their letters, songs and photos, and your orders.

It does not include information about other people that we are not allowed to share, or technical logs that cannot be tied to you.

If something looks wrong or missing, reply to this e-mail. You can also correct, delete or take back your consent at any time.

## T3. Confirm a correction

Hi {{name}}, we have changed {{what}} on your account as you asked on {{date}}. Please check it and tell us if it is not right.

## T4. Confirm a deletion

**Subject**: Your account has been deleted · Ref {{ref}}

Hi {{name}},

As you asked, we deleted your account, your {{n_pages}} page(s) and {{n_photos}} photo(s) on {{date}}. The photo files are removed from our storage and may remain in backups until those expire in the ordinary course.

{{if_paid: We keep a record of your payments (order references, dates and amounts) for as long as Philippine tax and accounting rules require. Your account is deactivated and cannot be used; everything else has been removed.}}

If you change your mind you can make a new account any time.

## T5. Refuse or limit a request

**Subject**: About your request · Ref {{ref}}

Hi {{name}},

Thank you for your request of {{date_received}}. We are not able to {{what}} because {{reason: it would reveal another person’s information / we must keep payment records by law / we could not confirm it is you}}.

What we can do instead: {{alternative}}. You may ask us to reconsider, and you can complain to the National Privacy Commission (privacy.gov.ph).

## T6. More time

Hi {{name}}, your request {{ref}} needs more time because {{reason}}. We expect to answer by {{new_date}}. Thank you for your patience.

---

## T6b. Decline a refund request

**Subject**: About your payment · Order {{order_ref}}

Hi {{name}},

Thank you for writing. Payments for {{brand}} plans are final and non-refundable, as set out in section 4 of our Terms ({{terms_url}}), and we are not able to refund order {{order_ref}}. You can still use your plan until {{date}}, and you can cancel renewal in your dashboard so you are not charged again.

{{if_mistake: We did find that you were charged {{twice / after cancelling}}. We have reversed {{amount}} and it should reach your {{method}} in {{days}} days.}}

If you think we are wrong, reply with your order reference and what happened and we will look again.

(Escalate instead if the person says they were charged without agreeing, is a minor, or has started a chargeback or a complaint with a regulator.)

## T7. Receive a report about a page or photo (takedown)

**Subject**: We received your report · Ref {{ref}}

Hi {{name}},

Thank you for telling us. We received your report on {{date}} about {{page_link}}. We take reports of unwanted or harmful content seriously and will look at it {{within 24 hours if it involves a risk to a person; otherwise within 3 working days}}.

To act, we may need: {{what: your contact details, proof you are the person in the photo, or the work you own}}. We do not tell the page’s owner who reported it unless the law requires it.

If you or someone else is in danger, please contact the police or {{local_hotline}} first.

## T8. Tell the owner we acted (content removed or account suspended)

**Subject**: About your page · Ref {{ref}}

Hi {{name}},

We {{removed / hid}} your page {{page}} on {{date}} because {{reason: it breaks section 6 of our Terms (it showed a person without their consent)}}. {{We also suspended your account / Your account is unchanged.}}

If you think we made a mistake, reply with your explanation and we will review it. We may keep the information needed to deal with the report and may give it to the authorities when the law requires it.

## T9. Tell the reporter the outcome

Hi {{name}}, we reviewed your report {{ref}} and {{removed the content / did not find a breach of our Terms / need more information: …}}. If you disagree, reply and we will look again. You can also contact the police, the Department of Justice Office of Cybercrime or the National Privacy Commission.

## T10. Copyright claim (additional details to request)

The work and where it appears · proof you own it · your name and contact details · a statement that you are the owner or authorised · a statement that the claim is made in good faith. Then use T7 and T8; if the owner replies with a counter-claim, **escalate** to counsel.

---

## T11. Notice of a personal data breach to affected users

(Use only after the decision to notify, taken with counsel. The National Privacy Commission and affected people must be told within the legal time of knowing about a breach that needs notice, 72 hours.)

**Subject**: Important: a security incident affecting your information

Hi {{name}},

On {{date}} we found that {{what_happened, in plain words}}. The information involved was {{kinds_of_information}}. {{It was / It may have been}} seen by {{who}}. We {{what_we_did_to_stop_it}}.

What you can do: {{change your password / watch for messages that ask for money or codes / …}}.

What we are doing: {{steps}}. Questions: {{legal_email}} {{phone}}. We are sorry.

{{operator}}

## T12. Parent or guardian asks to remove a minor’s account or photos

**Escalation trigger: minor.** Acknowledge (T1), confirm identity and guardianship by asking the account holder’s e-mail to confirm or the parent’s ID through a secure channel, then treat as a deletion (T4). Keep payment records. If the minor’s content is sexual or the minor is at risk, stop and follow the escalation steps above.
