// assets/js/tam-toast.js

(function () {
  document.addEventListener("DOMContentLoaded", function () {
    const message = tamToastSettings.message || "You have been logged out.";

    // Create the toast container
    const toast = document.createElement("div");
    toast.className = "tam-toast";

    // Create the message span
    const messageSpan = document.createElement("span");
    messageSpan.textContent = message;

    // Create the close button
    const closeButton = document.createElement("button");
    closeButton.className = "tam-toast-close";
    closeButton.innerHTML = "&times;"; // HTML entity for multiplication sign (×)

    // Append elements to the toast
    toast.appendChild(messageSpan);
    toast.appendChild(closeButton);
    document.body.appendChild(toast);

    // Show the toast with a fade-in effect
    setTimeout(function () {
      toast.classList.add("tam-show");
    }, 100); // Slight delay for CSS transition

    // Handle close button click
    closeButton.addEventListener("click", function () {
      toast.classList.remove("tam-show");
      // Remove the toast from DOM after transition
      setTimeout(function () {
        toast.remove();
      }, 500); // Match the CSS transition duration
    });
  });
})();
