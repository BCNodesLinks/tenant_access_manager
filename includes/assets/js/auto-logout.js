// assets/js/auto-logout.js

(function () {
  // Configuration
  const inactivityTime = tamSettings.inactivityTime || 60 * 60 * 1000; // 60 minutes in milliseconds
  const logoutUrl = tamSettings.logoutUrl || "/"; // Logout URL

  let timer;

  // Function to reset the timer
  function resetTimer() {
    clearTimeout(timer);
    timer = setTimeout(triggerLogout, inactivityTime);
  }

  // Function to trigger logout
  function triggerLogout() {
    window.location.href = logoutUrl;
  }

  // List of events to consider as user activity
  const events = ["mousemove", "keydown", "click", "scroll", "touchstart"];

  // Attach event listeners for each event
  events.forEach(function (event) {
    window.addEventListener(event, resetTimer, false);
  });

  // Initialize the timer when the script loads
  resetTimer();
})();
