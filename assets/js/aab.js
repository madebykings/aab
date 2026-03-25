jQuery(function ($) {
  const hasBrickFlow = $('.aab-custom-checkout').length > 0;

  if (hasBrickFlow && typeof AAB !== 'undefined') {
    setInterval(function () {
      $.post(AAB.ajax_url, {
        action: 'aab_refresh_reservation',
        nonce: AAB.nonce
      }).fail(function () {
        // Silent for now. We can add UX later if needed.
      });
    }, parseInt(AAB.heartbeat_interval_ms || 60000, 10));
  }
});
