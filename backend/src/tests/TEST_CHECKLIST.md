# ✅ قائمة اختبار نظام رصد الدرجات

## 📋 تعليمات الاختبار

### 1. إعداد البيئة
```bash
# 1. شغل MongoDB
mongod

# 2. شغل Backend
 cd backend
npm install
npm run dev

# 3. شغل Frontend
 cd ../frontend
npm install
npm run dev
```

### 2. إضافة بيانات اختبار
```bash
# استخدم Postman أو curl

# 1. إنشاء مستخدمين (Admin, Head, Teacher)
POST http://localhost:5000/api/auth/register
Content-Type: application/json

# Admin
{
  "name": "مدير النظام",
  "civilId": "999999",
  "password": "123456",
  "role": "admin"
}

# Head
{
  "name": "رئيس قسم الرياضيات",
  "civilId": "789012",
  "password": "123456",
  "role": "head",
  "subject": "[SUBJECT_ID]"
}

# Teacher
{
  "name": "معلم الرياضيات",
  "civilId": "123456",
  "password": "123456",
  "role": "teacher",
  "subject": "[SUBJECT_ID]"
}
```

### 3. اختبار APIs

#### ✅ اختبار المعلم (Teacher)
```bash
# 1. تسجيل الدخول
POST /api/auth/login
{ "civilId": "123456", "password": "123456" }

# 2. عرض الفصول
GET /api/grades/teacher/classes

# 3. عرض الدرجات
GET /api/grades/class/[CLASS_ID]/subject/[SUBJECT_ID]?period=first

# 4. حفظ الدرجات
POST /api/grades/save
{
  "classId": "[CLASS_ID]",
  "subjectId": "[SUBJECT_ID]",
  "period": "first",
  "grades": {
    "[STUDENT_ID_1]": { "work": 35, "exam": 55 },
    "[STUDENT_ID_2]": { "work": 38, "exam": 58 }
  }
}

# 5. اعتماد الأعمال
POST /api/grades/submit
{
  "classId": "[CLASS_ID]",
  "subjectId": "[SUBJECT_ID]",
  "period": "first",
  "type": "work",
  "grades": {
    "[STUDENT_ID_1]": { "work": 35 },
    "[STUDENT_ID_2]": { "work": 38 }
  }
}

# 6. اعتماد الاختبار
POST /api/grades/submit
{
  "classId": "[CLASS_ID]",
  "subjectId": "[SUBJECT_ID]",
  "period": "first",
  "type": "exam",
  "grades": {
    "[STUDENT_ID_1]": { "exam": 55 },
    "[STUDENT_ID_2]": { "exam": 58 }
  }
}
```

#### ✅ اختبار رئيس القسم (Head)
```bash
# 1. تسجيل الدخول
POST /api/auth/login
{ "civilId": "789012", "password": "123456" }

# 2. عرض فصول القسم
GET /api/grades/head/department-classes

# 3. مراجعة درجات معلم
GET /api/grades/head/review/[CLASS_ID]/[TEACHER_ID]/[SUBJECT_ID]?period=first

# 4. تعديل درجة
POST /api/grades/head/edit
{
  "classId": "[CLASS_ID]",
  "teacherId": "[TEACHER_ID]",
  "subjectId": "[SUBJECT_ID]",
  "studentId": "[STUDENT_ID]",
  "type": "work",
  "value": 37
}

# 5. اعتماد القسم
POST /api/grades/head/approve-department
{
  "type": "work",
  "period": "first"
}
```

#### ✅ اختبار الكنترول (Control)
```bash
# 1. تسجيل الدخول (Admin)
POST /api/auth/login
{ "civilId": "999999", "password": "123456" }

# 2. عرض جميع الدرجات
GET /api/grades/control/all?period=first

# 3. عرض بيانات الطباعة
GET /api/grades/control/print?classId=[CLASS_ID]&subjectId=[SUBJECT_ID]&period=first
```

