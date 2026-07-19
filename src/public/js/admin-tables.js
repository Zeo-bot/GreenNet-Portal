(function () {
    "use strict";

    const LONG_TEXT_LIMIT = 260;
    const CONTEXT_LIMIT = 90;

    function currentDir() {
        return document.documentElement.getAttribute("dir") || "rtl";
    }

    function normalizeText(text) {
        return (text || "").replace(/\s+/g, " ").trim();
    }

    function lower(text) {
        return normalizeText(text).toLowerCase();
    }

    function isInsideAdminContent(element) {
        return Boolean(element.closest(".gn-admin-content, main, .admin-main, .main-content"));
    }

    function hasInteractiveContent(cell) {
        return Boolean(cell.querySelector("a, button, form, input, select, textarea, .btn, .admin-mini-btn, .gn-btn, details"));
    }

    function looksTechnical(text) {
        const value = text.trim();

        if (value === "") return false;
        if (value.startsWith("{") || value.startsWith("[") || value.includes('":')) return true;
        if (value.includes("/ip/") || value.includes("/ppp/") || value.includes("RouterOS") || value.includes("MikroTik")) return true;
        if (/^\*?[A-Fa-f0-9]{6,}$/.test(value)) return true;
        if (/^\d{1,3}(\.\d{1,3}){3}/.test(value)) return true;
        if (/^\d{4}-\d{2}-\d{2}/.test(value)) return true;

        return false;
    }

    function prettyText(text) {
        const raw = text.trim();

        if (raw === "") return "";

        try {
            return JSON.stringify(JSON.parse(raw), null, 2);
        } catch (e) {
            return raw;
        }
    }

    function ensureTableWrapper(table) {
        const existing = table.closest(".admin-table-responsive, .table-responsive, .gn-table-scroll");

        if (existing) {
            existing.classList.add("gn-table-scroll");
            return existing;
        }

        const wrapper = document.createElement("div");
        wrapper.className = "gn-table-scroll";

        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);

        return wrapper;
    }

    function detectTableTitle(table) {
        const explicit = table.getAttribute("data-gn-title");

        if (explicit) {
            return explicit;
        }

        const localTitle = table.closest(".admin-section-card, .card, .gn-section-card")?.querySelector("h1, h2, h3");

        if (localTitle && normalizeText(localTitle.textContent) !== "") {
            return normalizeText(localTitle.textContent);
        }

        const pageTitle = document.querySelector(".admin-page-title, .gn-topbar-title");

        if (pageTitle && normalizeText(pageTitle.textContent) !== "") {
            return normalizeText(pageTitle.textContent);
        }

        return currentDir() === "ltr" ? "Data Table" : "جدول البيانات";
    }

    function ensureTableCard(wrapper, table) {
        if (wrapper.closest(".gn-table-card")) {
            return wrapper.closest(".gn-table-card");
        }

        if (table.hasAttribute("data-gn-no-card")) {
            return wrapper;
        }

        const card = document.createElement("section");
        card.className = "gn-table-card";

        wrapper.parentNode.insertBefore(card, wrapper);
        card.appendChild(wrapper);

        const toolbar = document.createElement("div");
        toolbar.className = "gn-table-toolbar";

        const title = document.createElement("div");
        title.className = "gn-table-title";

        const icon = document.createElement("span");
        icon.className = "gn-table-title-icon";
        icon.textContent = "☷";

        const titleText = document.createElement("span");
        titleText.className = "gn-table-title-text";
        titleText.textContent = detectTableTitle(table);

        const count = document.createElement("span");
        count.className = "gn-table-count";
        count.textContent = String(table.querySelectorAll("tbody tr").length);

        title.appendChild(icon);
        title.appendChild(titleText);
        title.appendChild(count);

        const tools = document.createElement("div");
        tools.className = "gn-table-tools";

        const search = document.createElement("input");
        search.type = "search";
        search.className = "gn-table-search";
        search.placeholder = currentDir() === "ltr" ? "Search table..." : "بحث داخل الجدول...";
        search.setAttribute("aria-label", "Table search");

        const compactButton = document.createElement("button");
        compactButton.type = "button";
        compactButton.className = "gn-btn gn-btn-secondary gn-btn-sm gn-skip-auto";
        compactButton.textContent = "Compact";

        tools.appendChild(search);
        tools.appendChild(compactButton);

        toolbar.appendChild(title);
        toolbar.appendChild(tools);

        card.insertBefore(toolbar, wrapper);

        setupTableSearch(table, search, count);
        setupCompactToggle(card, compactButton);

        return card;
    }

    function setupTableSearch(table, input, countElement) {
        input.addEventListener("input", function () {
            const q = lower(input.value);
            let visible = 0;

            table.querySelectorAll("tbody tr").forEach((row) => {
                const text = lower(row.textContent);
                const matched = q === "" || text.includes(q);

                row.classList.toggle("is-hidden-by-search", !matched);

                if (matched) visible += 1;
            });

            if (countElement) countElement.textContent = String(visible);
        });
    }

    function setupCompactToggle(card, button) {
        button.addEventListener("click", function () {
            card.classList.toggle("gn-table-compact");
            button.textContent = card.classList.contains("gn-table-compact") ? "Comfort" : "Compact";
        });
    }

    function headerClass(headerText) {
        const h = lower(headerText);

        if (h === "id" || h.includes("#") || h.includes("معرف")) return "gn-table-col-id";

        if (h.includes("subscriber") || h.includes("customer") || h.includes("username") || h.includes("المشترك") || h.includes("الزبون") || h.includes("المستخدم")) {
            return "gn-table-col-subscriber";
        }

        if (h.includes("name") || h.includes("الاسم")) return "gn-table-col-name";
        if (h.includes("phone") || h.includes("mobile") || h.includes("الهاتف") || h.includes("رقم")) return "gn-table-col-phone";
        if (h.includes("access") || h.includes("type") || h.includes("الوصول") || h.includes("النوع")) return "gn-table-col-access";
        if (h.includes("package") || h.includes("plan") || h.includes("profile") || h.includes("الباقة") || h.includes("الخطة") || h.includes("البروفايل")) return "gn-table-col-package";
        if (h.includes("payment") || h.includes("paid") || h.includes("status") || h.includes("الدفع") || h.includes("الحالة")) return "gn-table-col-payment";
        if (h.includes("created") || h.includes("updated") || h.includes("date") || h.includes("time") || h.includes("آخر") || h.includes("التاريخ") || h.includes("الوقت")) return "gn-table-col-date";
        if (h.includes("message") || h.includes("الرسالة")) return "gn-table-col-message";
        if (h.includes("context") || h.includes("payload") || h.includes("params") || h.includes("data") || h.includes("السياق")) return "gn-table-col-context";
        if (h.includes("action") || h.includes("actions") || h.includes("إجراء") || h.includes("إجراءات") || h.includes("التحكم")) return "gn-table-col-actions";

        return "";
    }

    function classifyColumns(table) {
        const headers = Array.from(table.querySelectorAll("thead th")).map((th) => normalizeText(th.textContent));

        headers.forEach((header, index) => {
            const className = headerClass(header);

            if (className === "") return;

            const colIndex = index + 1;

            table.querySelectorAll("thead th:nth-child(" + colIndex + "), tbody td:nth-child(" + colIndex + ")").forEach((cell) => {
                cell.classList.add(className);

                if (className === "gn-table-col-date" || className === "gn-table-col-phone") {
                    cell.setAttribute("dir", "ltr");
                }
            });
        });
    }

    function badgeClassForText(text) {
        const value = lower(text);

        if (value.includes("paid") || value.includes("active") || value.includes("enabled") || value.includes("success") || value.includes("مدفوع") || value.includes("فعال") || value.includes("نشط")) {
            return "is-success";
        }

        if (value.includes("pending") || value.includes("waiting") || value.includes("soon") || value.includes("معلق") || value.includes("انتظار") || value.includes("قارب")) {
            return "is-warning";
        }

        if (value.includes("unpaid") || value.includes("not paid") || value.includes("disabled") || value.includes("expired") || value.includes("failed") || value.includes("غير مدفوع") || value.includes("معطل") || value.includes("منتهي") || value.includes("فشل")) {
            return "is-danger";
        }

        if (value.includes("hybrid") || value.includes("hotspot") || value.includes("ppp") || value.includes("api") || value.includes("info")) {
            return "is-info";
        }

        return "is-muted";
    }

    function enhanceBadgeCell(cell) {
        if (cell.dataset.gnBadgeCell === "1") return;
        if (hasInteractiveContent(cell)) return;

        const text = normalizeText(cell.textContent);
        if (text === "") return;

        const badge = document.createElement("span");
        badge.className = "gn-table-badge " + badgeClassForText(text);
        badge.textContent = text;

        cell.innerHTML = "";
        cell.appendChild(badge);
        cell.dataset.gnBadgeCell = "1";
    }

    function enhanceActionCell(cell) {
        if (cell.dataset.gnActionsEnhanced === "1") return;

        const actions = Array.from(cell.querySelectorAll("a, button, input[type='submit']"));

        if (actions.length === 0) {
            cell.dataset.gnActionsEnhanced = "1";
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.className = "gn-table-actions-wrap";

        actions.forEach((action) => {
            if (action.closest(".gn-table-actions-wrap")) return;

            action.classList.add("gn-btn", "gn-btn-secondary", "gn-btn-sm");
            wrapper.appendChild(action);
        });

        cell.innerHTML = "";
        cell.appendChild(wrapper);
        cell.dataset.gnActionsEnhanced = "1";
    }

    function enhanceContextCell(cell) {
        if (cell.dataset.gnContextEnhanced === "1") return;

        const raw = cell.textContent.trim();

        if (raw === "") {
            cell.dataset.gnContextEnhanced = "1";
            return;
        }

        const pretty = prettyText(raw);
        const preview = normalizeText(raw);

        cell.innerHTML = "";
        cell.classList.add("gn-technical-cell");

        if (preview.length <= CONTEXT_LIMIT) {
            const short = document.createElement("span");
            short.className = "gn-cell-code";
            short.textContent = preview;
            cell.appendChild(short);
            cell.dataset.gnContextEnhanced = "1";
            return;
        }

        const details = document.createElement("details");
        details.className = "gn-context-details";

        const summary = document.createElement("summary");
        summary.textContent = currentDir() === "ltr" ? "Show details" : "عرض التفاصيل";

        const pre = document.createElement("pre");
        pre.className = "gn-context-pre";
        pre.textContent = pretty;

        details.appendChild(summary);
        details.appendChild(pre);

        cell.appendChild(details);
        cell.dataset.gnContextEnhanced = "1";
    }

    function enhanceDateCell(cell) {
        if (cell.dataset.gnDateEnhanced === "1") return;

        const text = normalizeText(cell.textContent);
        if (text === "") return;

        cell.innerHTML = "";

        const span = document.createElement("span");
        span.className = "gn-table-date-value";
        span.textContent = text;
        span.setAttribute("dir", "ltr");

        cell.appendChild(span);
        cell.dataset.gnDateEnhanced = "1";
    }

    function enhanceTechnicalCell(cell) {
        const text = normalizeText(cell.textContent);

        if (looksTechnical(text)) {
            cell.classList.add("gn-technical-cell");
            cell.setAttribute("dir", "ltr");
        }
    }

    function enhanceLongCell(cell) {
        if (cell.classList.contains("gn-cell-processed")) return;
        if (cell.classList.contains("gn-table-col-actions")) return;
        if (cell.classList.contains("gn-table-col-context")) return;
        if (cell.classList.contains("gn-table-col-payment")) return;
        if (cell.classList.contains("gn-table-col-access")) return;
        if (cell.classList.contains("gn-table-col-date")) return;
        if (hasInteractiveContent(cell)) return;

        const text = normalizeText(cell.textContent);

        if (text.length < LONG_TEXT_LIMIT) {
            cell.classList.add("gn-cell-processed");
            return;
        }

        const original = cell.innerHTML;

        cell.innerHTML = "";

        const box = document.createElement("div");
        box.className = "gn-cell-long";

        const content = document.createElement("div");
        content.className = "gn-cell-long-content";
        content.innerHTML = original;

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "gn-btn gn-btn-secondary gn-btn-sm gn-cell-toggle gn-skip-auto";
        toggle.textContent = currentDir() === "ltr" ? "Show more" : "عرض المزيد";

        toggle.addEventListener("click", function () {
            box.classList.toggle("is-expanded");
            toggle.textContent = box.classList.contains("is-expanded")
                ? (currentDir() === "ltr" ? "Hide" : "إخفاء")
                : (currentDir() === "ltr" ? "Show more" : "عرض المزيد");
        });

        box.appendChild(content);
        box.appendChild(toggle);
        cell.appendChild(box);
        cell.classList.add("gn-cell-processed");
    }

    function enhanceRows(table) {
        table.querySelectorAll("tbody td").forEach((cell) => {
            if (cell.classList.contains("gn-table-col-actions")) {
                enhanceActionCell(cell);
                return;
            }

            if (cell.classList.contains("gn-table-col-context")) {
                enhanceContextCell(cell);
                return;
            }

            if (cell.classList.contains("gn-table-col-payment") || cell.classList.contains("gn-table-col-access") || cell.classList.contains("gn-table-col-status")) {
                enhanceBadgeCell(cell);
                return;
            }

            if (cell.classList.contains("gn-table-col-date")) {
                enhanceDateCell(cell);
                return;
            }

            enhanceTechnicalCell(cell);
            enhanceLongCell(cell);
        });
    }

    function enhanceTable(table) {
        if (table.dataset.gnTableEnhanced === "1") return;
        if (!isInsideAdminContent(table)) return;

        table.dataset.gnTableEnhanced = "1";
        table.classList.add("gn-data-table", "admin-table");

        const wrapper = ensureTableWrapper(table);
        ensureTableCard(wrapper, table);

        classifyColumns(table);
        enhanceRows(table);
    }

    function init() {
        document.querySelectorAll("table").forEach((table) => {
            enhanceTable(table);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();