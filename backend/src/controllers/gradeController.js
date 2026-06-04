const Grade = require('../models/Grade');
const Excuse = require('../models/Excuse');
const Student = require('../models/Student');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const User = require('../models/User');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get teacher classes
// @route   GET /api/grades/teacher/classes
// @access  Private (Teacher)
exports.getTeacherClasses = asyncHandler(async (req, res, next) => {
  const teacher = await User.findById(req.user.id).populate('classes subjects');

  if (!teacher) {
    return next(new ErrorResponse('Teacher not found', 404));
  }

  const classes = await Class.find({ 
    'teachers.teacher': req.user.id 
  }).populate('students');

  res.status(200).json({
    success: true,
    count: classes.length,
    data: classes
  });
});

// @desc    Get class grades
// @route   GET /api/grades/class/:id/subject/:subjectId
// @access  Private (Teacher)
exports.getClassGrades = asyncHandler(async (req, res, next) => {
  const { id: classId, subjectId } = req.params;
  const { period = '1' } = req.query;

  const classData = await Class.findById(classId).populate('students');
  if (!classData) {
    return next(new ErrorResponse('Class not found', 404));
  }

  const students = await Student.find({ classId });

  const grades = await Grade.find({
    classId,
    subject: subjectId,
    period,
    teacher: req.user.id
  }).populate('student');

  const excuses = await Excuse.find({
    classId,
    subject: subjectId,
    period
  });

  const result = students.map(student => {
    const grade = grades.find(g => g.student._id.toString() === student._id.toString());
    const excuse = excuses.find(e => e.student.toString() === student._id.toString());

    return {
      studentId: student._id,
      studentName: student.name,
      classwork: grade ? grade.classwork : null,
      exam: grade ? grade.exam : null,
      total: grade ? grade.total : null,
      status: grade ? grade.status : 'draft',
      isExcused: !!excuse,
      excuseReason: excuse ? excuse.reason : '',
      excuseType: excuse ? excuse.type : '',
      notes: grade ? grade.notes : ''
    };
  });

  res.status(200).json({
    success: true,
    count: result.length,
    data: result
  });
});

// @desc    Save grades (draft)
// @route   POST /api/grades/save
// @access  Private (Teacher)
exports.saveGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, grades } = req.body;

  const savedGrades = [];

  for (const gradeData of grades) {
    let grade = await Grade.findOne({
      student: gradeData.studentId,
      subject: subjectId,
      classId,
      period,
      teacher: req.user.id
    });

    if (grade && grade.status === 'locked') {
      continue; // Cannot edit locked grades
    }

    if (grade) {
      grade.classwork = gradeData.classwork;
      grade.exam = gradeData.exam;
      grade.notes = gradeData.notes || '';
      grade.status = 'draft';
    } else {
      grade = await Grade.create({
        student: gradeData.studentId,
        subject: subjectId,
        classId,
        period,
        teacher: req.user.id,
        classwork: gradeData.classwork,
        exam: gradeData.exam,
        notes: gradeData.notes || ''
      });
    }

    await grade.save();
    savedGrades.push(grade);
  }

  res.status(200).json({
    success: true,
    count: savedGrades.length,
    data: savedGrades
  });
});

// @desc    Submit grades (final)
// @route   POST /api/grades/submit
// @access  Private (Teacher)
exports.submitGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type } = req.body;

  const grades = await Grade.find({
    classId,
    subject: subjectId,
    period,
    teacher: req.user.id
  });

  // Check if all students have grades
  const students = await Student.find({ classId });
  const gradedStudents = grades.filter(g => g.classwork !== null && g.exam !== null);

  if (gradedStudents.length < students.length) {
    return next(new ErrorResponse('All students must have grades before submission', 400));
  }

  for (const grade of grades) {
    if (type === 'classwork' || type === 'both') {
      grade.status = 'submitted';
    }
    if (type === 'exam') {
      grade.status = 'submitted';
    }
    grade.submittedAt = Date.now();
    await grade.save();
  }

  res.status(200).json({
    success: true,
    message: 'Grades submitted successfully'
  });
});

