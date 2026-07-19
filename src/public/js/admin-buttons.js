(function () {
    "use strict";

    function wrapButtonLabel(button) {
        if (
            button.classList.contains("gn-label-wrapped") ||
            button.querySelector(".gn-btn-label") ||
            button.children.length > 0
        ) {
            button.classList.add("gn-label-wrapped");
            return;
        }

        const text = button.textContent.trim();

        if (text === "") {
            return;
        }

        button.textContent = "";

        const span = document.createElement("span");
        span.className = "gn-btn-label";
        span.textContent = text;

        button.appendChild(span);
        button.classList.add("gn-label-wrapped");
    }

    function setupLoadingButtons() {
        document.querySelectorAll("form").forEach((form) => {
            form.addEventListener("submit", function () {
                const submitter = document.activeElement;

                if (
                    submitter &&
                    (
                        submitter.matches("button[type='submit']") ||
                        submitter.matches("input[type='submit']")
                    )
                ) {
                    if (submitter.hasAttribute("data-no-loading")) {
                        return;
                    }

                    if (submitter.tagName.toLowerCase() === "button") {
                        wrapButtonLabel(submitter);
                        submitter.classList.add("is-loading");
                    }

                    submitter.setAttribute("aria-busy", "true");
                }
            });
        });
    }

    function upgradeDangerButtons() {
        const keywords = [
            "delete",
            "حذف",
            "تعطيل",
            "disable",
            "remove",
            "trash"
        ];

        document.querySelectorAll("button, .btn, .admin-mini-btn, .button, input[type='submit']").forEach((button) => {
            const text = (button.textContent || button.value || "").toLowerCase();

            if (button.classList.contains("gn-skip-auto")) {
                return;
            }

            for (const keyword of keywords) {
                if (text.includes(keyword.toLowerCase())) {
                    button.classList.add("gn-btn-danger");
                    return;
                }
            }
        });
    }

    function upgradeMikroTikButtons() {
        const keywords = [
            "execute on mikrotik",
            "تنفيذ على mikrotik",
            "real write",
            "write enabled"
        ];

        document.querySelectorAll("button, .btn, .admin-mini-btn, .button").forEach((button) => {
            const text = (button.textContent || "").toLowerCase();

            if (button.classList.contains("gn-skip-auto")) {
                return;
            }

            for (const keyword of keywords) {
                if (text.includes(keyword.toLowerCase())) {
                    button.classList.add("gn-btn-mikrotik");
                    return;
                }
            }
        });
    }

    function setupRipplePointer() {
        document.querySelectorAll(".gn-btn, .btn, .admin-mini-btn, button, .button").forEach((button) => {
            button.addEventListener("pointermove", function (event) {
                const rect = button.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;

                button.style.setProperty("--gn-pointer-x", x + "px");
                button.style.setProperty("--gn-pointer-y", y + "px");
            });
        });
    }

    function init() {
        document.querySelectorAll("button, .btn, .admin-mini-btn, .button").forEach((button) => {
            wrapButtonLabel(button);
        });

        setupLoadingButtons();
        upgradeDangerButtons();
        upgradeMikroTikButtons();
        setupRipplePointer();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();