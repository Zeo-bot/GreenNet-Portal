(function () {
    "use strict";

    const STORAGE_THEME = "greennet.admin.theme";
    const STORAGE_DENSITY = "greennet.admin.density";
    const STORAGE_LANG = "greennet.admin.lang";

    const themes = [
        { value: "greennet-light", label: "GreenNet Light" },
        { value: "greennet-dark", label: "GreenNet Dark" },
        { value: "cloud-light", label: "Cloud Light" },
        { value: "high-contrast", label: "High Contrast" }
    ];

    function getCookie(name) {
        const parts = document.cookie.split(";").map((item) => item.trim());
        const prefix = name + "=";

        for (const part of parts) {
            if (part.indexOf(prefix) === 0) {
                return decodeURIComponent(part.substring(prefix.length));
            }
        }

        return "";
    }

    function setCookie(name, value, days) {
        const maxAge = days * 24 * 60 * 60;
        document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge + "; SameSite=Lax";
    }

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

    function validTheme(theme) {
        return themes.some((item) => item.value === theme) ? theme : "greennet-light";
    }

    function htmlLang() {
        const lang = (document.documentElement.getAttribute("lang") || "ar").toLowerCase();

        return lang === "en" ? "en" : "ar";
    }

    function currentLang() {
        const cookie = getCookie("greennet_admin_lang");

        if (cookie === "en" || cookie === "ar") {
            return cookie;
        }

        const stored = getStored(STORAGE_LANG, "");

        if (stored === "en" || stored === "ar") {
            return stored;
        }

        return htmlLang();
    }

    function applyBodyLanguageClasses(lang) {
        const selected = lang === "en" ? "en" : "ar";

        document.documentElement.setAttribute("lang", selected);
        document.documentElement.setAttribute("dir", selected === "en" ? "ltr" : "rtl");

        if (!document.body) {
            return;
        }

        document.body.setAttribute("data-admin-lang", selected);
        document.body.classList.remove("gn-dir-rtl", "gn-dir-ltr");
        document.body.classList.add(selected === "en" ? "gn-dir-ltr" : "gn-dir-rtl");
    }

    function applyTheme(theme) {
        const selected = validTheme(theme);

        document.documentElement.setAttribute("data-theme", selected);

        if (document.body) {
            document.body.setAttribute("data-admin-theme", selected);
        }

        setStored(STORAGE_THEME, selected);
    }

    function applyDensity(density) {
        const selected = density === "compact" ? "compact" : "comfortable";

        document.documentElement.setAttribute("data-density", selected);

        if (document.body) {
            document.body.setAttribute("data-admin-density", selected);
        }

        setStored(STORAGE_DENSITY, selected);
    }

    function createPanel() {
        if (document.querySelector(".gn-theme-panel")) {
            refreshPanel();
            return;
        }

        const panel = document.createElement("div");
        panel.className = "gn-theme-panel";
        panel.setAttribute("aria-label", "GreenNet theme controls");

        const themeSelect = document.createElement("select");
        themeSelect.className = "gn-theme-select";
        themeSelect.setAttribute("aria-label", "Theme");

        themes.forEach((theme) => {
            const option = document.createElement("option");
            option.value = theme.value;
            option.textContent = theme.label;
            themeSelect.appendChild(option);
        });

        const densityButton = document.createElement("button");
        densityButton.type = "button";
        densityButton.className = "gn-density-toggle";

        const langButton = document.createElement("button");
        langButton.type = "button";
        langButton.className = "gn-lang-toggle";

        panel.appendChild(themeSelect);
        panel.appendChild(densityButton);
        panel.appendChild(langButton);

        document.body.appendChild(panel);

        themeSelect.addEventListener("change", function () {
            applyTheme(themeSelect.value);
            refreshPanel();
        });

        densityButton.addEventListener("click", function () {
            const current = document.documentElement.getAttribute("data-density") || "comfortable";
            const next = current === "compact" ? "comfortable" : "compact";

            applyDensity(next);
            refreshPanel();
        });

        langButton.addEventListener("click", function () {
            const next = currentLang() === "ar" ? "en" : "ar";

            setCookie("greennet_admin_lang", next, 365);
            setStored(STORAGE_LANG, next);
            applyBodyLanguageClasses(next);

            window.location.reload();
        });

        refreshPanel();
    }

    function refreshPanel() {
        const panel = document.querySelector(".gn-theme-panel");

        if (!panel) {
            return;
        }

        const themeSelect = panel.querySelector(".gn-theme-select");
        const densityButton = panel.querySelector(".gn-density-toggle");
        const langButton = panel.querySelector(".gn-lang-toggle");

        const theme = validTheme(getStored(STORAGE_THEME, "greennet-light"));
        const density = getStored(STORAGE_DENSITY, "comfortable") === "compact" ? "compact" : "comfortable";
        const lang = currentLang();

        if (themeSelect) {
            themeSelect.value = theme;
        }

        if (densityButton) {
            densityButton.textContent = density === "compact" ? "Compact" : "Comfortable";
            densityButton.classList.toggle("is-active", density === "compact");
        }

        if (langButton) {
            langButton.textContent = lang === "ar" ? "EN" : "AR";
        }
    }

    function markTechnicalCells() {
        const selectors = [
            ".admin-code",
            "pre",
            "code",
            ".ip-address",
            ".router-command",
            ".mono",
            "[dir='ltr']"
        ];

        selectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((element) => {
                element.setAttribute("dir", "ltr");
            });
        });
    }

    function init() {
        const lang = currentLang();
        const theme = validTheme(getStored(STORAGE_THEME, "greennet-light"));
        const density = getStored(STORAGE_DENSITY, "comfortable") === "compact" ? "compact" : "comfortable";

        applyBodyLanguageClasses(lang);
        applyTheme(theme);
        applyDensity(density);

        createPanel();
        markTechnicalCells();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();