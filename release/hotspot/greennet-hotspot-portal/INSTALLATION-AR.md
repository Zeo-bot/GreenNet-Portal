# تثبيت بوابة GreenNet عبر WinBox

## قبل الرفع

1. خذ RouterOS backup وexport وفق الإجراء التشغيلي.
2. من `IP > Hotspot > Server Profiles` سجّل قيمة `HTML Directory` الحالية.
3. من `Files` نزّل مجلد Hotspot الحالي إلى الحاسوب كنسخة تراجع. لم توجد نسخة سابقة داخل مستودع GreenNet.
4. فك ZIP محليًا وتأكد أن `login.html` موجود مباشرة في جذر المجلد، وليس داخل مجلد مزدوج.
5. إذا أردت رابط بوابة المشترك، عدّل `js/config.js` وأضف مضيف GreenNet إلى walled garden بعد مراجعة أمنية.

## الرفع

1. افتح WinBox ثم `Files`.
2. أعد تسمية مجلد Hotspot الحالي إلى اسم احتياطي، مثل `hotspot-before-greennet`، بدل الكتابة فوقه.
3. ارفع مجلد `greennet-hotspot-portal` كاملًا مع مجلداته الفرعية.
4. افتح `IP > Hotspot > Server Profiles`.
5. افتح profile المستخدم، واضبط `HTML Directory` على المسار المرفوع. على أجهزة التخزين الداخلي قد يكون `greennet-hotspot-portal` أو `flash/greennet-hotspot-portal` حسب مكان الرفع.
6. لا تغيّر `Login By`. الحزمة تدعم `http-chap` وتبقي PAP صالحًا عندما يكون مفعّلًا في profile.
7. افتح شبكة Hotspot من هاتف وجهاز كمبيوتر واختبر الدخول والحالة والخروج.

## أوامر قراءة مفيدة

```routeros
/ip/hotspot/profile/print detail
/file/print detail where name~"greennet-hotspot-portal"
/ip/hotspot/active/print detail
/log/print where topics~"hotspot"
```

## التراجع

1. أعد `HTML Directory` إلى القيمة السابقة المسجلة.
2. اختبر صفحة الدخول الأصلية.
3. بعد التأكد فقط، احذف مجلد GreenNet بالاسم الدقيق إن رغبت.
4. لا تحذف مجلد Hotspot الأصلي أو profile أو المستخدمين.

لا تستورد الحزمة كسكربت ولا تنفذ أي تغيير firewall تلقائي؛ هي ملفات HTML ثابتة فقط.
