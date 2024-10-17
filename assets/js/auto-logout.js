(function ($) {
  var inactivityTime = tamSettings.inactivityTime;
  var logoutNonce = tamSettings.logoutNonce;
  var timeout;

  function logout() {
    // Redirect to logout URL with nonce
    window.location.href = "/?tam_logout=1&tam_logout_nonce=" + logoutNonce;
  }

  function resetTimer() {
    clearTimeout(timeout);
    timeout = setTimeout(logout, inactivityTime);
  }

  // Reset timer on various user interactions
  $(document).on("mousemove keypress click scroll", resetTimer);

  // Initialize timer
  resetTimer();
})(jQuery);
