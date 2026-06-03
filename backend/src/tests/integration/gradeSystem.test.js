const mongoose = require('mongoose');
const request = require('supertest');
const app = require('../app');
const User = require('../models/User');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const Student = require('../models/Student');
const Grade = require('../models/Grade');
const Settings = require('../models/Settings');

// Test data
let adminToken, headToken, teacherToken, controlToken;
let testClass, testSubject, testStudent, testTeacher, testHead;

describe('🧪 نظام رصد الدرجات - اختبار شامل', () => {

  beforeAll(async () => {
    // Connect to test database
    await mongoose.connect(process.env.MONGO_URI_TEST || 'mongodb://localhost:27017/school_erp_test');

    // Clear collections
    await User.deleteMany({});
    await Class.deleteMany({});
    await Subject.deleteMany({});
    await Student.deleteMany({});
    await Grade.deleteMany({});
    await Settings.deleteMany({});

    // Create test users
    testTeacher = await User.create({
      name: 'معلم تجريبي',
      civilId: '123456',
      password: '123456',
      role: 'teacher'
    });

    testHead = await User.create({
      name: 'رئيس قسم تجريبي',
      civilId: '789012',
      password: '123456',
      role: 'head'
    });

    const admin = await User.create({
      name: 'مدير تجريبي',
      civilId: '999999',
      password: '123456',
      role: 'admin'
    });

    // Create test subject
    testSubject = await Subject.create({
      name: 'الرياضيات',
      code: 'MATH'
    });

    // Assign subject to teacher and head
    testTeacher.subject = testSubject._id;
    testHead.subject = testSubject._id;
    await testTeacher.save();
    await testHead.save();

    // Create test class
    testClass = await Class.create({
      name: '6/1',
      gradeLevel: 6,
      subjects: [testSubject._id],
      teachers: [testTeacher._id]
    });

    // Create test student
    testStudent = await Student.create({
      name: 'طالب تجريبي',
      studentId: '2025001',
      class: testClass._id
    });

    // Add student to class
    testClass.students.push(testStudent._id);
    await testClass.save();

    // Create default settings
    await Settings.create({
      firstPeriodEnabled: true,
      secondPeriodEnabled: true,
      workEnabled: true,
      examEnabled: true
    });

    // Login and get tokens
    const teacherLogin = await request(app)
      .post('/api/auth/login')
      .send({ civilId: '123456', password: '123456' });
    teacherToken = teacherLogin.body.token;

    const headLogin = await request(app)
      .post('/api/auth/login')
      .send({ civilId: '789012', password: '123456' });
    headToken = headLogin.body.token;

    const adminLogin = await request(app)
      .post('/api/auth/login')
      .send({ civilId: '999999', password: '123456' });
    adminToken = adminLogin.body.token;
  });

  afterAll(async () => {
    await mongoose.connection.close();
  });

  // ==================== TEST 1: Teacher Workflow ====================
  describe('👨‍🏫 اختبار المعلم', () => {

    test('1.1: عرض فصول المعلم', async () => {
      const res = await request(app)
        .get('/api/grades/teacher/classes')
        .set('Authorization', `Bearer ${teacherToken}`);

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data).toHaveLength(1);
      expect(res.body.data[0].name).toBe('6/1');
    });

    test('1.2: عرض درجات الفصل', async () => {
      const res = await request(app)
        .get(`/api/grades/class/${testClass._id}/subject/${testSubject._id}?period=first`)
        .set('Authorization', `Bearer ${teacherToken}`);

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data.students).toHaveLength(1);
      expect(res.body.data.settings.workMax).toBe(40);
      expect(res.body.data.settings.examMax).toBe(60);
    });

    test('1.3: حفظ درجات (مسودة)', async () => {
      const res = await request(app)
        .post('/api/grades/save')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          grades: {
            [testStudent._id]: { work: 35, exam: 55 }
          }
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.message).toContain('تم حفظ');
    });

    test('1.4: اعتماد درجات الأعمال', async () => {
      const res = await request(app)
        .post('/api/grades/submit')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          type: 'work',
          grades: {
            [testStudent._id]: { work: 35 }
          }
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.message).toContain('تم اعتماد');
    });

    test('1.5: اعتماد درجات الاختبار', async () => {
      const res = await request(app)
        .post('/api/grades/submit')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          type: 'exam',
          grades: {
            [testStudent._id]: { exam: 55 }
          }
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
    });

    test('1.6: عدم القدرة على التعديل بعد الاعتماد', async () => {
      const res = await request(app)
        .post('/api/grades/save')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          grades: {
            [testStudent._id]: { work: 40 }
          }
        });

      // Should not update because already submitted
      const grade = await Grade.findOne({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id
      });

      expect(grade.work).toBe(35); // Should remain 35, not 40
    });
  });

  // ==================== TEST 2: Head of Department Workflow ====================
  describe('👨‍💼 اختبار رئيس القسم', () => {

    test('2.1: عرض فصول القسم', async () => {
      const res = await request(app)
        .get('/api/grades/head/department-classes')
        .set('Authorization', `Bearer ${headToken}`);

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data).toHaveLength(1);
      expect(res.body.data[0].workApproved).toBe(true);
      expect(res.body.data[0].examApproved).toBe(true);
    });

    test('2.2: مراجعة درجات المعلم', async () => {
      const res = await request(app)
        .get(`/api/grades/head/review/${testClass._id}/${testTeacher._id}/${testSubject._id}?period=first`)
        .set('Authorization', `Bearer ${headToken}`);

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data.students).toHaveLength(1);
      expect(res.body.data.grades[testStudent._id].work).toBe(35);
    });

    test('2.3: تعديل درجة من رئيس القسم', async () => {
      const res = await request(app)
        .post('/api/grades/head/edit')
        .set('Authorization', `Bearer ${headToken}`)
        .send({
          classId: testClass._id,
          teacherId: testTeacher._id,
          subjectId: testSubject._id,
          studentId: testStudent._id,
          type: 'work',
          value: 38
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);

      // Verify edit
      const grade = await Grade.findOne({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id
      });

      expect(grade.work).toBe(38);
      expect(grade.workEditedByHead).toBe(true);
    });

    test('2.4: اعتماد القسم', async () => {
      const res = await request(app)
        .post('/api/grades/head/approve-department')
        .set('Authorization', `Bearer ${headToken}`)
        .send({
          type: 'work',
          period: 'first'
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.message).toContain('تم اعتماد');

      // Verify department approval
      const grade = await Grade.findOne({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id
      });

      expect(grade.workDeptApproved).toBe(true);
    });
  });

  // ==================== TEST 3: Admin Workflow ====================
  describe('👨‍💻 اختبار المدير', () => {

    test('3.1: عرض الإعدادات', async () => {
      const res = await request(app)
        .get('/api/admin/settings')
        .set('Authorization', `Bearer ${adminToken}`);

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);
      expect(res.body.data.firstPeriodEnabled).toBe(true);
    });

    test('3.2: تحديث الإعدادات', async () => {
      const res = await request(app)
        .post('/api/admin/settings')
        .set('Authorization', `Bearer ${adminToken}`)
        .send({
          firstPeriodEnabled: false,
          workEnabled: false
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);

      // Verify
      const settings = await Settings.findOne();
      expect(settings.firstPeriodEnabled).toBe(false);
      expect(settings.workEnabled).toBe(false);
    });

    test('3.3: فك الاعتماد', async () => {
      const res = await request(app)
        .post('/api/admin/unlock')
        .set('Authorization', `Bearer ${adminToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          type: 'work',
          level: 'teacher'
        });

      expect(res.status).toBe(200);
      expect(res.body.success).toBe(true);

      // Verify unlock
      const grade = await Grade.findOne({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id
      });

      expect(grade.workSubmitted).toBe(false);
    });
  });

  // ==================== TEST 4: Validation Tests ====================
  describe('⚠️ اختبار الحالات الخاصة', () => {

    test('4.1: درجة أكبر من القصوى', async () => {
      const res = await request(app)
        .post('/api/grades/save')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          grades: {
            [testStudent._id]: { work: 50 } // More than 40
          }
        });

      // Should be capped at 40 or rejected
      const grade = await Grade.findOne({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id
      });

      expect(grade.work).toBeLessThanOrEqual(40);
    });

    test('4.2: اعتماد بدون رصد كل الطلاب', async () => {
      // Create another student without grade
      const anotherStudent = await Student.create({
        name: 'طالب آخر',
        studentId: '2025002',
        class: testClass._id
      });

      testClass.students.push(anotherStudent._id);
      await testClass.save();

      const res = await request(app)
        .post('/api/grades/submit')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          type: 'work',
          grades: {
            [testStudent._id]: { work: 35 }
            // Missing anotherStudent
          }
        });

      expect(res.status).toBe(400);
      expect(res.body.success).toBe(false);
    });

    test('4.3: عدم السماح برصد درجة لطالب معذور', async () => {
      // Create excuse
      const Excuse = require('../models/Excuse');
      await Excuse.create({
        student: testStudent._id,
        class: testClass._id,
        subject: testSubject._id,
        period: 'first',
        type: 'work',
        reason: 'مريض',
        createdBy: testTeacher._id
      });

      // Try to submit - should fail or skip
      const res = await request(app)
        .post('/api/grades/submit')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          type: 'work',
          grades: {} // Empty because student is excused
        });

      // Should succeed because student is excused
      expect(res.status).toBe(200);
    });
  });

  // ==================== TEST 5: Performance Tests ====================
  describe('⚡ اختبار الأداء', () => {

    test('5.1: تحميل الفصول (< 2 ثانية)', async () => {
      const start = Date.now();

      await request(app)
        .get('/api/grades/teacher/classes')
        .set('Authorization', `Bearer ${teacherToken}`);

      const duration = Date.now() - start;
      expect(duration).toBeLessThan(2000);
    });

    test('5.2: حفظ الدرجات (< 1 ثانية)', async () => {
      const start = Date.now();

      await request(app)
        .post('/api/grades/save')
        .set('Authorization', `Bearer ${teacherToken}`)
        .send({
          classId: testClass._id,
          subjectId: testSubject._id,
          period: 'first',
          grades: {
            [testStudent._id]: { work: 30, exam: 50 }
          }
        });

      const duration = Date.now() - start;
      expect(duration).toBeLessThan(1000);
    });
  });
});

// Run tests
// npm test -- tests/integration/gradeSystem.test.js
