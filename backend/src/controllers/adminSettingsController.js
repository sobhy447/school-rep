const Settings = require('../models/Settings');
const Grade = require('../models/Grade');
const Class = require('../models/Class');
const Subject = require('../models/Subject');
const User = require('../models/User');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get admin settings
// @route   GET /api/admin/settings
// @access  Private (Admin)
exports.getAdminSettings = asyncHandler(async (req, res, next) => {
  let settings = await Settings.findOne();

  if (!settings) {
    settings = await Settings.create({
      firstPeriodEnabled: true,
      secondPeriodEnabled: true,
      workEnabled: true,
      examEnabled: true
    });
  }

  res.status(200).json({
    success: true,
    data: settings
  });
});

// @desc    Update admin settings
// @route   POST /api/admin/settings
// @access  Private (Admin)
exports.updateAdminSettings = asyncHandler(async (req, res, next) => {
  const { firstPeriodEnabled, secondPeriodEnabled, workEnabled, examEnabled } = req.body;

  let settings = await Settings.findOne();

  if (!settings) {
    settings = new Settings();
  }

  if (firstPeriodEnabled !== undefined) settings.firstPeriodEnabled = firstPeriodEnabled;
  if (secondPeriodEnabled !== undefined) settings.secondPeriodEnabled = secondPeriodEnabled;
  if (workEnabled !== undefined) settings.workEnabled = workEnabled;
  if (examEnabled !== undefined) settings.examEnabled = examEnabled;

  await settings.save();

  res.status(200).json({
    success: true,
    message: 'تم تحديث الإعدادات بنجاح',
    data: settings
  });
});

// @desc    Get all approvals for unlock
// @route   GET /api/admin/approvals
// @access  Private (Admin)
exports.getAllApprovals = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period } = req.query;

  const query = { academicYear: new Date().getFullYear() };
  if (period) query.period = period;

  const grades = await Grade.find(query)
    .populate('class', 'name gradeLevel')
    .populate('subject', 'name')
    .populate('teacher', 'name')
    .populate('student', 'name');

  // Group by class/subject/teacher
  const grouped = {};
  grades.forEach(grade => {
    const key = `${grade.class._id}-${grade.subject._id}-${grade.teacher._id}`;
    if (!grouped[key]) {
      grouped[key] = {
        _id: key,
        classId: grade.class._id,
        className: grade.class.name,
        subjectId: grade.subject._id,
        subjectName: grade.subject.name,
        teacherId: grade.teacher._id,
        teacherName: grade.teacher.name,
        period: grade.period,
        workApproved: false,
        examApproved: false,
        workHeadApproved: false,
        examHeadApproved: false,
        level: 'teacher'
      };
    }

    if (grade.workSubmitted) grouped[key].workApproved = true;
    if (grade.examSubmitted) grouped[key].examApproved = true;
    if (grade.workDeptApproved) grouped[key].workHeadApproved = true;
    if (grade.examDeptApproved) grouped[key].examHeadApproved = true;
  });

  // Create approval records for both levels
  const approvals = [];
  Object.values(grouped).forEach(group => {
    // Teacher level
    if (group.workApproved || group.examApproved) {
      approvals.push({
        ...group,
        _id: `${group._id}-teacher`,
        level: 'teacher',
        workApproved: group.workApproved,
        examApproved: group.examApproved,
        workTeacherStatus: group.workApproved ? 'approved' : 'pending',
        examTeacherStatus: group.examApproved ? 'approved' : 'pending'
      });
    }

    // Head level
    if (group.workHeadApproved || group.examHeadApproved) {
      approvals.push({
        ...group,
        _id: `${group._id}-head`,
        level: 'head',
        workApproved: group.workHeadApproved,
        examApproved: group.examHeadApproved,
        workHeadStatus: group.workHeadApproved ? 'approved' : 'pending',
        examHeadStatus: group.examHeadApproved ? 'approved' : 'pending'
      });
    }
  });

  // Filter
  let result = approvals;
  if (classId) result = result.filter(a => a.classId.toString() === classId);
  if (subjectId) result = result.filter(a => a.subjectId.toString() === subjectId);

  res.status(200).json({
    success: true,
    count: result.length,
    data: result
  });
});

// @desc    Unlock approval (admin)
// @route   POST /api/admin/unlock
// @access  Private (Admin)
exports.unlockApproval = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, type, level } = req.body;

  if (!classId || !subjectId || !period || !level) {
    return next(new ErrorResponse('بيانات غير مكتملة', 400));
  }

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
