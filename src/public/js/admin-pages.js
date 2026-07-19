(function () {
    "use strict";

    function pageClassFromPath() {
        const path = window.location.pathname
            .replace(/^\/+/, "")
            .replace(/\/+$/, "")
            .replace(/[^a-zA-Z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");

        return path ? "gn-page-" + path : "gn-page-admin";
    }

    function normalizeText(text) {
        return (text || "").replace(/\s+/g, " ").trim().toLowerCase();
    }

    function isCancelText(text) {
        const value = normalizeText(text);

        return [
            "إلغاء",
            "الغاء",
            "إلغاء البحث",
            "الغاء البحث",
            "مسح",
            "مسح البحث",
            "clear",
            "cancel",
            "reset",
            "back"
        ].some((word) => value === word || value.includes(word.toLowerCase()));
    }

    function isSubmitLike(element) {
        if (!element) {
            return false;
        }

        if (element.matches('button[type="submit"], input[type="submit"]')) {
            return true;
        }

        if (element.matches("button") && !element.getAttribute("type")) {
            return true;
        }

        return false;
    }

    function isActionLike(element) {
        if (!element) {
            return false;
        }

        return Boolean(
            element.matches("button") ||
            element.matches("input[type='submit']") ||
            element.matches("a") ||
            element.classList.contains("gn-btn") ||
            element.classList.contains("btn") ||
            element.classList.contains("admin-mini-btn")
        );
    }

    function ensureActionClasses(element) {
        if (!element) {
            return;
        }

        if (element.matches("a") || element.matches("button")) {
            element.classList.add("gn-btn");
        }

        const text = normalizeText(element.textContent || element.value || "");

        if (isCancelText(text)) {
            element.classList.add("gn-cancel-action");

            if (!element.classList.contains("gn-btn-secondary")) {
                element.classList.add("gn-btn-secondary");
            }

            return;
        }

        if (element.matches("a") && !element.classList.contains("gn-btn-primary") && !element.classList.contains("gn-btn-danger")) {
            element.classList.add("gn-btn-secondary");
        }

        if (isSubmitLike(element) && !element.classList.contains("gn-btn-primary")) {
            element.classList.add("gn-btn-primary");
        }
    }

    function enhanceLegacyPage() {
        const content = document.querySelector(".gn-admin-content");

        if (!content || content.dataset.gnLegacyEnhanced === "1") {
            return;
        }

        const hasModernHeader = Boolean(content.querySelector(".admin-page-header, .gn-page-header, .gn-notif-page, .gn-logs-page, .gn-renew-page, .gn-pay-page, .gn-ws-page, .gn-router-page, .gn-dry-hero, .gn-audit-card"));
        const hasLegacyForm = Boolean(content.querySelector("form"));
        const hasRawHeadings = Boolean(content.querySelector(":scope > h1, :scope > h2"));

        if (!hasModernHeader && (hasLegacyForm || hasRawHeadings)) {
            content.classList.add("gn-legacy-page");

            const firstH1 = content.querySelector(":scope > h1, :scope > h2");

            if (firstH1) {
                const hero = document.createElement("section");
                hero.className = "gn-legacy-hero";

                content.insertBefore(hero, firstH1);
                hero.appendChild(firstH1);

                let next = hero.nextSibling;

                while (next) {
                    const current = next;
                    next = next.nextSibling;

                    if (
                        current.nodeType === Node.ELEMENT_NODE &&
                        (current.matches("form, table, .admin-section-card, .card, .gn-table-card") || current.matches("h1, h2"))
                    ) {
                        break;
                    }

                    if (current.nodeType === Node.TEXT_NODE && current.textContent.trim() === "") {
                        current.remove();
                        continue;
                    }

                    if (
                        current.nodeType === Node.ELEMENT_NODE &&
                        !current.matches("script, style")
                    ) {
                        hero.appendChild(current);
                    }
                }
            }
        }

        content.dataset.gnLegacyEnhanced = "1";
    }

    function wrapLegacyForms() {
        document.querySelectorAll(".gn-admin-content form").forEach((form) => {
            if (form.dataset.gnFormWrapped === "1") {
                return;
            }

            if (form.closest(".gn-legacy-form-card, .gn-table-card, .gn-audit-card, .gn-notif-page, .gn-logs-page, .gn-renew-page, .gn-pay-page, .gn-ws-page, .gn-router-page, .gn-dry-hero")) {
                form.dataset.gnFormWrapped = "1";
                return;
            }

            const wrapper = document.createElement("section");
            wrapper.className = "gn-legacy-form-card";

            const title = document.createElement("h2");
            title.className = "gn-legacy-card-title";
            title.textContent = document.documentElement.getAttribute("dir") === "ltr" ? "Form" : "بيانات النموذج";

            form.parentNode.insertBefore(wrapper, form);
            wrapper.appendChild(title);
            wrapper.appendChild(form);

            form.dataset.gnFormWrapped = "1";
        });
    }

    function groupSimpleFormFields() {
        document.querySelectorAll(".gn-admin-content form").forEach((form) => {
            if (form.dataset.gnFieldsGrouped === "1") {
                return;
            }

            const children = Array.from(form.children);
            const fieldNodes = children.filter((child) => {
                return child.matches && child.matches("label, input, select, textarea");
            });

            if (fieldNodes.length >= 4) {
                const grid = document.createElement("div");
                grid.className = "gn-legacy-form-grid";

                let i = 0;

                while (i < children.length) {
                    const child = children[i];

                    if (child.matches && child.matches("label")) {
                        const field = document.createElement("div");
                        field.className = "form-group";

                        const label = child;
                        const input = children[i + 1];

                        form.insertBefore(grid, label);
                        field.appendChild(label);

                        if (input && input.matches && input.matches("input, select, textarea")) {
                            field.appendChild(input);
                            i += 2;
                        } else {
                            i += 1;
                        }

                        grid.appendChild(field);
                        continue;
                    }

                    i += 1;
                }
            }

            form.dataset.gnFieldsGrouped = "1";
        });
    }

    function enhanceForms() {
        document.querySelectorAll(".gn-admin-content form").forEach((form) => {
            if (form.dataset.gnFormEnhanced === "1") {
                return;
            }

            if (form.classList.contains("gn-audit-filters")) {
                form.dataset.gnFormEnhanced = "1";
                return;
            }

            if (form.closest(".gn-table-card")) {
                form.dataset.gnFormEnhanced = "1";
                return;
            }

            form.classList.add("gn-admin-form");

            const directChildren = Array.from(form.children);
            const actionChildren = directChildren.filter((child) => {
                if (!child.querySelectorAll) {
                    return false;
                }

                const actions = child.querySelectorAll("button, input[type='submit'], a.gn-btn, a.btn, a.admin-mini-btn, a");
                const meaningfulActions = Array.from(actions).filter((item) => {
                    if (!isActionLike(item)) {
                        return false;
                    }

                    const href = item.getAttribute("href") || "";

                    if (href.startsWith("#") && !isCancelText(item.textContent || "")) {
                        return false;
                    }

                    return true;
                });

                return meaningfulActions.length > 0;
            });

            actionChildren.forEach((child) => {
                child.classList.add("gn-form-action-row");

                child.querySelectorAll("button, input[type='submit'], a").forEach((action) => {
                    ensureActionClasses(action);
                });
            });

            form.querySelectorAll("button, input[type='submit'], a").forEach((action) => {
                ensureActionClasses(action);
            });

            if (form.querySelector("input[type='search']")) {
                form.classList.add("gn-search-form");
            }

            form.dataset.gnFormEnhanced = "1";
        });
    }

    function enhanceStandaloneCancelLinks() {
        document.querySelectorAll(".gn-admin-content a").forEach((link) => {
            if (link.dataset.gnCancelEnhanced === "1") {
                return;
            }

            const text = link.textContent || "";

            if (isCancelText(text)) {
                link.classList.add("gn-btn", "gn-btn-secondary", "gn-cancel-action");

                const parent = link.parentElement;

                if (parent && !parent.classList.contains("gn-form-action-row") && !parent.classList.contains("admin-header-actions")) {
                    const siblings = Array.from(parent.children).filter((child) => isActionLike(child));

                    if (siblings.length >= 1) {
                        parent.classList.add("gn-form-action-row");
                        siblings.forEach((item) => ensureActionClasses(item));
                    }
                }
            }

            link.dataset.gnCancelEnhanced = "1";
        });
    }

    function enhanceFilterBars() {
        document.querySelectorAll(".admin-filter-bar").forEach((bar) => {
            bar.classList.add("gn-filter-panel");
        });
    }

    function enhanceBadges() {
        const map = [
            { words: ["active", "enabled", "paid", "success", "approved", "مفعل", "نشط", "مدفوع", "موافق"], cls: "gn-badge-success" },
            { words: ["pending", "waiting", "open", "انتظار", "معلق"], cls: "gn-badge-warning" },
            { words: ["disabled", "expired", "failed", "denied", "unpaid", "معطل", "منتهي", "مرفوض", "فشل"], cls: "gn-badge-danger" },
            { words: ["dry", "info", "preview", "محاكاة"], cls: "gn-badge-info" }
        ];

        document.querySelectorAll(".status, .status-badge, .badge").forEach((el) => {
            if (el.dataset.gnBadgeEnhanced === "1") {
                return;
            }

            const text = normalizeText(el.textContent);
            el.classList.add("gn-badge");

            map.forEach((item) => {
                if (item.words.some((word) => text.includes(word.toLowerCase()))) {
                    el.classList.add(item.cls);
                }
            });

            el.dataset.gnBadgeEnhanced = "1";
        });
    }

    function enhanceTechnicalText() {
        document.querySelectorAll("pre, code, .mono, .admin-code").forEach((el) => {
            el.setAttribute("dir", "ltr");
        });
    }

    function enhanceExternalLinks() {
        document.querySelectorAll('a[target="_blank"]').forEach((link) => {
            if (link.dataset.gnExternal === "1") {
                return;
            }

            link.dataset.gnExternal = "1";

            if (!link.textContent.includes("↗")) {
                link.insertAdjacentText("beforeend", " ↗");
            }
        });
    }

    function init() {
        document.body.classList.add(pageClassFromPath());

        enhanceLegacyPage();
        wrapLegacyForms();
        groupSimpleFormFields();

        enhanceFilterBars();
        enhanceForms();
        enhanceStandaloneCancelLinks();
        enhanceBadges();
        enhanceTechnicalText();
        enhanceExternalLinks();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();