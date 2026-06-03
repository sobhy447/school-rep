const mongoose = require('mongoose');
const User = require('../models/User');
const Subject = require('../models/Subject');
const Class = require('../models/Class');
const Student = require('../models/Student');
const Settings = require('../models/Settings');

const seedData = async () => {
  try {
    console.log('🌱 Starting seed...');

    // Clear existing data
    await User.deleteMany({});
    await Subject.deleteMany({});
    await Class.deleteMany({});
    await Student.deleteMany({});
    await Settings.deleteMany({});

    // Create subjects
    const math = await Subject.create({
      name: 'الرياضيات',
      code: 'MATH',
      workMax: 40,
      examMax: 60
    });

    const science = await Subject.create({
      name: 'العلوم',
      code: 'SCI',
      workMax: 40,
      examMax: 60
    });

    const arabic = await Subject.create({
      name: 'اللغة العربية',
      code: 'ARB',
      workMax: 40,
      examMax: 60
    });

    console.log('✅ Subjects created');

    // Create users
    const admin = await User.create({
      name: 'مدير النظام',
      civilId: '999999',
      password: '123456',
      role: 'admin'
    });

    const head = await User.create({
      name: 'رئيس قسم الرياضيات',
      civilId: '789012',
      password: '123456',
      role: 'head',
      subject: math._id
    });

    const teacher = await User.create({
      name: 'معلم الرياضيات',
      civilId: '123456',
      password: '123456',
      role: 'teacher',
      subject: math._id
    });

    const control = await User.create({
      name: 'مسؤول الكنترول',
      civilId: '555555',
      password: '123456',
      role: 'control'
    });

    console.log('✅ Users created');

    // Create classes
    const class6_1 = await Class.create({
      name: '6/1',
      gradeLevel: 6,
      subjects: [math._id, science._id, arabic._id],
      teachers: [teacher._id]
    });

    const class7_1 = await Class.create({
      name: '7/1',
      gradeLevel: 7,
      subjects: [math._id, science._id, arabic._id],
      teachers: [teacher._id]
    });

    console.log('✅ Classes created');

    // Create students
    const students = [];
    for (let i = 1; i <= 10; i++) {
      const student = await Student.create({
        name: `طالب ${i}`,
        studentId: `202500${i}`,
        class: class6_1._id,
        gradeLevel: 6,
        gender: i % 2 === 0 ? 'male' : 'female'
      });
      students.push(student);
    }

    // Add students to class
    class6_1.students = students.map(s => s._id);
    await class6_1.save();

    console.log('✅ Students created');

    // Create settings
    await Settings.create({
      firstPeriodEnabled: true,
      secondPeriodEnabled: true,
      workEnabled: true,
      examEnabled: true,
      schoolName: 'مدرسة النموذجية',
      ministryName: 'وزارة التربية',
      country: 'دولة الكويت',
      academicYear: '2025-2026'
    });

    console.log('✅ Settings created');

    console.log('\n🎉 Seed completed successfully!');
    console.log('\nDefault credentials:');
    console.log('  Admin:    civilId=999999, password=123456');
    console.log('  Head:     civilId=789012, password=123456');
    console.log('  Teacher:  civilId=123456, password=123456');
    console.log('  Control:  civilId=555555, password=123456');

    process.exit(0);
  } catch (err) {
    console.error('❌ Seed error:', err);
    process.exit(1);
  }
};

// Run if called directly
if (require.main === module) {
  require('dotenv').config();
  mongoose.connect(process.env.MONGO_URI)
    .then(() => seedData())
    .catch(err => {
      console.error('❌ Database connection error:', err);
      process.exit(1);
    });
}

module.exports = seedData;
