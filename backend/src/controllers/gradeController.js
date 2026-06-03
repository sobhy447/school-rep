const Grade = require('../models/Grade');
const Student = require('../models/Student');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const User = require('../models/User');
const Excuse = require('../models/Excuse');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get teacher's classes with subjects
// @route   GET /api/grades/teacher/classes
// @access  Private (Teacher)
exports.getTeacherClasses = asyncHandler(async (req, res, next) => {
  const teacherId = req.user._id;

  // Find classes where teacher is assigned
  const classes = await Class.find({ teachers: teacherId })
    .populate('students', 'name studentId')
    .populate('subjects', 'name code')
    .populate('gradeLevel', 'name level');

  // Format response
  const formattedClasses = classes.map(cls => ({
    _id: cls._id,
    name: cls.name,
    gradeLevel: cls.gradeLevel?.level || 6,
    studentCount: cls.students.length,
    subjects: cls.subjects.map(sub => ({
      _id: sub._id,
      name: sub.name,
      code: sub.code
    })),
    subjectName: cls.subjects.length === 1 ? cls.subjects[0].name : null
  }));

  res.status(200).json({
    success: true,
    data: formattedClasses
  });
});

// @desc    Get grades for a class/subject/period
// @route   GET /api/grades/class/:classId/subject/:subjectId
// @access  Private (Teacher, Head, Admin)
exports.getClassGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId } = req.params;
  const { period = 'first' } = req.query;
  const userId = req.user._id;
  const userRole = req.user.role;

  // Check permission
  const classData = await Class.findById(classId)
    .populate('students', 'name studentId gradeLevel')
    .populate('teachers', 'name');

  if (!classData) {
    return next(new ErrorResponse('الفصل غير موجود', 404));
  }

  // Check if user is teacher of this class or head or admin
  const isTeacher = classData.teachers.some(t => t._id.toString() === userId.toString());
  const isHead = req.user.role === 'head' && req.user.subject?.toString() === subjectId;
  const isAdmin = userRole === 'admin';

  if (!isTeacher && !isHead && !isAdmin) {
    return next(new ErrorResponse('غير مصرح لك بعرض هذا الفصل', 403));
  }

  // Get grades
  const grades = await Grade.find({
    class: classId,
    subject: subjectId,
    period,
    academicYear: new Date().getFullYear()
  }).populate('student', 'name studentId');

  // Get excuses
  const excuses = await Excuse.find({
    class: classId,
    subject: subjectId,
    period,
    academicYear: new Date().getFullYear()
  });

  // Format grades
  const gradesMap = {};
  grades.forEach(grade => {
    gradesMap[grade.student._id.toString()] = {
      work: grade.work,
      exam: grade.exam,
      workSaved: grade.workSaved,
      examSaved: grade.examSaved,
      workSubmitted: grade.workSubmitted,
      examSubmitted: grade.examSubmitted,
      workEditedByHead: grade.workEditedByHead,
      examEditedByHead: grade.examEditedByHead
    };
  });

  // Format excuses
  const excusesMap = {};
  excuses.forEach(excuse => {
    if (!excusesMap[excuse.student.toString()]) {
      excusesMap[excuse.student.toString()] = {};
    }
    excusesMap[excuse.student.toString()][excuse.type] = true;
  });

  // Approval status
  const approvalStatus = {
    work: grades.some(g => g.workSubmitted) ? 'submitted' : 'pending',
    exam: grades.some(g => g.examSubmitted) ? 'submitted' : 'pending'
  };

  // Check if fully approved by head
  const headApproval = await Grade.findOne({
    class: classId,
    subject: subjectId,
    period,
    workDeptApproved: true,
    examDeptApproved: true
  });

  if (headApproval) {
    approvalStatus.work = 'head_approved';
    approvalStatus.exam = 'head_approved';
  }

  res.status(200).json({
    success: true,
    data: {
      students: classData.students,
      grades: gradesMap,
      excuses: excusesMap,
      approvalStatus,
      settings: {
        workMax: 40,
        examMax: 60,
        allowHalf: true,
        workEnabled: true,
        examEnabled: true,
        firstPeriodEnabled: true,
        secondPeriodEnabled: true
      }
    }
  });
});

