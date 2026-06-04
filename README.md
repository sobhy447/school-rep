# نظام ERP المدرسة (School ERP System)

نظام متكامل لإدارة رصد درجات الطلاب للمدارس الكويتية.

## المميزات

- ✅ رصد الدرجات (معلم + رئيس قسم + كنترول)
- ✅ اعتماد متدرج (معلم → رئيس قسم → مدير)
- ✅ نظام الاعذار
- ✅ إعدادات المدير (فترات + أعمدة + فك اعتماد)
- ✅ إشعارات لحظية
- ✅ طباعة PDF (كشوف رسمية)
- ✅ تصميم RTL احترافي

## التقنيات

**Backend:** Node.js + Express + MongoDB
**Frontend:** React + Vite + Tailwind CSS

## النشر على Render.com

### 1. MongoDB Atlas
- أنشئ حساب على [MongoDB Atlas](https://www.mongodb.com/cloud/atlas)
- أنشئ Cluster مجاني (M0)
- أنشئ User وانسخ Connection String

### 2. GitHub
```bash
git init
git add .
git commit -m "School ERP System"
git remote add origin https://github.com/YOUR_USERNAME/school-erp.git
git push -u origin main
```

### 3. Render.com
- أنشئ Web Service جديد
- اربط بـ GitHub repo
- اختر Node.js
- أضف Environment Variables:
```
NODE_ENV=production
MONGO_URI=mongodb+srv://username:password@cluster.mongodb.net/school_erp?retryWrites=true&w=majority
JWT_SECRET=your-secret-key-here
JWT_EXPIRE=30d
```
- Build Command: `npm install`
- Start Command: `node src/server.js`

### 4. Seed Data
```bash
npm run seed
```

## بيانات الدخول الافتراضية

| الدور | الرقم المدني | الباسورد |
|-------|-------------|----------|
| مدير | 999999 | 123456 |
| رئيس قسم | 789012 | 123456 |
| معلم | 123456 | 123456 |
| كنترول | 555555 | 123456 |

## API Endpoints

### Auth
- POST `/api/auth/login` - تسجيل الدخول

### Grades
- GET `/api/grades/teacher/classes` - فصول المعلم
- GET `/api/grades/class/:id/subject/:subjectId` - درجات الفصل
- POST `/api/grades/save` - حفظ مسودة
- POST `/api/grades/submit` - اعتماد نهائي

### Head
- GET `/api/grades/head/department-classes` - فصول القسم
- POST `/api/grades/head/approve-department` - اعتماد القسم

### Admin
- GET `/api/admin/settings` - الإعدادات
- PUT `/api/admin/settings` - تحديث الإعدادات
- POST `/api/admin/unlock` - فك الاعتماد

## الترخيص

MIT
