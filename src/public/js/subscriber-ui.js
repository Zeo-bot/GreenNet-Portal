(function () {
    "use strict";

    const STORAGE_THEME = "greennet.admin.theme";

    function getStored(key, fallback) {
        try {
            return localStorage.getItem(key) || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function setStored(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            // ignore
        }
    }

    function applyTheme(theme) {
        const selected = theme === "greennet-dark" ? "greennet-dark" : "greennet-light";

        if (selected === "greennet-dark") {
            document.documentElement.setAttribute("data-theme", "greennet-dark");
        } else {
            document.documentElement.removeAttribute("data-theme");
        }

        setStored(STORAGE_THEME, selected);
    }

    function currentTheme() {
        return getStored(STORAGE_THEME, "greennet-light") === "greennet-dark"
            ? "greennet-dark"
            : "greennet-light";
    }

    function createThemeButton() {
        if (document.querySelector(".gn-sub-theme")) return;

        const panel = document.createElement("div");
        panel.className = "gn-sub-theme";

        const button = document.createElement("button");
        button.type = "button";
        button.textContent = currentTheme() === "greennet-dark" ? "Light" : "Dark";

        panel.appendChild(button);
        document.body.appendChild(panel);

        button.addEventListener("click", function () {
            const next = currentTheme() === "greennet-dark" ? "greennet-light" : "greennet-dark";
            applyTheme(next);
            button.textContent = next === "greennet-dark" ? "Light" : "Dark";
        });
    }

    function markActiveBottomNav() {
        const path = window.location.pathname.replace(/\/+$/, "") || "/";

        document.querySelectorAll(".gn-sub-bottom-nav a").forEach((link) => {
            const href = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";

            if (href === path) {
                link.classList.add("is-active");
            }
        });
    }

    function addLoadingToForms() {
        document.querySelectorAll("form").forEach((form) => {
            form.addEventListener("submit", function () {
                const button = form.querySelector('button[type="submit"], input[type="submit"]');

                if (button) {
                    button.classList.add("is-loading");
                    button.setAttribute("disabled", "disabled");
                }
            });
        });
    }

    function init() {
        document.body.classList.add("gn-subscriber-ui-ready");
        applyTheme(currentTheme());
        createThemeButton();
        markActiveBottomNav();
        addLoadingToForms();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();