// @desc    Save grades (draft)
// @route   POST /api/grades/save
// @access  Private (Teacher)
exports.saveGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, grades } = req.body;
  const teacherId = req.user._id;

  // Validate input
  if (!classId || !subjectId || !period || !grades) {
    return next(new ErrorResponse('بيانات غير مكتملة', 400));
  }

  // Check if class belongs to teacher
  const classData = await Class.findById(classId);
  if (!classData.teachers.includes(teacherId)) {
    return next(new ErrorResponse('غير مصرح لك بحفظ درجات هذا الفصل', 403));
  }

  // Save each grade
  const savedGrades = [];
  for (const [studentId, gradeData] of Object.entries(grades)) {
    let grade = await Grade.findOne({
      student: studentId,
      class: classId,
      subject: subjectId,
      period,
      academicYear: new Date().getFullYear()
    });

    if (!grade) {
      grade = new Grade({
        student: studentId,
        class: classId,
        subject: subjectId,
        teacher: teacherId,
        period,
        academicYear: new Date().getFullYear()
      });
    }

    // Update only if not submitted
    if (!grade.workSubmitted && gradeData.work !== undefined) {
      grade.work = gradeData.work;
      grade.workSaved = true;
    }
    if (!grade.examSubmitted && gradeData.exam !== undefined) {
      grade.exam = gradeData.exam;
      grade.examSaved = true;
    }

    await grade.save();
    savedGrades.push(grade);
  }

  res.status(200).json({
    success: true,
    message: 'تم حفظ الدرجات بنجاح',
    data: savedGrades.length
  });
});

// @desc    Submit grades for approval (final)
// @route   POST /api/grades/submit
// @access  Private (Teacher)
exports.submitGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type, grades } = req.body;
  const teacherId = req.user._id;

  // Validate
  if (!classId || !subjectId || !period || !type) {
    return next(new ErrorResponse('بيانات غير مكتملة', 400));
  }

  // Check if all students have grades
  const classData = await Class.findById(classId).populate('students');
  const allStudents = classData.students;

  // Check for missing grades (excluding excused students)
  const excuses = await Excuse.find({
    class: classId,
    subject: subjectId,
    period,
    type
  });

  const excusedStudentIds = excuses.map(e => e.student.toString());

  const missingStudents = allStudents.filter(s => {
    const studentId = s._id.toString();
    const hasGrade = grades[studentId] && grades[studentId][type] !== undefined;
    const isExcused = excusedStudentIds.includes(studentId);
    return !hasGrade && !isExcused;
  });

  if (missingStudents.length > 0) {
    return next(new ErrorResponse(
      `يوجد ${missingStudents.length} طالب بدون درجة - لا يمكن الاعتماد`,
      400
    ));
  }

  // Update grades to submitted
  for (const [studentId, gradeData] of Object.entries(grades)) {
    const grade = await Grade.findOne({
      student: studentId,
      class: classId,
      subject: subjectId,
      period,
      academicYear: new Date().getFullYear()
    });

    if (grade) {
      if (type === 'work') {
        grade.workSubmitted = true;
        grade.workSubmittedAt = new Date();
      } else {
        grade.examSubmitted = true;
        grade.examSubmittedAt = new Date();
      }
      await grade.save();
    }
  }

  res.status(200).json({
    success: true,
    message: `تم اعتماد درجات ${type === 'work' ? 'الأعمال' : 'الاختبار'} بنجاح`
  });
});

