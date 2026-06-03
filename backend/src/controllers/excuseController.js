const Excuse = require('../models/Excuse');
const Student = require('../models/Student');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get all excuses
// @route   GET /api/excuses
// @access  Private (Admin, Head, Teacher)
exports.getExcuses = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type } = req.query;

  const query = { academicYear: new Date().getFullYear() };
  if (classId) query.class = classId;
  if (subjectId) query.subject = subjectId;
  if (period) query.period = period;
  if (type) query.type = type;

  const excuses = await Excuse.find(query)
    .populate('student', 'name studentId')
    .populate('class', 'name gradeLevel')
    .populate('subject', 'name')
    .populate('createdBy', 'name')
    .sort({ createdAt: -1 });

  // Format response
  const formattedExcuses = excuses.map(excuse => ({
    _id: excuse._id,
    studentId: excuse.student.studentId,
    studentName: excuse.student.name,
    className: excuse.class.name,
    gradeLevel: excuse.class.gradeLevel,
    subjectName: excuse.subject.name,
    period: excuse.period,
    type: excuse.type,
    reason: excuse.reason,
    createdBy: excuse.createdBy?.name,
    createdAt: excuse.createdAt
  }));

  res.status(200).json({
    success: true,
    count: formattedExcuses.length,
    data: formattedExcuses
  });
});

// @desc    Add new excuse
// @route   POST /api/excuses
// @access  Private (Admin, Head, Teacher)
exports.addExcuse = asyncHandler(async (req, res, next) => {
  const { classId, studentId, subjectId, period, type, reason } = req.body;

  // Validate
  if (!classId || !studentId || !subjectId || !period || !type || !reason) {
    return next(new ErrorResponse('جميع الحقول مطلوبة', 400));
  }

  // Check if excuse already exists
  const existingExcuse = await Excuse.findOne({
    student: studentId,
    class: classId,
    subject: subjectId,
    period,
    type,
    academicYear: new Date().getFullYear()
  });

  if (existingExcuse) {
    return next(new ErrorResponse('يوجد عذر مسبق لهذا الطالب في نفس المادة والفترة', 400));
  }

  // Create excuse
  const excuse = await Excuse.create({
    student: studentId,
    class: classId,
    subject: subjectId,
    period,
    type,
    reason,
    createdBy: req.user._id,
    academicYear: new Date().getFullYear()
  });

  res.status(201).json({
    success: true,
    message: 'تم إضافة العذر بنجاح',
    data: excuse
  });
});

// @desc    Delete excuse
// @route   DELETE /api/excuses/:id
// @access  Private (Admin, Head)
exports.deleteExcuse = asyncHandler(async (req, res, next) => {
  const excuse = await Excuse.findById(req.params.id);

  if (!excuse) {
    return next(new ErrorResponse('العذر غير موجود', 404));
  }

  // Check permission
  const isAdmin = req.user.role === 'admin';
  const isHead = req.user.role === 'head' && req.user.subject?.toString() === excuse.subject.toString();
  const isCreator = excuse.createdBy.toString() === req.user._id.toString();

  if (!isAdmin && !isHead && !isCreator) {
    return next(new ErrorResponse('غير مصرح لك بحذف هذا العذر', 403));
  }

  await excuse.deleteOne();

  res.status(200).json({
    success: true,
    message: 'تم حذف العذر بنجاح'
  });
});

// @desc    Get students for a class
// @route   GET /api/classes/:id/students
// @access  Private
exports.getClassStudents = asyncHandler(async (req, res, next) => {
  const classData = await Class.findById(req.params.id)
    .populate('students', 'name studentId');

  if (!classData) {
    return next(new ErrorResponse('الفصل غير موجود', 404));
  }

  res.status(200).json({
    success: true,
    data: classData.students
  });
});

// @desc    Get all classes
// @route   GET /api/classes
// @access  Private
exports.getAllClasses = asyncHandler(async (req, res, next) => {
  const classes = await Class.find()
    .populate('gradeLevel', 'name level')
    .populate('students', 'name');

  const formatted = classes.map(cls => ({
    _id: cls._id,
    name: cls.name,
    gradeLevel: cls.gradeLevel?.level || 6,
    studentCount: cls.students.length
  }));

  res.status(200).json({
    success: true,
    data: formatted
  });
});

// @desc    Get all subjects
// @route   GET /api/subjects
// @access  Private
exports.getAllSubjects = asyncHandler(async (req, res, next) => {
  const subjects = await Subject.find().select('name code');

  res.status(200).json({
    success: true,
    data: subjects
  });
});
