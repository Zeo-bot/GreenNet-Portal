# حزمة بوابة GreenNet Hotspot

حزمة عربية أولًا ومتجاوبة لصفحات MikroTik Hotspot، تعمل محليًا دون CDN أو خطوط أو خدمات خارجية.

## المحتويات

- `login.html`: دخول Hotspot مع HTTP-CHAP وPAP وتجربة مجانية عند تفعيلها.
- `status.html`: حالة الجلسة والاستهلاك والخروج.
- `logout.html`: ملخص الجلسة بعد الخروج.
- `error.html`: الأخطاء القاتلة برسالة عربية.
- `alogin.html` و`redirect.html`: توافق التحويل بعد الدخول.
- `api.json`: إعلان captive portal الحديث.
- `md5.js`: MD5 محلي مطلوب لـ HTTP-CHAP.
- `css/` و`js/` و`img/`: أصول محلية بالكامل.

## رابط بوابة المشترك

المسار الحقيقي داخل GreenNet هو `/login`، ثم يحوّل GreenNet المشترك إلى `/dashboard` بعد المصادقة. لأن اسم مضيف GreenNet يختلف بين البيئات، لا تحتوي الحزمة عنوانًا ثابتًا.

عدّل فقط:

```js
// js/config.js
subscriberPortalUrl: 'https://portal.example.com/login'
```

استخدم HTTPS وعنوانًا يستطيع عميل Hotspot الوصول إليه. أضف اسم المضيف إلى walled garden إذا أردت ظهور الرابط قبل مصادقة Hotspot. إذا بقيت القيمة فارغة، يُخفى الرابط تلقائيًا.

## الأمان والتوافق

- لا تحتوي الحزمة أسرارًا أو كلمة مرور إدارة GreenNet.
- لا تُخزن كلمة مرور Hotspot ولا تُرسل إلى GreenNet.
- عند HTTP-CHAP تُرسل قيمة MD5 الناتجة عن `chap-id + password + chap-challenge`.
- لا تستخدم `localhost` أو عنوان تطوير.
- لا تغيّر الحزمة إعداد Hotspot أو firewall أو المستخدمين.

راجع `INSTALLATION-AR.md` قبل الرفع و`TEST-CHECKLIST-AR.md` بعد التثبيت.