#### ✅ اختبار المدير (Admin)
```bash
# 1. فك الاعتماد
POST /api/grades/admin/unlock
{
  "classId": "[CLASS_ID]",
  "subjectId": "[SUBJECT_ID]",
  "period": "first",
  "type": "work",
  "level": "teacher"
}
```

### 4. اختبار الواجهة (Frontend)

#### ✅ اختبار المعلم
- [ ] تسجيل الدخول كمعلم
- [ ] عرض الفصول (أزرار بألوان المراحل)
- [ ] اختيار الفصل والمادة
- [ ] عرض جدول الطلاب
- [ ] إدخال درجات الأعمال (40)
- [ ] إدخال درجات الاختبار (60)
- [ ] التحقق من القيم (ما ينفعش أكتر من 40/60)
- [ ] التحقق من الكسر (نص درجة بس)
- [ ] حفظ الدرجات (مسودة)
- [ ] اعتماد الأعمال (بعد رصد كل الطلاب)
- [ ] اعتماد الاختبار (بعد رصد كل الطلاب)
- [ ] عدم القدرة على التعديل بعد الاعتماد

#### ✅ اختبار رئيس القسم
- [ ] تسجيل الدخول كرئيس قسم
- [ ] عرض تاب "مراجعة فصول القسم"
- [ ] عرض فصول المدرسة بمعلمي قسمه فقط
- [ ] رؤية حالة الاعتماد لكل فصل (✅/⚠️)
- [ ] عاين الدرجات (من بره)
- [ ] تعديل الدرجات (قبل اعتماد المعلم)
- [ ] حفظ التعديلات
- [ ] عرض تاب "فصولي (كمعلم)"
- [ ] رصد درجات فصوله الخاصة
- [ ] اعتماد القسم (ما يظهرش إلا بعد اعتماد جميع المعلمين)
- [ ] إشعار للمدير بعد اعتماد القسم

#### ✅ اختبار الكنترول
- [ ] تسجيل الدخول ككنترول/مدير
- [ ] تصفية: مرحلة + مادة + فترة + نوع
- [ ] عرض إحصائيات (إجمالي + معتمد + قيد الانتظار)
- [ ] عرض جدول الكشوف المعتمدة
- [ ] مراجعة الكشف (عرض التفاصيل)
- [ ] طباعة الكشف (PDF رسمي)
- [ ] التحقق من شعار الوزارة والمدرسة
- [ ] التحقق من بيانات الاعتماد (المعلم + رئيس القسم + المدير)

#### ✅ اختبار المدير
- [ ] فك اعتماد المعلم
- [ ] فك اعتماد رئيس القسم
- [ ] تحكم الفترات النشطة
- [ ] تحكم الأعمدة المرصودة (أعمال/اختبار)

### 5. اختبار الحالات الخاصة
- [ ] طالب معذور (خانة مقفولة)
- [ ] درجة أكتر من القصوى (خطأ)
- [ ] كسر غير نص درجة (خطأ)
- [ ] اعتماد قبل رصد كل الطلاب (خطأ)
- [ ] تعديل بعد الاعتماد (خطأ)
- [ ] اعتماد القسم قبل اعتماد جميع المعلمين (خطأ)

### 6. اختبار الأداء
- [ ] تحميل الفصول (< 2 ثانية)
- [ ] تحميل الدرجات (< 2 ثانية)
- [ ] حفظ الدرجات (< 1 ثانية)
- [ ] اعتماد الدرجات (< 1 ثانية)
- [ ] طباعة الكشف (< 3 ثواني)

---

## 📝 ملاحظات الاختبار
- سجل أي خطأ أو مشكلة
- تأكد من وجود بيانات كافية في قاعدة البيانات
- اختبر كل دور (معلم، رئيس قسم، كنترول، مدير)
- اختبر الحالات الخاصة والأخطاء