// @desc    Get head's department classes
// @route   GET /api/grades/head/department-classes
// @access  Private (Head)
exports.getHeadDepartmentClasses = asyncHandler(async (req, res, next) => {
  const headId = req.user._id;
  const headSubject = req.user.subject;

  if (!headSubject) {
    return next(new ErrorResponse('لم يتم تحديد مادة رئيس القسم', 400));
  }

  // Find all classes with teachers who teach head's subject
  const classes = await Class.find()
    .populate('teachers', 'name subject')
    .populate('students', 'name')
    .populate('subjects', 'name code')
    .populate('gradeLevel', 'name level');

  // Filter classes that have the head's subject
  const departmentClasses = classes.filter(cls => 
    cls.subjects.some(s => s._id.toString() === headSubject.toString())
  );

  // Format with approval status
  const formattedClasses = await Promise.all(
    departmentClasses.map(async (cls) => {
      const subjectTeacher = cls.teachers.find(t => 
        t.subject?.toString() === headSubject.toString()
      );

      const grades = await Grade.find({
        class: cls._id,
        subject: headSubject,
        academicYear: new Date().getFullYear()
      });

      const workApproved = grades.length > 0 && grades.every(g => g.workSubmitted);
      const examApproved = grades.length > 0 && grades.every(g => g.examSubmitted);

      return {
        _id: cls._id,
        name: cls.name,
        gradeLevel: cls.gradeLevel?.level || 6,
        studentCount: cls.students.length,
        teacherId: subjectTeacher?._id,
        teacherName: subjectTeacher?.name || 'غير محدد',
        subjectId: headSubject,
        subjectName: cls.subjects.find(s => s._id.toString() === headSubject.toString())?.name,
        workApproved,
        examApproved,
        isHeadClass: cls.teachers.some(t => t._id.toString() === headId.toString())
      };
    })
  );

  res.status(200).json({
    success: true,
    data: formattedClasses
  });
});

// @desc    Get teacher grades for review (head)
// @route   GET /api/grades/head/review/:classId/:teacherId/:subjectId
// @access  Private (Head)
exports.getTeacherGradesForReview = asyncHandler(async (req, res, next) => {
  const { classId, teacherId, subjectId } = req.params;
  const { period = 'first' } = req.query;
  const headId = req.user._id;

  // Check if head's subject matches
  const head = await User.findById(headId);
  if (head.subject?.toString() !== subjectId) {
    return next(new ErrorResponse('غير مصرح لك بمراجعة هذا القسم', 403));
  }

  const classData = await Class.findById(classId)
    .populate('students', 'name studentId')
    .populate('teachers', 'name');

  const teacher = classData.teachers.find(t => t._id.toString() === teacherId);
  const subject = await Subject.findById(subjectId);

  const grades = await Grade.find({
    class: classId,
    subject: subjectId,
    teacher: teacherId,
    period,
    academicYear: new Date().getFullYear()
  }).populate('student', 'name studentId');

  const excuses = await Excuse.find({
    class: classId,
    subject: subjectId,
    period,
    academicYear: new Date().getFullYear()
  });

  const gradesMap = {};
  grades.forEach(g => {
    gradesMap[g.student._id.toString()] = {
      work: g.work,
      exam: g.exam,
      workSaved: g.workSaved,
      examSaved: g.examSaved,
      workSubmitted: g.workSubmitted,
      examSubmitted: g.examSubmitted,
      workEditedByHead: g.workEditedByHead,
      examEditedByHead: g.examEditedByHead
    };
  });

  const excusesMap = {};
  excuses.forEach(e => {
    if (!excusesMap[e.student.toString()]) excusesMap[e.student.toString()] = {};
    excusesMap[e.student.toString()][e.type] = true;
  });

  res.status(200).json({
    success: true,
    data: {
      classInfo: { _id: classData._id, name: classData.name },
      teacherInfo: { _id: teacher?._id, name: teacher?.name },
      subjectInfo: { _id: subject._id, name: subject.name },
      period,
      students: classData.students,
      grades: gradesMap,
      excuses: excusesMap,
      approvalStatus: {
        work: grades.some(g => g.workSubmitted) ? 'submitted' : 'pending',
        exam: grades.some(g => g.examSubmitted) ? 'submitted' : 'pending'
      }
    }
  });
});

