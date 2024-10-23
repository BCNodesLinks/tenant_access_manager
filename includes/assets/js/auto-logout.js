// assets/js/auto-logout.js

(function () {
  // Configuration
  const inactivityTime = tamSettings.inactivityTime || 1 * 60 * 1000; // 60 minutes
  const warningTime = tamSettings.warningTime || 1 * 10 * 1000; // 5 minutes before logout
  const logoutUrl = tamSettings.logoutUrl || "/"; // Logout URL

  let logoutTimer;
  let warningTimer;
  let warningDisplayed = false;

  // Function to reset both timers
  function resetTimers() {
    clearTimeout(logoutTimer);
    clearTimeout(warningTimer);
    hideWarning();
    warningDisplayed = false;
    logoutTimer = setTimeout(triggerLogout, inactivityTime);
    warningTimer = setTimeout(showWarning, inactivityTime - warningTime);
  }

  // Function to trigger logout
  function triggerLogout() {
    window.location.href = logoutUrl;
  }

  // Function to show warning modal
  function showWarning() {
    if (warningDisplayed) return;
    warningDisplayed = true;

    // Create modal HTML
    const modal = document.createElement("div");
    modal.id = "tam-auto-logout-modal";
    modal.style.position = "fixed";
    modal.style.top = "0";
    modal.style.left = "0";
    modal.style.width = "100%";
    modal.style.height = "100%";
    modal.style.background = "rgba(0,0,0,0.5)";
    modal.style.display = "flex";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.zIndex = "9999";

    const modalContent = document.createElement("div");
    modalContent.style.background = "#fff";
    modalContent.style.padding = "20px";
    modalContent.style.borderRadius = "5px";
    modalContent.style.textAlign = "center";

    const message = document.createElement("p");
    message.textContent =
      "You will be logged out due to inactivity in 5 minutes.";

    const stayLoggedInButton = document.createElement("button");
    stayLoggedInButton.id = "tam-stay-logged-in";
    stayLoggedInButton.textContent = "Stay Logged In";
    stayLoggedInButton.style.padding = "10px 20px";
    stayLoggedInButton.style.marginTop = "10px";

    // Append elements
    modalContent.appendChild(message);
    modalContent.appendChild(stayLoggedInButton);
    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Handle 'Stay Logged In' button click
    stayLoggedInButton.addEventListener("click", function () {
      resetTimers();
    });
  }

  // Function to hide warning modal
  function hideWarning() {
    const modal = document.getElementById("tam-auto-logout-modal");
    if (modal) {
      document.body.removeChild(modal);
    }
  }

  // List of events to consider as user activity
  const events = ["mousemove", "keydown", "click", "scroll", "touchstart"];

  // Attach event listeners for each event
  events.forEach(function (event) {
    window.addEventListener(event, resetTimers, false);
  });

  // Initialize the timers when the script loads
  resetTimers();
})();