// @desc    Get head department classes
// @route   GET /api/grades/head/department-classes
// @access  Private (Head)
exports.getHeadDepartmentClasses = asyncHandler(async (req, res, next) => {
  const head = await User.findById(req.user.id);

  if (!head || head.role !== 'head') {
    return next(new ErrorResponse('Not authorized', 403));
  }

  const subjects = await Subject.find({ department: head.department });
  const subjectIds = subjects.map(s => s._id);

  const classes = await Class.find({
    'teachers.subject': { $in: subjectIds }
  }).populate('teachers.teacher teachers.subject');

  const result = classes.map(cls => {
    const deptTeachers = cls.teachers.filter(t => 
      subjectIds.some(id => id.toString() === t.subject._id.toString())
    );

    return {
      classId: cls._id,
      className: cls.name,
      grade: cls.grade,
      section: cls.section,
      teachers: deptTeachers.map(t => ({
        teacherId: t.teacher._id,
        teacherName: t.teacher.name,
        subjectId: t.subject._id,
        subjectName: t.subject.name,
        isSubmitted: false // Will be updated below
      }))
    };
  });

  // Check submission status
  for (const cls of result) {
    for (const teacher of cls.teachers) {
      const grades = await Grade.find({
        classId: cls.classId,
        teacher: teacher.teacherId,
        subject: teacher.subjectId
      });

      teacher.isSubmitted = grades.length > 0 && grades.every(g => g.status === 'submitted');
      teacher.isApproved = grades.length > 0 && grades.every(g => g.status === 'approved');
    }
  }

  res.status(200).json({
    success: true,
    count: result.length,
    data: result
  });
});

// @desc    Head review grades
// @route   GET /api/grades/head/review/:classId/:teacherId/:subjectId
// @access  Private (Head)
exports.headReviewGrades = asyncHandler(async (req, res, next) => {
  const { classId, teacherId, subjectId } = req.params;
  const { period = '1' } = req.query;

  const grades = await Grade.find({
    classId,
    teacher: teacherId,
    subject: subjectId,
    period
  }).populate('student');

  res.status(200).json({
    success: true,
    count: grades.length,
    data: grades
  });
});

// @desc    Head edit grade
// @route   POST /api/grades/head/edit
// @access  Private (Head)
exports.headEditGrade = asyncHandler(async (req, res, next) => {
  const { gradeId, classwork, exam, notes } = req.body;

  const grade = await Grade.findById(gradeId);
  if (!grade) {
    return next(new ErrorResponse('Grade not found', 404));
  }

  grade.classwork = classwork !== undefined ? classwork : grade.classwork;
  grade.exam = exam !== undefined ? exam : grade.exam;
  grade.notes = notes || grade.notes;
  grade.updatedAt = Date.now();

  await grade.save();

  res.status(200).json({
    success: true,
    data: grade
  });
});

// @desc    Head approve department
// @route   POST /api/grades/head/approve-department
// @access  Private (Head)
exports.headApproveDepartment = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type } = req.body;

  const grades = await Grade.find({
    classId,
    subject: subjectId,
    period
  });

  for (const grade of grades) {
    if (type === 'classwork' || type === 'both') {
      grade.status = 'approved';
    }
    if (type === 'exam') {
      grade.status = 'approved';
    }
    grade.approvedAt = Date.now();
    grade.approvedBy = req.user.id;
    await grade.save();
  }

  res.status(200).json({
    success: true,
    message: 'Department grades approved successfully'
  });
});

// @desc    Get all grades for control
// @route   GET /api/grades/control/all
// @access  Private (Control)
exports.getControlGrades = asyncHandler(async (req, res, next) => {
  const { grade, subject, period, status } = req.query;

  let query = {};
  if (grade) query.grade = grade;
  if (subject) query.subject = subject;
  if (period) query.period = period;
  if (status) query.status = status;

  const grades = await Grade.find(query)
    .populate('student', 'name studentId')
    .populate('subject', 'name')
    .populate('teacher', 'name')
    .populate('classId', 'name grade section');

  res.status(200).json({
    success: true,
    count: grades.length,
    data: grades
  });
});

// @desc    Get print data
// @route   GET /api/grades/control/print
// @access  Private (Control)
exports.getPrintData = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period } = req.query;

  const grades = await Grade.find({
    classId,
    subject: subjectId,
    period,
    status: 'approved'
  })
    .populate('student', 'name studentId')
    .populate('subject', 'name')
    .populate('teacher', 'name')
    .populate('approvedBy', 'name')
    .populate('classId', 'name grade section');

  const classData = await Class.findById(classId).populate('teachers.teacher');

  res.status(200).json({
    success: true,
    data: {
      grades,
      classInfo: classData
    }
  });
});
