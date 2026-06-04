const mongoose = require('mongoose');
const User = require('../models/User');
const Student = require('../models/Student');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const Settings = require('../models/Settings');
const dotenv = require('dotenv');

dotenv.config();

const connectDB = async () => {
  try {
    const conn = await mongoose.connect(process.env.MONGO_URI || 'mongodb://localhost:27017/school_erp', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log(`MongoDB Connected: ${conn.connection.host}`);
  } catch (err) {
    console.error(`Error: ${err.message}`);
    process.exit(1);
  }
};

const seedData = async () => {
  await connectDB();

  // Clear existing data
  await User.deleteMany();
  await Student.deleteMany();
  await Class.deleteMany();
  await Subject.deleteMany();
  await Settings.deleteMany();

  console.log('Data cleared...');

  // Create subjects
  const subjects = await Subject.insertMany([
    { name: 'رياضيات', code: 'MATH', department: 'علوم' },
    { name: 'فيزياء', code: 'PHY', department: 'علوم' },
    { name: 'كيمياء', code: 'CHEM', department: 'علوم' },
    { name: 'أحياء', code: 'BIO', department: 'علوم' },
    { name: 'لغة عربية', code: 'ARB', department: 'لغات' },
    { name: 'لغة إنجليزية', code: 'ENG', department: 'لغات' },
    { name: 'تربية إسلامية', code: 'ISL', department: 'دين' },
    { name: 'تاريخ', code: 'HIS', department: 'اجتماعيات' },
    { name: 'جغرافيا', code: 'GEO', department: 'اجتماعيات' }
  ]);

  console.log(`${subjects.length} subjects created`);

  // Create users
  const users = await User.insertMany([
    {
      civilId: '999999',
      name: 'المدير',
      email: 'admin@school.edu',
      password: '123456',
      role: 'admin'
    },
    {
      civilId: '789012',
      name: 'رئيس قسم العلوم',
      email: 'head@school.edu',
      password: '123456',
      role: 'head',
      department: 'علوم'
    },
    {
      civilId: '123456',
      name: 'معلم رياضيات',
      email: 'teacher@school.edu',
      password: '123456',
      role: 'teacher',
      department: 'علوم'
    },
    {
      civilId: '555555',
      name: 'كنترول',
      email: 'control@school.edu',
      password: '123456',
      role: 'control'
    }
  ]);

  console.log(`${users.length} users created`);

  // Create classes
  const classes = await Class.insertMany([
    { name: '6/1', grade: '6', section: '1' },
    { name: '6/2', grade: '6', section: '2' },
    { name: '7/1', grade: '7', section: '1' },
    { name: '7/2', grade: '7', section: '2' },
    { name: '8/1', grade: '8', section: '1' },
    { name: '8/2', grade: '8', section: '2' },
    { name: '9/1', grade: '9', section: '1' },
    { name: '9/2', grade: '9', section: '2' }
  ]);

  console.log(`${classes.length} classes created`);

  // Create students
  const studentsData = [];
  for (const cls of classes) {
    for (let i = 1; i <= 5; i++) {
      studentsData.push({
        studentId: `${cls.grade}${cls.section}${String(i).padStart(3, '0')}`,
        name: `طالب ${cls.grade}/${cls.section} - ${i}`,
        classId: cls._id,
        grade: cls.grade,
        section: cls.section
      });
    }
  }

  const students = await Student.insertMany(studentsData);
  console.log(`${students.length} students created`);

  // Update classes with students
  for (const cls of classes) {
    const classStudents = students.filter(s => s.classId.toString() === cls._id.toString());
    cls.students = classStudents.map(s => s._id);
    await cls.save();
  }

  // Assign teachers to classes
  const mathTeacher = users.find(u => u.role === 'teacher');
  const mathSubject = subjects.find(s => s.code === 'MATH');

  for (const cls of classes) {
    cls.teachers.push({
      teacher: mathTeacher._id,
      subject: mathSubject._id
    });
    await cls.save();
  }

  // Update teacher with classes and subjects
  mathTeacher.classes = classes.map(c => c._id);
  mathTeacher.subjects = [mathSubject._id];
  await mathTeacher.save();

  // Create default settings
  await Settings.create({
    activePeriod: '1',
    activeColumns: { classwork: true, exam: true },
    schoolName: 'مدرسة النموذجية',
    academicYear: '2025-2026'
  });

  console.log('Settings created');
  console.log('\n✅ Seed completed!');
  console.log('\nDefault login credentials:');
  console.log('Admin: 999999 / 123456');
  console.log('Head: 789012 / 123456');
  console.log('Teacher: 123456 / 123456');
  console.log('Control: 555555 / 123456');

  process.exit(0);
};

seedData().catch(err => {
  console.error(err);
  process.exit(1);
});
