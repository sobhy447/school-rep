const Excuse = require('../models/Excuse');
const Student = require('../models/Student');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get all excuses
// @route   GET /api/excuses
// @access  Private
exports.getExcuses = asyncHandler(async (req, res, next) => {
  const { classId, period, type } = req.query;
  let query = {};

  if (classId) query.classId = classId;
  if (period) query.period = period;
  if (type) query.type = type;

  const excuses = await Excuse.find(query)
    .populate('student', 'name studentId')
    .populate('subject', 'name')
    .populate('classId', 'name grade section')
    .populate('createdBy', 'name');

  res.status(200).json({
    success: true,
    count: excuses.length,
    data: excuses
  });
});

// @desc    Create excuse
// @route   POST /api/excuses
// @access  Private (Teacher, Head, Admin)
exports.createExcuse = asyncHandler(async (req, res, next) => {
  const { studentId, subjectId, classId, period, type, reason } = req.body;

  const student = await Student.findById(studentId);
  if (!student) {
    return next(new ErrorResponse('Student not found', 404));
  }

  const excuse = await Excuse.create({
    student: studentId,
    subject: subjectId,
    classId,
    period,
    type,
    reason,
    createdBy: req.user.id
  });

  res.status(201).json({
    success: true,
    data: excuse
  });
});

// @desc    Delete excuse
// @route   DELETE /api/excuses/:id
// @access  Private (Admin, Head)
exports.deleteExcuse = asyncHandler(async (req, res, next) => {
  const excuse = await Excuse.findById(req.params.id);

  if (!excuse) {
    return next(new ErrorResponse('Excuse not found', 404));
  }

  // Only admin or the creator can delete
  if (req.user.role !== 'admin' && excuse.createdBy.toString() !== req.user.id) {
    return next(new ErrorResponse('Not authorized', 403));
  }

  await excuse.deleteOne();

  res.status(200).json({
    success: true,
    message: 'Excuse deleted'
  });
});