// @desc    Edit grade as head
// @route   POST /api/grades/head/edit
// @access  Private (Head)
exports.editGradeAsHead = asyncHandler(async (req, res, next) => {
  const { classId, teacherId, subjectId, studentId, type, value } = req.body;
  const headId = req.user._id;

  if (!classId || !teacherId || !subjectId || !studentId || !type || value === undefined) {
    return next(new ErrorResponse('بيانات غير مكتملة', 400));
  }

  const head = await User.findById(headId);
  if (head.subject?.toString() !== subjectId) {
    return next(new ErrorResponse('غير مصرح لك بتعديل درجات هذا القسم', 403));
  }

  let grade = await Grade.findOne({
    student: studentId,
    class: classId,
    subject: subjectId,
    teacher: teacherId,
    academicYear: new Date().getFullYear()
  });

  if (!grade) {
    grade = new Grade({
      student: studentId,
      class: classId,
      subject: subjectId,
      teacher: teacherId,
      period: req.body.period || 'first',
      academicYear: new Date().getFullYear()
    });
  }

  if (type === 'work') {
    grade.work = value;
    grade.workEditedByHead = true;
    grade.workEditedByHeadAt = new Date();
  } else {
    grade.exam = value;
    grade.examEditedByHead = true;
    grade.examEditedByHeadAt = new Date();
  }

  await grade.save();

  res.status(200).json({
    success: true,
    message: 'تم تعديل الدرجة بنجاح'
  });
});

// @desc    Approve department grades
// @route   POST /api/grades/head/approve-department
// @access  Private (Head)
exports.approveDepartment = asyncHandler(async (req, res, next) => {
  const { type, period } = req.body;
  const headId = req.user._id;
  const headSubject = req.user.subject;

  if (!headSubject) {
    return next(new ErrorResponse('لم يتم تحديد مادة رئيس القسم', 400));
  }

  const classes = await Class.find({ subjects: headSubject });

  for (const cls of classes) {
    const grades = await Grade.find({
      class: cls._id,
      subject: headSubject,
      period,
      academicYear: new Date().getFullYear()
    });

    const allSubmitted = type === 'work' 
      ? grades.every(g => g.workSubmitted)
      : grades.every(g => g.examSubmitted);

    if (!allSubmitted) {
      return next(new ErrorResponse(
        `لم يتم اعتماد جميع الفصول - ${cls.name} باقي`,
        400
      ));
    }
  }

  await Grade.updateMany(
    {
      class: { $in: classes.map(c => c._id) },
      subject: headSubject,
      period,
      academicYear: new Date().getFullYear()
    },
    {
      $set: type === 'work' 
        ? { workDeptApproved: true, workDeptApprovedAt: new Date() }
        : { examDeptApproved: true, examDeptApprovedAt: new Date() }
    }
  );

  res.status(200).json({
    success: true,
    message: `تم اعتماد ${type === 'work' ? 'الأعمال' : 'الاختبار'} للقسم بنجاح`
  });
});

// @desc    Get all approved grades for control
// @route   GET /api/grades/control/all
// @access  Private (Control, Admin)
exports.getAllGrades = asyncHandler(async (req, res, next) => {
  const { gradeLevel, subject, period = 'first', type = 'all' } = req.query;

  const query = {
    academicYear: new Date().getFullYear(),
    $or: [
      { workDeptApproved: true },
      { examDeptApproved: true }
    ]
  };

  if (period) query.period = period;

  const grades = await Grade.find(query)
    .populate('student', 'name studentId')
    .populate('class', 'name gradeLevel')
    .populate('subject', 'name')
    .populate('teacher', 'name');

  const grouped = {};
  grades.forEach(grade => {
    const key = `${grade.class._id}-${grade.subject._id}`;
    if (!grouped[key]) {
      grouped[key] = {
        _id: key,
        classId: grade.class._id,
        className: grade.class.name,
        gradeLevel: grade.class.gradeLevel,
        subjectId: grade.subject._id,
        subjectName: grade.subject.name,
        teacherId: grade.teacher._id,
        teacherName: grade.teacher.name,
        period: grade.period,
        workDeptApproved: false,
        examDeptApproved: false,
        students: [],
        grades: {},
        excuses: {}
      };
    }

    grouped[key].students.push(grade.student);
    grouped[key].grades[grade.student._id.toString()] = {
      work: grade.work,
      exam: grade.exam
    };

    if (grade.workDeptApproved) grouped[key].workDeptApproved = true;
    if (grade.examDeptApproved) grouped[key].examDeptApproved = true;
  });

  let result = Object.values(grouped);
  if (gradeLevel) {
    result = result.filter(r => r.gradeLevel === parseInt(gradeLevel));
  }
  if (subject) {
    result = result.filter(r => r.subjectName === subject);
  }

  result.forEach(r => {
    r.students = [...new Map(r.students.map(s => [s._id.toString(), s])).values()];
  });

  res.status(200).json({
    success: true,
    count: result.length,
    data: result
  });
});

