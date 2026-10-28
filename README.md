# Cible Real Estate CRM

نظام CRM داخلي لشركة Cible Real Estate لإدارة العملاء والوسطاء والطلبات والمتابعات والصفقات داخل قسمَي **الإيجار** و**إعادة البيع**، كقسمين معزولين تشغيليًا وبيانيًا. الواجهة عربية باتجاه RTL، والهوية البصرية للشركة مطبقة على لوحة الإدارة.

- **التقنية:** Laravel 12، Filament 5، PHP 8.2، SQLite للتطوير والاختبار (MySQL لاحقًا باسم قاعدة `CRM3`)، `propaganistas/laravel-phone` لتوحيد أرقام الهاتف إلى E.164.
- **حالة العمل وما تبقى:** [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md) — القسم 0 هو المرجع النافذ.
- **القرارات الملزمة:** [`docs/DECISIONS.md`](docs/DECISIONS.md).
- **فهرس الوثائق:** [`docs/README.md`](docs/README.md).

## التشغيل على بيئة تطوير

يتطلب PHP 8.2 أو أحدث مع `ext-intl` و`ext-mbstring`، وComposer.

```bash
composer install
copy .env.example .env          # على Linux/macOS: cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

ثم افتح `http://localhost:8000/admin`.

### الحساب الأول

`php artisan migrate:fresh --seed` ينشئ حساب مدير شركة واحدًا من متغيرات `.env`:

```dotenv
CRM_MANAGER_NAME="مدير الشركة"
CRM_MANAGER_EMAIL=manager@cible.test
CRM_MANAGER_PASSWORD=
```

إذا تركت `CRM_MANAGER_PASSWORD` فارغًا، يولّد الـ seeder كلمة مرور مؤقتة ويطبعها مرة واحدة في الطرفية. بعد أول دخول غيّرها. إعادة تشغيل الـ seeder لا تلمس حسابًا موجودًا.

### إضافة الموظفين

شاشة إدارة فريق العمل لم تُنفَّذ بعد (`D-080`)، والحسابات تُنشأ بالأمر:

```bash
# موظف في قسم الإيجار
php artisan users:create employee@example.com --name="اسم الموظف" --department=rental

# موظف يخدم القسمين
php artisan users:create both@example.com --department=rental --department=resale

# مدير شركة إضافي (يملك القسمين دائمًا)
php artisan users:create manager2@example.com --role=manager
```

الموظف يحتاج قسمًا واحدًا على الأقل، والقيمة المقبولة `rental` أو `resale` فقط.

### الاختبارات والتنسيق

```bash
php artisan test          # SQLite in-memory، لا يلمس قاعدة التطوير
vendor/bin/pint           # تنسيق الكود
```

## البنية

```
app/
  Console/Commands/       أوامر الإدارة (users:create)
  Enums/                  الأقسام والأدوار والمراحل والحالات والنتائج والمحافظات
  Filament/               موارد وشاشات وودجات اللوحة، وكل مورد مقيد بقسمه
  Models/                 نماذج لكل قسم (Rental/…, Resale/…) فوق نماذج Base مشتركة
  Policies/               الصلاحيات، وعزل الأقسام في الاستعلامات
  Services/               منطق المجال: العملاء، الوسطاء، المناطق، الطلبات، الأنشطة، الصفقات، التدقيق
  Support/                أدوات مساعدة (الصلاحيات، خيارات الطلب)
database/
  migrations/             الجداول، مع فصل rental_* وresale_* وجدول areas الموحد
  seeders/                المناطق، مصادر الوصول، أول مدير
docs/                     حزمة الوثائق المرجعية والقرارات
public/css/cible-admin.css هوية اللوحة؛ public/fonts خطوط العلامة
```

## مبادئ ملزمة في الكود

- **لا تسريب بين القسمين:** كل مورد واستعلام وخدمة مقيّد بقسمه، والمدير فقط يملك القسمين (`D-020`، `D-063`).
- **المتابعة إلزامية للطلب النشط:** لا طلب نشط بدون تاريخ متابعة قادم (`D-008`، `D-043`).
- **ثلاثة مفاهيم منفصلة:** المرحلة، والحالة، والنتيجة — لا تُدمج في حقل واحد (`D-043`).
- **الإغلاق غير الناجح يحتاج نصًا من الموظف**، والتصنيف المساعد اختياري (`D-050`).
- **لا حذف للعملاء والمناطق:** أرشفة فقط (`D-067`)، وبيانات الطلبات التاريخية تبقى مفهومة.
- **التدقيق للقراءة فقط:** الموظف يرى ما فعله، والمدير يرى القسم (`D-027`، `D-071`).
- **كل كتابة تمر عبر الخدمة:** لا تُكتب الجداول مباشرة من الشاشة، حتى لا يُتجاوز التحقق أو التدقيق.
