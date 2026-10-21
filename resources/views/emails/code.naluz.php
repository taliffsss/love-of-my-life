<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#fbf4f1;font-family:Helvetica,Arial,sans-serif;color:#2a1620">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fbf4f1;padding:32px 12px">
    <tr><td align="center">
      <table role="presentation" width="520" cellspacing="0" cellpadding="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:18px;border:1px solid #f0d9de">
        <tr><td style="padding:30px 32px 6px;font-family:Georgia,serif;font-size:22px;color:#c42f4b">{{ $brand }}</td></tr>
        <tr><td style="padding:8px 32px 0;font-size:20px;font-weight:bold">{{ $headline }}</td></tr>
        <tr><td style="padding:10px 32px 0;font-size:16px;line-height:1.6">Hi {{ $name }}, {{ $intro }}</td></tr>
        <tr><td align="center" style="padding:26px 32px">
          <div style="display:inline-block;background:#fdeef1;border:1px solid #f0c9d2;border-radius:14px;padding:16px 28px;font-size:36px;letter-spacing:10px;font-weight:bold;color:#c42f4b;font-family:Menlo,Consolas,monospace">{{ $code }}</div>
        </td></tr>
        <tr><td style="padding:0 32px 30px;font-size:13px;line-height:1.6;color:#6d5560">The code works for {{ $minutes }} minutes. Never share it with anyone: we will never ask you for it. If you did not ask for it, you can ignore this e-mail.</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
