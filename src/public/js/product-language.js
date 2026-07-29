(function () {
    "use strict";

    const UI_LANG = "greennet.ui.lang";
    const ADMIN_LANG = "greennet.admin.lang";
    const THEME = "greennet.admin.theme";

    const keyed = {
        subscriber_portal: { ar: "بوابة المشترك", en: "Subscriber Portal" },
        my_account: { ar: "حسابي", en: "My Account" },
        home: { ar: "الرئيسية", en: "Home" },
        package: { ar: "الباقة", en: "Package" },
        renewal: { ar: "التجديد", en: "Renewal" },
        notifications: { ar: "الإشعارات", en: "Notifications" },
        account: { ar: "حسابي", en: "Account" }
    };

    const arToEn = {
        "لوحة الإدارة": "Admin Panel",
        "لوحة المدير": "Admin Panel",
        "لوحة التحكم": "Dashboard",
        "الرئيسية": "Home",
        "المشتركون": "Subscribers",
        "المشتركين": "Subscribers",
        "طلبات التجديد": "Renewal Requests",
        "الدفعات": "Payments",
        "الباقات": "Packages",
        "تجهيز الباقات / Profiles": "Router/Profile Provisioning",
        "الشبكة": "Network",
        "الراوترات": "Routers",
        "الجلسات النشطة": "Active Sessions",
        "الأتمتة": "Automation",
        "النظام": "System",
        "النسخ الاحتياطي والاستعادة": "Backup & Restore",
        "الإعدادات": "Settings",
        "أدوات متقدمة": "Advanced",
        "جاهزية النظام": "System Health",
        "تفاصيل السجل": "Record Details",
        "فحص الجاهزية": "Readiness",
        "أدوات User Manager": "User Manager Tools",
        "عمليات RouterOS الأصلية": "Native RouterOS Operations",
        "إعدادات أمان الكتابة": "Write Safety",
        "السجلات": "Logs",
        "سجل العمليات": "Audit Log",
        "تسجيل الخروج": "Logout",
        "المظهر": "Theme",
        "بوابة المشترك": "Subscriber Portal",
        "حسابي": "My Account",
        "أهلاً بك في GreenNet": "Welcome to GreenNet",
        "الباقة الحالية": "Current Package",
        "الأيام المتبقية": "Days Remaining",
        "استهلاك الباقة": "Package Usage",
        "الاستهلاك الحالي": "Current Usage",
        "التفاصيل": "Details",
        "التنزيل": "Download",
        "الرفع": "Upload",
        "الإجمالي": "Total",
        "المتبقي": "Remaining",
        "تفاصيل الاشتراك": "Subscription Details",
        "السرعة": "Speed",
        "تاريخ الانتهاء": "Expiration Date",
        "الجلسة الحالية": "Current Session",
        "متصل الآن": "Online Now",
        "غير متصل": "Offline",
        "الباقة والاستهلاك": "Package & Usage",
        "التفاصيل الكاملة": "Full Details",
        "طلب تجديد": "Request Renewal",
        "تابع حالة طلبك": "Track Your Request",
        "آخر التنبيهات": "Latest Alerts",
        "الدعم": "Support",
        "تواصل معنا": "Contact Us",
        "باقتك الحالية": "Your Current Package",
        "السعة": "Quota",
        "تفاصيل الباقة": "Package Details",
        "مدة الباقة": "Package Validity",
        "بداية الاشتراك": "Subscription Start",
        "نهاية الاشتراك": "Subscription End",
        "قيمة التجديد": "Renewal Price",
        "آخر تجديد": "Latest Renewal",
        "تاريخ الدفع": "Payment Date",
        "بداية الباقة": "Package Start",
        "نهاية الباقة": "Package End",
        "عرض الاستهلاك": "View Usage",
        "تفاصيل الاستخدام": "Usage Details",
        "المستخدم من الباقة": "Used Quota",
        "إجمالي الاستخدام": "Total Usage",
        "معلومات الباقة": "Package Information",
        "تاريخ البداية": "Start Date",
        "طلب تجديد الاشتراك": "Request Subscription Renewal",
        "تجديد الاشتراك": "Renew Subscription",
        "طلب قيد المراجعة": "Request Under Review",
        "طلبات التجديد": "Renewal Requests",
        "إرسال طلب تجديد": "Submit Renewal Request",
        "رقم الهاتف": "Phone Number",
        "ملاحظات إضافية": "Additional Notes",
        "إرسال الطلب": "Submit Request",
        "حالة طلبات التجديد": "Renewal Request Status",
        "لا توجد طلبات تجديد حتى الآن.": "No renewal requests yet.",
        "استلمنا طلب التجديد الخاص بك وهو قيد المراجعة. لا تحتاج لإرسال طلب آخر الآن.": "We received your renewal request. It is under review; you do not need to submit another request.",
        "سنراجع الطلب ونتواصل معك عند الحاجة. إرسال الطلب لا يغيّر الباقة مباشرة.": "We will review your request and contact you if needed. Submitting it does not change the package immediately.",
        "أرغب في تجديد اشتراكي على نفس الباقة.": "I would like to renew my current package.",
        "ملاحظة اختيارية": "Optional Note",
        "اكتب أي ملاحظة تساعدنا في معالجة الطلب": "Add any note that may help us process your request",
        "التواصل عبر واتساب": "Contact via WhatsApp",
        "مركز الإشعارات": "Notification Center",
        "لا توجد إشعارات حالياً": "No notifications",
        "ستظهر هنا تنبيهات الاشتراك والصيانة عند توفرها.": "Subscription and maintenance notices will appear here.",
        "جديد": "New",
        "مقروء": "Read",
        "حساب المشترك": "Subscriber Account",
        "بيانات الحساب": "Account Details",
        "اسم المستخدم": "Username",
        "حالة الجلسة": "Session Status",
        "المساعدة والحساب": "Help & Account",
        "الدعم والمساعدة": "Support & Help",
        "لتغيير كلمة المرور أو تحديث بيانات الحساب، تواصل مع الدعم.": "Contact support to change your password or update account details.",
        "كيف يمكننا مساعدتك؟": "How can we help?",
        "واتساب": "WhatsApp",
        "فتح محادثة مع الدعم": "Open a support chat",
        "اتصال هاتفي": "Phone Call",
        "معلومات تساعد فريق الدعم": "Information for Support",
        "معلومات التواصل غير متاحة حالياً. حاول مرة أخرى لاحقاً.": "Support contact information is not configured. Please try again later.",
        "أنت تشاهد صفحة المشترك في وضع المعاينة.": "You are previewing the subscriber page.",
        "بيانات الاستخدام غير متاحة حالياً.": "Usage data is currently unavailable.",
        "بيانات الاستهلاك غير متاحة حالياً، حاول مرة أخرى لاحقاً.": "Usage data is currently unavailable. Please try again later.",
        "لا توجد باقة مرتبطة بحسابك حالياً. تواصل مع الدعم لتحديث بيانات الاشتراك.": "No package is linked to your account. Contact support to update the subscription.",
        "لا يوجد تجديد مسجل حتى الآن.": "No renewal has been recorded yet.",
        "لا توجد باقة حالية": "No Current Package",
        "آخر الإعلانات": "Latest Announcements",
        "إعلانات الشبكة": "Network Announcements",
        "عدد الإعلانات": "Announcements",
        "لا توجد إعلانات حالياً.": "No announcements.",
        "حالة الاشتراك": "Subscription Status",
        "غير متاح": "Unavailable",
        "نشط": "Active",
        "منتهي": "Expired",
        "موقوف": "Suspended",
        "قيد الانتظار": "Pending",
        "ناجح": "Successful",
        "فشل": "Failed",
        "متصل": "Online",
        "غير متصل": "Offline",
        "إضافة": "Add",
        "حفظ": "Save",
        "تحديث": "Update",
        "بحث": "Search",
        "إلغاء": "Cancel",
        "حذف": "Delete",
        "استعادة": "Restore",
        "إنشاء نسخة احتياطية": "Create Backup",
        "لا توجد بيانات": "No data available",
        "لا توجد نتائج": "No results",
        "إدارة المشترك": "Manage Customer",
        "معاينة تطبيق المشترك": "Preview Subscriber App"
        ,"إجمالي المشتركين": "Total Subscribers"
        ,"اشتراك ساري": "Active Subscriptions"
        ,"اشتراكات قريبة الانتهاء": "Expiring Subscriptions"
        ,"طلبات التجديد": "Renewal Requests"
        ,"الجلسات النشطة": "Active Sessions"
        ,"حالة الموجّهات": "Router Status"
        ,"راوترات تحتاج تحديثاً": "Routers Need Attention"
        ,"تحتاج متابعة": "Needs Attention"
        ,"خلال سبعة أيام": "Within Seven Days"
        ,"عرض الكل": "View All"
        ,"إدارة الطلبات": "Manage Requests"
        ,"قائمة المشتركين": "Subscriber List"
        ,"جدول المشتركين": "Customer Table"
        ,"إضافة مشترك": "Add Subscriber"
        ,"إضافة مشترك جديد": "Add New Subscriber"
        ,"بحث سريع عن مشترك": "Quick Subscriber Search"
        ,"بحث شامل": "Global Search"
        ,"الكل": "All"
        ,"المشترك": "Subscriber"
        ,"المشتركون": "Subscribers"
        ,"الحالة": "Status"
        ,"الراوتر": "Router"
        ,"انتهاء الاشتراك": "Subscription Expiry"
        ,"حالة الاتصال": "Connection Status"
        ,"حالة المزامنة": "Sync Status"
        ,"الإجراءات": "Actions"
        ,"العمليات الأساسية": "Primary Actions"
        ,"تجديد الاشتراك": "Renew Subscription"
        ,"تغيير الباقة": "Change Package"
        ,"مزامنة": "Synchronize"
        ,"الحساب والراوتر": "Account & Router"
        ,"تفعيل الحساب": "Enable Account"
        ,"إيقاف الحساب": "Suspend Account"
        ,"فصل الجلسات": "Disconnect Sessions"
        ,"إجراءات متقدمة": "Advanced Actions"
        ,"حذف الحساب من الراوتر": "Delete Router Account"
        ,"حذف المشترك محلياً": "Delete Local Subscriber"
        ,"إدارة الراوترات": "Router Management"
        ,"إدارة الموجّهات": "Router Management"
        ,"الراوترات المسجلة": "Registered Routers"
        ,"إضافة راوتر": "Add Router"
        ,"إعداد موجّه جديد": "Set Up Router"
        ,"فحص الاتصال الآن": "Check Connection"
        ,"فحص الجلسات الآن": "Check Sessions"
        ,"الافتراضي": "Default"
        ,"المفعّل": "Enabled"
        ,"آخر تحديث": "Last Updated"
        ,"التجهيز": "Provisioning"
        ,"الاسم": "Name"
        ,"المدة": "Duration"
        ,"الحصة": "Quota"
        ,"السعر": "Price"
        ,"الأتمتة": "Automation"
        ,"آخر تشغيل": "Last Run"
        ,"المهمة": "Task"
        ,"تشغيل الآن": "Run Now"
        ,"بانتظار إعادة المحاولة": "Retry Pending"
        ,"الاحتياطي والاستعادة": "Backup & Restore"
        ,"إنشاء نسخة": "Create Backup"
        ,"النسخ المتاحة": "Available Backups"
        ,"رفع واستعادة": "Upload & Restore"
        ,"تحميل": "Download"
        ,"رفع فقط": "Upload Only"
        ,"رفع والتحقق دون استعادة": "Upload and Validate"
        ,"فتح": "Open"
        ,"فعال": "Active"
        ,"قريب الانتهاء": "Expiring"
        ,"غير مدفوع": "Unpaid"
        ,"غير مدفوع / مستحق": "Unpaid / Due"
        ,"قيد المراجعة": "Under Review"
        ,"تمت الموافقة": "Approved"
        ,"لم تتم الموافقة": "Not Approved"
    };

    function getCookie(name) {
        const prefix = name + "=";
        const part = document.cookie.split(";").map((item) => item.trim()).find((item) => item.indexOf(prefix) === 0);
        return part ? decodeURIComponent(part.slice(prefix.length)) : "";
    }

    function setCookie(name, value) {
        document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=31536000; SameSite=Lax";
    }

    function stored(key, fallback) {
        try { return localStorage.getItem(key) || fallback; } catch (e) { return fallback; }
    }

    function store(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* no-op */ }
    }

    function language() {
        const admin = document.body.classList.contains("gn-admin-body") || document.body.classList.contains("gn-admin-login-body");
        const cookie = getCookie(admin ? "greennet_admin_lang" : "greennet_ui_lang");
        if (cookie === "ar" || cookie === "en") return cookie;
        const saved = stored(admin ? ADMIN_LANG : UI_LANG, "");
        return saved === "en" ? "en" : "ar";
    }

    const enToAr = Object.keys(arToEn).reduce((result, arabic) => {
        if (!result[arToEn[arabic]]) result[arToEn[arabic]] = arabic;
        return result;
    }, {
        "Router Onboarding": "إعداد الموجّه",
        "Admin Panel": "لوحة الإدارة",
        "System Health": "جاهزية النظام",
        "Backup": "النسخ الاحتياطي",
        "Settings": "الإعدادات"
    });

    function translateText(text, lang) {
        const trimmed = text.trim();
        const dictionary = lang === "en" ? arToEn : enToAr;
        if (!trimmed || !dictionary[trimmed]) return text;
        return text.replace(trimmed, dictionary[trimmed]);
    }

    function translateTree(lang) {
        document.documentElement.lang = lang;
        document.documentElement.dir = lang === "en" ? "ltr" : "rtl";

        document.querySelectorAll("[data-i18n]").forEach((node) => {
            const entry = keyed[node.getAttribute("data-i18n")];
            if (entry) node.textContent = entry[lang];
        });

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach((node) => {
            if (node.parentElement && !node.parentElement.closest("script,style,code,pre,[data-no-translate]")) {
                node.nodeValue = translateText(node.nodeValue || "", lang);
            }
        });

        document.querySelectorAll("[placeholder],[title],[aria-label]").forEach((node) => {
            ["placeholder", "title", "aria-label"].forEach((attribute) => {
                if (node.hasAttribute(attribute)) node.setAttribute(attribute, translateText(node.getAttribute(attribute) || "", lang));
            });
        });
    }

    function toggleLanguage() {
        const next = language() === "ar" ? "en" : "ar";
        const admin = document.body.classList.contains("gn-admin-body") || document.body.classList.contains("gn-admin-login-body");
        setCookie(admin ? "greennet_admin_lang" : "greennet_ui_lang", next);
        store(admin ? ADMIN_LANG : UI_LANG, next);
        window.location.reload();
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute("data-theme") || stored(THEME, "greennet-light");
        const next = current === "greennet-dark" ? "greennet-light" : "greennet-dark";
        document.documentElement.setAttribute("data-theme", next);
        store(THEME, next);
    }

    function init() {
        const lang = language();
        const savedTheme = stored(THEME, "greennet-light");
        if (savedTheme !== "greennet-dark" && savedTheme !== "greennet-light") {
            store(THEME, "greennet-light");
            document.documentElement.setAttribute("data-theme", "greennet-light");
        }
        translateTree(lang);
        document.querySelectorAll("[data-gn-language-toggle]").forEach((button) => {
            button.textContent = lang === "ar" ? "English" : "العربية";
            button.addEventListener("click", toggleLanguage);
        });
        document.querySelectorAll("[data-gn-theme-toggle]").forEach((button) => button.addEventListener("click", toggleTheme));
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
})();
