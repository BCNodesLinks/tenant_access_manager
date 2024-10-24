// includes/assets/js/auto-logout.js

(function () {
  // Retrieve the inactivity time and logout URL from the localized script variables
  var inactivityTime = tamSettings.inactivityTime || 1800000; // Default to 30 minutes if not set
  var logoutUrl = tamSettings.logoutUrl || "/";

  var timeout;

  function resetTimer() {
    clearTimeout(timeout);
    timeout = setTimeout(function () {
      // Append ?autologout=1 to the logoutUrl when auto-logging out
      var autoLogoutUrl =
        logoutUrl +
        (logoutUrl.indexOf("?") === -1 ? "?" : "&") +
        "autologout=1";
      window.location.href = autoLogoutUrl;
    }, inactivityTime);
  }

  // List of events that reset the inactivity timer
  document.addEventListener("mousemove", resetTimer);
  document.addEventListener("keypress", resetTimer);
  document.addEventListener("click", resetTimer);
  document.addEventListener("scroll", resetTimer);
  document.addEventListener("touchstart", resetTimer); // For touch devices
  document.addEventListener("touchmove", resetTimer); // For touch devices

  // Initialize the timer when the script loads
  resetTimer();
})();
