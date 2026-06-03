# 🎓 School ERP System - نظام إدارة المدرسة

[![Node.js](https://img.shields.io/badge/Node.js-18%2B-green)](https://nodejs.org)
[![MongoDB](https://img.shields.io/badge/MongoDB-Atlas-brightgreen)](https://mongodb.com)
[![React](https://img.shields.io/badge/React-18%2B-blue)](https://react.dev)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

## 📋 نظرة عامة

نظام ERP متكامل لإدارة المدارس، يشمل:
- 📊 **رصد الدرجات** (معلم + رئيس قسم + كنترول)
- 📄 **استخراج PDF** وطباعة كشوف الرصد
- 📈 **إحصائيات وداشبورد** تفاعلي
- ⚙️ **إدارة الإعدادات** (مدير)
- 🔔 **نظام إشعارات** لحظي
- 🏥 **نظام اعذار** الطلاب

## 🏗️ هيكل المشروع

```
school-erp/
├── backend/                 # Node.js + Express + MongoDB
│   ├── src/
│   │   ├── server.js        # نقطة الدخول
│   │   ├── controllers/     # منطق التحكم
│   │   ├── routes/         # المسارات
│   │   ├── models/         # نماذج قاعدة البيانات
│   │   ├── middleware/      # وسائط التحقق
│   │   └── utils/          # أدوات مساعدة
│   ├── package.json
│   └── .env.example
│
├── frontend/                # React + Vite + Tailwind CSS
│   ├── src/
│   │   ├── components/      # المكونات
│   │   ├── pages/           # الصفحات
│   │   ├── hooks/           # Hooks مخصصة
│   │   ├── services/        # API services
│   │   └── context/         # Context API
│   ├── package.json
│   └── .env.example
│
└── docs/                    # التوثيق
```

## 🚀 النشر السريع

### الخطوة 1: MongoDB Atlas (قاعدة البيانات)

1. ادخل على [MongoDB Atlas](https://www.mongodb.com/cloud/atlas)
2. سجل بحساب Google (مجاني)
3. أنشئ **Cluster** → اختر **M0 Sandbox** (مجاني)
4. أنشئ **Database User** (اسم + باسورد)
5. اختر **Connect** → **Connect your application**
6. انسخ الـ **Connection String**:
   ```
   mongodb+srv://username:password@cluster.mongodb.net/school_erp?retryWrites=true&w=majority
   ```

### الخطوة 2: Render.com (Backend)

1. ادخل على [Render.com](https://render.com)
2. سجل بحساب GitHub
3. أنشئ **Web Service** جديد
4. اربط بـ **GitHub repo** (`school-erp`)
5. أضف **Environment Variables**:
   ```env
   NODE_ENV=production
   MONGO_URI=mongodb+srv://username:password@cluster.mongodb.net/school_erp?retryWrites=true&w=majority
   JWT_SECRET=your-super-secret-key-here
   JWT_EXPIRE=30d
   FRONTEND_URL=https://your-frontend-url.com
   ```
6. اضغط **Create Web Service**

### الخطوة 3: Kimi Pages (Frontend)

1. بناء المشروع:
   ```bash
   cd frontend
   npm install
   npm run build
   ```
2. رفع مجلد `dist/` على [Kimi Pages](https://kimi.ai)
3. أضف **Environment Variable**:
   ```env
   VITE_API_URL=https://your-backend-url.onrender.com/api
   ```

## 🛠️ التشغيل المحلي

### المتطلبات
- Node.js 18+
- MongoDB (محلي أو Atlas)
- npm أو yarn

### Backend
```bash
cd backend
npm install
cp .env.example .env
# عدل .env ببياناتك
npm run dev
```

### Frontend
```bash
cd frontend
npm install
cp .env.example .env
# عدل .env ببياناتك
npm run dev
```

## 📚 API Documentation

### المصادقة (Auth)
```
POST /api/auth/login          # تسجيل الدخول
POST /api/auth/register       # تسجيل مستخدم جديد
```

### رصد الدرجات (Grades)
```
GET  /api/grades/teacher/classes              # فصول المعلم
GET  /api/grades/class/:id/subject/:sid       # درجات الفصل
POST /api/grades/save                         # حفظ درجات
POST /api/grades/submit                       # اعتماد درجات
GET  /api/grades/head/department-classes      # فصول القسم
POST /api/grades/head/approve-department    # اعتماد القسم
GET  /api/grades/control/all                  # كل الدرجات
GET  /api/grades/control/print               # بيانات الطباعة
```

### الإدارة (Admin)
```
GET  /api/admin/settings         # عرض الإعدادات
POST /api/admin/settings         # تحديث الإعدادات
GET  /api/admin/approvals        # عرض الاعتمادات
POST /api/admin/unlock            # فك الاعتماد
```

### الاعذار (Excuses)
```
GET  /api/excuses         # عرض الاعذار
POST /api/excuses         # إضافة عذر
DELETE /api/excuses/:id   # حذف عذر
```

### الإشعارات (Notifications)
```
GET  /api/notifications              # عرض الإشعارات
POST /api/notifications/:id/read   # تعليم كمقروء
POST /api/notifications/read-all   # تعليم الكل
DELETE /api/notifications/:id      # حذف
```

## 🎨 الأدوار والصلاحيات

| الدور | الصلاحيات |
|-------|----------|
| **👨‍🏫 المعلم** | رصد درجات فصوله، اعتماد درجاته |
| **👨‍💼 رئيس القسم** | مراجعة درجات معلميه، تعديل، اعتماد القسم |
| **🖨️ الكنترول** | مراجعة جميع الدرجات، طباعة كشوف |
| **👨‍💻 المدير** | إعدادات النظام، فك الاعتماد، إدارة المستخدمين |

## 🧪 الاختبار

```bash
# Backend tests
cd backend
npm test

# Frontend build test
cd frontend
npm run build
```

## 🔧 المتغيرات البيئية

| المتغير | الوصف | مطلوب |
|---------|-------|-------|
| `NODE_ENV` | بيئة التشغيل | نعم |
| `PORT` | منفذ السيرفر | لا (5000) |
| `MONGO_URI` | رابط MongoDB | نعم |
| `JWT_SECRET` | مفتاح JWT | نعم |
| `JWT_EXPIRE` | مدة JWT | لا (30d) |
| `FRONTEND_URL` | رابط Frontend | لا |

## 📞 الدعم

- 📧 Email: support@school-erp.com
- 🐛 Issues: [GitHub Issues](https://github.com/your-username/school-erp/issues)
- 📖 Docs: [Documentation](https://docs.school-erp.com)

## 📜 الترخيص

هذا المشروع مرخص بموجب [MIT License](LICENSE).

---

**🎉 تم إنشاؤه بـ ❤️ لفريق School ERP**
