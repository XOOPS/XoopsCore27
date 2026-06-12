// theme-toggle.js

document.addEventListener("DOMContentLoaded", () => {
    const toggleBtn = document.getElementById("theme-toggle");
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const savedTheme = localStorage.getItem("theme") || (prefersDark ? "dark" : "light");

    applyTheme(savedTheme);

    if (!toggleBtn) {
        return;
    }

    toggleBtn.innerHTML = savedTheme === "dark" ? "☀️" : "🌙";

    toggleBtn.addEventListener("click", () => {
        const newTheme = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
        applyTheme(newTheme);
        toggleBtn.innerHTML = newTheme === "dark" ? "☀️" : "🌙";
    });

    function applyTheme(theme) {
        document.documentElement.setAttribute("data-theme", theme);
        document.documentElement.setAttribute("data-bs-theme", theme);
        localStorage.setItem("theme", theme);
    }
});
