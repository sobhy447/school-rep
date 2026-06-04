const Settings = require('../models/Settings');
const Grade = require('../models/Grade');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Get settings
// @route   GET /api/admin/settings
// @access  Private (Admin)
exports.getSettings = asyncHandler(async (req, res, next) => {
  let settings = await Settings.findOne();

  if (!settings) {
    settings = await Settings.create({});
  }

  res.status(200).json({
    success: true,
    data: settings
  });
});

// @desc    Update settings
// @route   PUT /api/admin/settings
// @access  Private (Admin)
exports.updateSettings = asyncHandler(async (req, res, next) => {
  const { activePeriod, activeColumns, schoolName, academicYear } = req.body;

  let settings = await Settings.findOne();

  if (!settings) {
    settings = await Settings.create({});
  }

  if (activePeriod) settings.activePeriod = activePeriod;
  if (activeColumns) settings.activeColumns = activeColumns;
  if (schoolName) settings.schoolName = schoolName;
  if (academicYear) settings.academicYear = academicYear;

  settings.updatedAt = Date.now();
  await settings.save();

  res.status(200).json({
    success: true,
    data: settings
  });
});

// @desc    Unlock grades
// @route   POST /api/admin/unlock
// @access  Private (Admin)
exports.unlockGrades = asyncHandler(async (req, res, next) => {
  const { classId, subjectId, period, teacherId, type } = req.body;

  let query = { classId, period };
  if (subjectId) query.subject = subjectId;
  if (teacherId) query.teacher = teacherId;

  const grades = await Grade.find(query);

  for (const grade of grades) {
    if (type === 'classwork' || type === 'both') {
      grade.status = 'draft';
    }
    if (type === 'exam') {
      grade.status = 'draft';
    }
    grade.submittedAt = null;
    grade.approvedAt = null;
    grade.approvedBy = null;
    await grade.save();
  }

  res.status(200).json({
    success: true,
    message: 'Grades unlocked successfully',
    count: grades.length
  });
});
