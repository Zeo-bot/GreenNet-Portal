(function () {
    "use strict";

    function closeSidebar() {
        document.body.classList.remove("gn-sidebar-open");
    }

    function openSidebar() {
        document.body.classList.add("gn-sidebar-open");
    }

    function toggleSidebar() {
        document.body.classList.toggle("gn-sidebar-open");
    }

    function setupMobileSidebar() {
        document.querySelectorAll("[data-gn-sidebar-toggle]").forEach((button) => {
            button.addEventListener("click", function () {
                toggleSidebar();
            });
        });

        document.querySelectorAll("[data-gn-sidebar-close]").forEach((element) => {
            element.addEventListener("click", function () {
                closeSidebar();
            });
        });

        document.querySelectorAll(".gn-nav-item").forEach((link) => {
            link.addEventListener("click", function () {
                if (window.innerWidth <= 1100) {
                    closeSidebar();
                }
            });
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeSidebar();
            }
        });
    }

    function markCurrentNav() {
        const currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
        let best = null;
        let bestLength = 0;

        document.querySelectorAll(".gn-nav-item[href]").forEach((link) => {
            const href = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";

            const exact = href === currentPath;
            const nested = href !== "/" && currentPath.startsWith(href + "/");

            if ((exact || nested) && href.length > bestLength) {
                best = link;
                bestLength = href.length;
            }
        });

        if (best) {
            best.classList.add("is-active");
        }
    }

    function init() {
        setupMobileSidebar();
        markCurrentNav();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();