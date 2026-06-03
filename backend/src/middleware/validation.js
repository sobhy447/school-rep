const ErrorResponse = require('../utils/ErrorResponse');

// Validate grade input
exports.validateGrade = (req, res, next) => {
  const { work, exam } = req.body;

  if (work !== undefined) {
    if (work < 0 || work > 40) {
      return next(new ErrorResponse('درجة الأعمال يجب أن تكون بين 0 و 40', 400));
    }
  }

  if (exam !== undefined) {
    if (exam < 0 || exam > 60) {
      return next(new ErrorResponse('درجة الاختبار يجب أن تكون بين 0 و 60', 400));
    }
  }

  next();
};

// Validate excuse input
exports.validateExcuse = (req, res, next) => {
  const { studentId, classId, subjectId, period, type, reason } = req.body;

  if (!studentId || !classId || !subjectId || !period || !type || !reason) {
    return next(new ErrorResponse('جميع الحقول مطلوبة', 400));
  }

  if (!['work', 'exam'].includes(type)) {
    return next(new ErrorResponse('نوع العذر يجب أن يكون أعمال أو اختبار', 400));
  }

  if (!['first', 'second'].includes(period)) {
    return next(new ErrorResponse('الفترة يجب أن تكون أولى أو ثانية', 400));
  }

  next();
};
