@if (count($socialProviders) > 0)
<div class="social" aria-label="Continue with">
  @if (in_array('google', $socialProviders, true))
  <a class="btn btn-secondary btn-lg btn-social" href="/auth/google/redirect">
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#4285F4" d="M23 12.3c0-.8-.1-1.500-.2-2.200H12v4.200h6.200a5.300 5.300 0 0 1-2.300 3.500v2.900h3.700c2.200-2 3.400-5 3.400-8.400z"/><path fill="#34A853" d="M12 24c3.100 0 5.700-1 7.600-2.800l-3.700-2.900c-1 .7-2.300 1.100-3.900 1.100-3 0-5.500-2-6.400-4.800H1.800v3A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.600 14.600a7.200 7.200 0 0 1 0-4.600V7H1.800a12 12 0 0 0 0 10.800l3.800-3.200z"/><path fill="#EA4335" d="M12 4.800c1.700 0 3.200.6 4.400 1.700l3.300-3.300A11.900 11.900 0 0 0 12 0 12 12 0 0 0 1.800 7l3.800 3c.9-2.800 3.400-5.200 6.400-5.200z"/></svg>
    Continue with Google
  </a>
  @endif
  @if (in_array('facebook', $socialProviders, true))
  <a class="btn btn-secondary btn-lg btn-social" href="/auth/facebook/redirect">
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="#1877F2" d="M24 12a12 12 0 1 0-13.900 11.900v-8.400H7.100V12h3V9.400c0-3 1.800-4.700 4.500-4.700 1.300 0 2.700.2 2.700.2v3h-1.500c-1.500 0-2 .9-2 1.900V12h3.400l-.5 3.500h-2.900v8.400A12 12 0 0 0 24 12z"/></svg>
    Continue with Facebook
  </a>
  @endif
  <p class="social-or small muted"><span>or with your e-mail</span></p>
</div>
@endif