// @desc    Get print data
// @route   GET /api/grades/control/print
// @access  Private (Control, Admin)
exports.getPrintData = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type = 'all' } = req.query;

  const schoolInfo = {
    name: 'مدرسة النموذجية',
    ministry: 'وزارة التربية',
    country: 'دولة الكويت',
    address: 'العاصمة - الكويت',
    academicYear: '2025-2026',
    director: 'مدير المدرسة'
  };

  const classData = await Class.findById(classId)
    .populate('students', 'name studentId')
    .populate('teachers', 'name');

  const subject = await Subject.findById(subjectId);
  const teacher = classData.teachers[0];
  const head = await User.findOne({ role: 'head', subject: subjectId });

  const grades = await Grade.find({
    class: classId,
    subject: subjectId,
    period,
    academicYear: new Date().getFullYear()
  }).populate('student', 'name studentId');

  const excuses = await Excuse.find({
    class: classId,
    subject: subjectId,
    period,
    academicYear: new Date().getFullYear()
  });

  const gradesMap = {};
  grades.forEach(g => {
    gradesMap[g.student._id.toString()] = {
      work: g.work,
      exam: g.exam,
      workEditedByHead: g.workEditedByHead,
      examEditedByHead: g.examEditedByHead
    };
  });

  const excusesMap = {};
  excuses.forEach(e => {
    if (!excusesMap[e.student.toString()]) excusesMap[e.student.toString()] = {};
    excusesMap[e.student.toString()][e.type] = true;
  });

  res.status(200).json({
    success: true,
    data: {
      schoolInfo,
      classInfo: { _id: classData._id, name: classData.name },
      subjectInfo: { _id: subject._id, name: subject.name },
      teacherInfo: { 
        _id: teacher?._id, 
        name: teacher?.name,
        approvalDate: grades[0]?.workSubmittedAt || grades[0]?.examSubmittedAt
      },
      headInfo: {
        _id: head?._id,
        name: head?.name,
        approvalDate: grades[0]?.workDeptApprovedAt || grades[0]?.examDeptApprovedAt
      },
      period,
      students: classData.students,
      grades: gradesMap,
      excuses: excusesMap
    }
  });
});

// @desc    Unlock approval (admin)
// @route   POST /api/grades/admin/unlock
// @access  Private (Admin)
exports.unlockApproval = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type, level } = req.body;

  const update = {};
  if (level === 'teacher') {
    if (type === 'work' || type === 'all') {
      update.workSubmitted = false;
      update.workSubmittedAt = null;
    }
    if (type === 'exam' || type === 'all') {
      update.examSubmitted = false;
      update.examSubmittedAt = null;
    }
  } else if (level === 'head') {
    if (type === 'work' || type === 'all') {
      update.workDeptApproved = false;
      update.workDeptApprovedAt = null;
    }
    if (type === 'exam' || type === 'all') {
      update.examDeptApproved = false;
      update.examDeptApprovedAt = null;
    }
  }

  await Grade.updateMany(
    { class: classId, subject: subjectId, period },
    { $set: update }
  );

  res.status(200).json({
    success: true,
    message: 'تم فك الاعتماد بنجاح'
  });
});
